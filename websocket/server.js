/**
 * Socket.io Server for Jitsi Video Conferencing
 * Handles real-time session events, participant tracking, chat, and AI features
 */

const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');
const axios = require('axios');
const Redis = require('ioredis');

const app = express();
const server = http.createServer(app);

// CORS configuration
app.use(cors({
    origin: process.env.FRONTEND_URLS?.split(',') || ['http://localhost:5173', 'http://localhost:5174'],
    credentials: true
}));

app.use(express.json());

// Socket.io server with CORS
const io = new Server(server, {
    cors: {
        origin: process.env.FRONTEND_URLS?.split(',') || ['http://localhost:5173', 'http://localhost:5174'],
        methods: ['GET', 'POST'],
        credentials: true
    }
});

// Redis client for Laravel integration
const redis = new Redis({
    host: process.env.REDIS_HOST || '127.0.0.1',
    port: process.env.REDIS_PORT || 6379,
    password: process.env.REDIS_PASSWORD || undefined,
    db: process.env.REDIS_DB || 0
});

// Redis subscriber for Laravel events
const redisSubscriber = new Redis({
    host: process.env.REDIS_HOST || '127.0.0.1',
    port: process.env.REDIS_PORT || 6379,
    password: process.env.REDIS_PASSWORD || undefined,
    db: process.env.REDIS_DB || 0
});

const LARAVEL_API_URL = process.env.LARAVEL_API_URL || 'http://localhost:8000/api/v1';

// Active sessions tracking
const activeSessions = new Map(); // sessionId -> { participants: Map, metadata: {} }

/**
 * Authenticate socket connection using JWT token
 */
async function authenticateSocket(socket) {
    const token = socket.handshake.auth.token || socket.handshake.query.token;
    
    if (!token) {
        throw new Error('No authentication token provided');
    }

    try {
        // Verify token with Laravel backend
        const response = await axios.get(`${LARAVEL_API_URL}/auth/me`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        });

        return response.data;
    } catch (error) {
        throw new Error('Authentication failed: ' + error.message);
    }
}

/**
 * Join session room and track participant
 */
async function handleSessionJoin(socket, sessionId, userData) {
    const roomName = `session:${sessionId}`;
    
    // Join the Socket.io room
    socket.join(roomName);
    socket.sessionId = sessionId;
    socket.userData = userData;

    // Initialize session tracking if not exists
    if (!activeSessions.has(sessionId)) {
        activeSessions.set(sessionId, {
            participants: new Map(),
            startedAt: Date.now(),
            metadata: {}
        });
    }

    const session = activeSessions.get(sessionId);
    
    // Add participant
    session.participants.set(userData.id, {
        socketId: socket.id,
        userId: userData.id,
        name: userData.name,
        email: userData.email,
        joinedAt: Date.now(),
        micActive: false,
        cameraActive: false,
        handsRaised: 0,
        chatMessages: 0
    });

    // Notify room of new participant
    socket.to(roomName).emit('participant:joined', {
        userId: userData.id,
        name: userData.name,
        timestamp: new Date().toISOString()
    });

    // Send current participant list to joining user
    const participantList = Array.from(session.participants.values()).map(p => ({
        userId: p.userId,
        name: p.name,
        micActive: p.micActive,
        cameraActive: p.cameraActive,
        handsRaised: p.handsRaised
    }));

    socket.emit('session:joined', {
        sessionId,
        room: roomName,
        participants: participantList,
        participantCount: session.participants.size
    });

    console.log(`User ${userData.name} (${userData.id}) joined session ${sessionId}`);
    
    // Update Laravel backend
    try {
        await axios.post(`${LARAVEL_API_URL}/sessions/${sessionId}/participant-metrics`, {
            socket_id: socket.id
        }, {
            headers: { 'Authorization': `Bearer ${socket.handshake.auth.token}` }
        });
    } catch (error) {
        console.error('Failed to notify Laravel of participant join:', error.message);
    }
}

/**
 * Handle participant leaving session
 */
async function handleSessionLeave(socket, sessionId) {
    const roomName = `session:${sessionId}`;
    
    socket.leave(roomName);

    const session = activeSessions.get(sessionId);
    if (session) {
        const participant = session.participants.get(socket.userData?.id);
        
        if (participant) {
            // Notify others
            socket.to(roomName).emit('participant:left', {
                userId: socket.userData.id,
                name: socket.userData.name,
                duration: Date.now() - participant.joinedAt,
                timestamp: new Date().toISOString()
            });

            // Remove participant
            session.participants.delete(socket.userData.id);

            // Clean up empty sessions
            if (session.participants.size === 0) {
                activeSessions.delete(sessionId);
                console.log(`Session ${sessionId} cleaned up (no participants)`);
            }
        }
    }

    delete socket.sessionId;
    delete socket.userData;

    console.log(`Socket ${socket.id} left session ${sessionId}`);
}

/**
 * Handle mute/unmute events
 */
function handleParticipantMute(socket, sessionId, data) {
    const roomName = `session:${sessionId}`;
    const session = activeSessions.get(sessionId);
    
    if (session && socket.userData) {
        const participant = session.participants.get(socket.userData.id);
        if (participant) {
            participant.micActive = !data.muted;
            participant.cameraActive = !data.videoMuted;
        }
    }

    // Broadcast to all participants in session
    io.to(roomName).emit('participant:mute', {
        userId: socket.userData?.id,
        muted: data.muted,
        videoMuted: data.videoMuted,
        timestamp: new Date().toISOString()
    });

    console.log(`User ${socket.userData?.name} mute status: audio=${data.muted}, video=${data.videoMuted}`);
}

/**
 * Handle hand raise/lower
 */
function handleParticipantHand(socket, sessionId, raised) {
    const roomName = `session:${sessionId}`;
    const session = activeSessions.get(sessionId);
    
    if (session && socket.userData) {
        const participant = session.participants.get(socket.userData.id);
        if (participant) {
            if (raised) {
                participant.handsRaised++;
            }
        }
    }

    io.to(roomName).emit('participant:hand', {
        userId: socket.userData?.id,
        raised: raised,
        timestamp: new Date().toISOString()
    });

    console.log(`User ${socket.userData?.name} hand ${raised ? 'raised' : 'lowered'}`);
}

/**
 * Handle chat messages with AI integration
 */
async function handleChatMessage(socket, sessionId, data) {
    const roomName = `session:${sessionId}`;
    const message = data.message || data.text || '';
    
    // Track chat message
    const session = activeSessions.get(sessionId);
    if (session && socket.userData) {
        const participant = session.participants.get(socket.userData.id);
        if (participant) {
            participant.chatMessages++;
        }
    }

    // Check for AI command (@AI)
    if (message.trim().startsWith('@AI')) {
        const question = message.replace('@AI', '').trim();
        
        // Broadcast the original question
        io.to(roomName).emit('chat:message', {
            id: generateMessageId(),
            sender: {
                userId: socket.userData?.id,
                name: socket.userData?.name
            },
            text: message,
            timestamp: new Date().toISOString(),
            isAI: false
        });

        try {
            // Call Laravel backend for AI answer
            const response = await axios.post(`${LARAVEL_API_URL}/sessions/${sessionId}/ask-ai`, {
                question: question
            }, {
                headers: { 
                    'Authorization': `Bearer ${socket.handshake.auth.token}`,
                    'Accept': 'application/json'
                }
            });

            // Broadcast AI answer
            io.to(roomName).emit('chat:message', {
                id: generateMessageId(),
                sender: {
                    userId: 'ai-assistant',
                    name: 'AI Assistant',
                    isAI: true
                },
                text: response.data.answer,
                replyTo: message,
                timestamp: new Date().toISOString(),
                isAI: true
            });

        } catch (error) {
            console.error('AI answer failed:', error.message);
            
            io.to(roomName).emit('chat:message', {
                id: generateMessageId(),
                sender: {
                    userId: 'ai-assistant',
                    name: 'AI Assistant',
                    isAI: true
                },
                text: 'Sorry, I was unable to process your question at this time.',
                replyTo: message,
                timestamp: new Date().toISOString(),
                isAI: true,
                error: true
            });
        }
    } else {
        // Regular chat message
        io.to(roomName).emit('chat:message', {
            id: generateMessageId(),
            sender: {
                userId: socket.userData?.id,
                name: socket.userData?.name
            },
            text: message,
            timestamp: new Date().toISOString(),
            isAI: false
        });
    }
}

/**
 * Handle transcript updates from Laravel
 */
function handleTranscriptNew(data) {
    const { session_id, speaker, text, segments, time } = data;
    const roomName = `session:${session_id}`;
    
    io.to(roomName).emit('transcript:new', {
        id: generateMessageId(),
        speaker: speaker,
        text: text,
        segments: segments,
        timestamp: time
    });

    console.log(`Transcript broadcast for session ${session_id}: ${text.substring(0, 50)}...`);
}

/**
 * Broadcast engagement stats periodically
 */
function broadcastEngagementUpdate(sessionId) {
    const session = activeSessions.get(sessionId);
    if (!session || session.participants.size === 0) return;

    const roomName = `session:${sessionId}`;
    const stats = Array.from(session.participants.values()).map(p => ({
        userId: p.userId,
        name: p.name,
        micActive: p.micActive,
        cameraActive: p.cameraActive,
        handsRaised: p.handsRaised,
        chatMessages: p.chatMessages,
        joinDuration: Math.floor((Date.now() - p.joinedAt) / 1000)
    }));

    io.to(roomName).emit('engagement:update', {
        sessionId,
        timestamp: new Date().toISOString(),
        participantCount: session.participants.size,
        participants: stats
    });
}

/**
 * Start engagement broadcast interval
 */
function startEngagementBroadcast(sessionId) {
    const session = activeSessions.get(sessionId);
    if (!session || session.engagementInterval) return;

    session.engagementInterval = setInterval(() => {
        broadcastEngagementUpdate(sessionId);
    }, 30000); // Every 30 seconds
}

/**
 * Stop engagement broadcast
 */
function stopEngagementBroadcast(sessionId) {
    const session = activeSessions.get(sessionId);
    if (session && session.engagementInterval) {
        clearInterval(session.engagementInterval);
        session.engagementInterval = null;
    }
}

/**
 * Generate unique message ID
 */
function generateMessageId() {
    return `msg_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
}

/**
 * Redis subscriber for Laravel events
 */
redisSubscriber.subscribe('jitsi-events', (err, count) => {
    if (err) {
        console.error('Redis subscription error:', err);
    } else {
        console.log(`Subscribed to ${count} Redis channel(s)`);
    }
});

redisSubscriber.on('message', (channel, message) => {
    try {
        const event = JSON.parse(message);
        
        switch (event.type) {
            case 'transcript:new':
                handleTranscriptNew(event.data);
                break;
            case 'session:ended':
                stopEngagementBroadcast(event.data.session_id);
                break;
            case 'session:started':
                startEngagementBroadcast(event.data.session_id);
                break;
            default:
                console.log('Unknown Redis event:', event.type);
        }
    } catch (error) {
        console.error('Error processing Redis message:', error);
    }
});

/**
 * Socket.io connection handler
 */
io.on('connection', async (socket) => {
    console.log(`Socket connected: ${socket.id}`);

    try {
        // Authenticate connection
        const userData = await authenticateSocket(socket);
        socket.userData = userData;
        
        console.log(`User authenticated: ${userData.name} (${userData.id})`);

        // Session events
        socket.on('session:join', async (data) => {
            const { sessionId } = data;
            await handleSessionJoin(socket, sessionId, userData);
            
            // Start engagement broadcast for this session
            startEngagementBroadcast(sessionId);
        });

        socket.on('session:leave', async (data) => {
            const { sessionId } = data;
            await handleSessionLeave(socket, sessionId);
        });

        // Participant events
        socket.on('participant:mute', (data) => {
            handleParticipantMute(socket, socket.sessionId, data);
        });

        socket.on('participant:hand', (data) => {
            handleParticipantHand(socket, socket.sessionId, data.raised);
        });

        // Chat events
        socket.on('chat:message', async (data) => {
            await handleChatMessage(socket, socket.sessionId, data);
        });

        // Jitsi iframe events relay
        socket.on('jitsi:event', (data) => {
            const { type, eventData } = data;
            const roomName = `session:${socket.sessionId}`;
            
            // Relay specific events to room
            switch (type) {
                case 'participantJoined':
                case 'participantLeft':
                case 'audioMuteStatusChanged':
                case 'videoMuteStatusChanged':
                case 'raiseHandUpdated':
                case 'connectionQualityChanged':
                    io.to(roomName).emit(`jitsi:${type}`, eventData);
                    break;
            }
        });

        // Disconnect handler
        socket.on('disconnect', async (reason) => {
            console.log(`Socket disconnected: ${socket.id}, reason: ${reason}`);
            
            if (socket.sessionId) {
                await handleSessionLeave(socket, socket.sessionId);
            }
        });

    } catch (error) {
        console.error('Socket authentication failed:', error.message);
        socket.emit('error', { message: 'Authentication failed' });
        socket.disconnect(true);
    }
});

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'healthy',
        connections: io.engine.clientsCount,
        activeSessions: activeSessions.size,
        timestamp: new Date().toISOString()
    });
});

// Admin endpoint to get active sessions
app.get('/admin/sessions', async (req, res) => {
    const adminToken = req.headers.authorization;
    
    // Basic token validation (implement proper validation)
    if (adminToken !== `Bearer ${process.env.ADMIN_TOKEN}`) {
        return res.status(401).json({ error: 'Unauthorized' });
    }

    const sessions = Array.from(activeSessions.entries()).map(([id, session]) => ({
        sessionId: id,
        participantCount: session.participants.size,
        startedAt: session.startedAt,
        participants: Array.from(session.participants.values()).map(p => ({
            userId: p.userId,
            name: p.name,
            joinedAt: p.joinedAt,
            micActive: p.micActive,
            cameraActive: p.cameraActive
        }))
    }));

    res.json({ sessions });
});

// Error handling
process.on('unhandledRejection', (reason, promise) => {
    console.error('Unhandled Rejection at:', promise, 'reason:', reason);
});

process.on('uncaughtException', (error) => {
    console.error('Uncaught Exception:', error);
    process.exit(1);
});

// Start server
const PORT = process.env.SOCKET_PORT || 3001;
server.listen(PORT, () => {
    console.log(`Socket.io server running on port ${PORT}`);
    console.log(`Laravel API: ${LARAVEL_API_URL}`);
    console.log(`Redis: ${process.env.REDIS_HOST || '127.0.0.1'}:${process.env.REDIS_PORT || 6379}`);
});
