# LMS Jitsi WebSocket Server

Real-time Socket.io server for Jitsi video conferencing integration.

## Features

- **Session Management**: Join/leave session rooms with participant tracking
- **Live Chat**: Real-time messaging with AI assistant integration (@AI)
- **Transcription Broadcasting**: Live transcript updates from Whisper AI
- **Engagement Metrics**: 30-second interval participant stats broadcast
- **Jitsi Events**: Relay mute, hand raise, and other Jitsi iframe events

## Events

### Client → Server

| Event | Payload | Description |
|-------|---------|-------------|
| `session:join` | `{ sessionId }` | Join a session room |
| `session:leave` | `{ sessionId }` | Leave a session room |
| `participant:mute` | `{ muted, videoMuted }` | Broadcast mute status |
| `participant:hand` | `{ raised }` | Hand raise/lower |
| `chat:message` | `{ message }` | Send chat message |
| `jitsi:event` | `{ type, eventData }` | Relay Jitsi iframe events |

### Server → Client

| Event | Payload | Description |
|-------|---------|-------------|
| `session:joined` | `{ sessionId, room, participants, participantCount }` | Join confirmation |
| `participant:joined` | `{ userId, name, timestamp }` | New participant joined |
| `participant:left` | `{ userId, name, duration, timestamp }` | Participant left |
| `participant:mute` | `{ userId, muted, videoMuted, timestamp }` | Mute status changed |
| `participant:hand` | `{ userId, raised, timestamp }` | Hand status changed |
| `chat:message` | `{ id, sender, text, isAI, replyTo, timestamp }` | Chat message |
| `transcript:new` | `{ id, speaker, text, segments, timestamp }` | Live transcription |
| `engagement:update` | `{ sessionId, participantCount, participants, timestamp }` | Stats update (30s) |

## Setup

```bash
cd websocket
npm install
cp .env.example .env
# Edit .env with your configuration
npm start
```

## Environment Variables

```env
SOCKET_PORT=3001
LARAVEL_API_URL=http://localhost:8000/api/v1
ADMIN_TOKEN=your-secure-admin-token
FRONTEND_URLS=http://localhost:5173,http://localhost:5174
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

## Production Deployment

Using PM2:

```bash
npm run pm2:start
```

## Laravel Integration

The Laravel backend broadcasts events to Redis channel `jitsi-events`.
The Node.js server subscribes to these events and forwards them to connected clients.

Events published by Laravel:
- `transcript:new` - When AI transcription is completed
- `session:started` - When session goes live
- `session:ended` - When session ends
