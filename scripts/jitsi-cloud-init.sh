#!/bin/bash
# Cloud-init script for automated Jitsi Meet server setup
# Tested on Ubuntu 22.04 LTS
# Usage: Add this as user-data when creating a new VPS

set -e

# Configuration - CODAGENZ PRODUCTION SETUP
JITSI_DOMAIN="meet.codagenz.com"     # Jitsi Meet domain
JITSI_APP_ID="apes-lms-production"     # App ID for APES UDOM LMS
# IMPORTANT: Generate a real secret with: openssl rand -hex 32
JITSI_APP_SECRET="GENERATE_WITH_OPENSSL_RAND_HEX_32"
ADMIN_EMAIL="admin@codagenz.com"      # For Let's Encrypt notifications

# Frontend URLs (for CORS and notifications)
INSTRUCTOR_URL="https://apesguide.codagenz.com"
STUDENT_URL="https://apesudom.codagenz.com"
API_URL="https://api.codagenz.com"

# System update
echo "=== Updating system ==="
apt-get update
apt-get upgrade -y

# Install dependencies
echo "=== Installing dependencies ==="
apt-get install -y \
    apt-transport-https \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    software-properties-common \
    nginx-full \
    apache2-utils \
    git \
    build-essential \
    pkg-config \
    libfreetype6-dev \
    libfontconfig1-dev \
    libxcb-xfixes0-dev \
    libxcb-shape0-dev

# Add Jitsi repository
echo "=== Adding Jitsi repository ==="
curl -fsSL https://download.jitsi.org/jitsi-key.gpg.key | gpg --dearmor -o /usr/share/keyrings/jitsi-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/jitsi-keyring.gpg] https://download.jitsi.org stable/" | tee /etc/apt/sources.list.d/jitsi-stable.list

# Add Jibri repository
echo "=== Adding Jibri repository ==="
curl -fsSL https://download.jitsi.org/jitsi-key.gpg.key | gpg --dearmor -o /usr/share/keyrings/jitsi-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/jitsi-keyring.gpg] https://download.jitsi.org stable/" | tee /etc/apt/sources.list.d/jitsi-stable.list

apt-get update

# Set debconf selections for unattended install
echo "=== Configuring debconf ==="
echo "jitsi-videobridge2 jitsi-videobridge2/jvb-hostname string ${JITSI_DOMAIN}" | debconf-set-selections
echo "jitsi-meet-web-config jitsi-meet/cert-choice select Generate a new self-signed certificate" | debconf-set-selections

# Install Jitsi Meet
echo "=== Installing Jitsi Meet ==="
apt-get install -y jitsi-meet

# Install Jibri (for recording)
echo "=== Installing Jibri ==="
apt-get install -y jibri

# Install token authentication
echo "=== Installing JWT token support ==="
apt-get install -y jitsi-meet-tokens

# Configure Prosody for JWT
echo "=== Configuring JWT authentication ==="
cat > /etc/prosody/conf.d/${JITSI_DOMAIN}.cfg.lua << 'EOF'
-- Prosody configuration for JWT authentication
plugin_paths = { "/usr/share/jitsi-meet/prosody-plugins/" }

-- Enable required modules
modules_enabled = {
    "bosh";
    "pubsub";
    "ping";
    "speakerstats";
    "turncredentials";
    "conference_duration";
    "muc_lobby_rooms";
    "av_moderation";
    "room_metadata";
}

-- Authentication
authentication = "token"
app_id = "APP_ID_PLACEHOLDER"
app_secret = "APP_SECRET_PLACEHOLDER"
asap_accepted_issuers = { "APP_ID_PLACEHOLDER" }
asap_accepted_audiences = { "jitsi" }
allow_empty_token = false

-- MUC (Multi-User Chat) configuration
Component "conference.JITSI_DOMAIN_PLACEHOLDER" "muc"
    name = "Jitsi MUC"
    modules_enabled = {
        "muc_meeting_id";
        "muc_domain_mapper";
        "token_verification";
    }
    admins = { "focus@auth.JITSI_DOMAIN_PLACEHOLDER" }
    restrict_room_creation = true
    muc_room_locking = false
    muc_room_default_public_jids = true

-- Focus component
Component "focus.JITSI_DOMAIN_PLACEHOLDER"
    component_secret = "focus_secret_change_me"

-- JVB component
Component "jitsi-videobridge.JITSI_DOMAIN_PLACEHOLDER"
    component_secret = "jvb_secret_change_me"
EOF

# Replace placeholders
sed -i "s/APP_ID_PLACEHOLDER/${JITSI_APP_ID}/g" /etc/prosody/conf.d/${JITSI_DOMAIN}.cfg.lua
sed -i "s/APP_SECRET_PLACEHOLDER/${JITSI_APP_SECRET}/g" /etc/prosody/conf.d/${JITSI_DOMAIN}.cfg.lua
sed -i "s/JITSI_DOMAIN_PLACEHOLDER/${JITSI_DOMAIN}/g" /etc/prosody/conf.d/${JITSI_DOMAIN}.cfg.lua

# Configure Jicofo
echo "=== Configuring Jicofo ==="
cat > /etc/jitsi/jicofo/jicofo.conf << 'EOF'
jicofo {
  authentication {
    enabled = true
    type = JWT
    login-url = "JITSI_DOMAIN_PLACEHOLDER"
  }
  
  bridge {
    brewery-jid = "JvbBrewery@internal.auth.JITSI_DOMAIN_PLACEHOLDER"
  }
  
  conference {
    max-video-senders = 25
    min-video-senders = 5
  }
  
  jibri {
    brewery-jid = "JibriBrewery@internal.auth.JITSI_DOMAIN_PLACEHOLDER"
    pending-timeout = 90
  }
}
EOF

sed -i "s/JITSI_DOMAIN_PLACEHOLDER/${JITSI_DOMAIN}/g" /etc/jitsi/jicofo/jicofo.conf

# Configure Jitsi Meet web interface
echo "=== Configuring Jitsi Meet web ==="
cat > /etc/jitsi/meet/${JITSI_DOMAIN}-config.js << 'EOF'
var config = {
    hosts: {
        domain: 'JITSI_DOMAIN_PLACEHOLDER',
        muc: 'conference.JITSI_DOMAIN_PLACEHOLDER',
        bridge: 'jitsi-videobridge.JITSI_DOMAIN_PLACEHOLDER',
        focus: 'focus.JITSI_DOMAIN_PLACEHOLDER'
    },
    bosh: '//JITSI_DOMAIN_PLACEHOLDER/http-bind',
    websocket: 'wss://JITSI_DOMAIN_PLACEHOLDER/xmpp-websocket',
    clientNode: 'http://jitsi.org/jitsimeet',
    
    // Enable pre-join page
    prejoinPageEnabled: true,
    
    // Disable P2P (force JVB routing)
    p2p: {
        enabled: false
    },
    
    // Video constraints
    constraints: {
        video: {
            height: {
                ideal: 720,
                max: 1080,
                min: 240
            }
        }
    },
    
    // Bandwidth limits
    channelLastN: 25,
    
    // Recording
    recordingService: {
        enabled: true,
        sharingEnabled: true,
        hideStorageWarning: false
    },
    
    // Live streaming
    liveStreaming: {
        enabled: false
    },
    
    // Security
    enableInsecureRoomNameWarning: false,
    
    // JWT authentication
    enableUserRolesBasedOnToken: true,
    
    // Analytics (disable for privacy)
    analytics: {
        disabled: true
    },
    
    // Breakout rooms
    breakoutRooms: {
        hideAddRoomButton: false
    },
    
    // Polls
    polls: {
        disabled: false
    },
    
    // Transcription
    transcription: {
        enabled: true,
        useAppLanguage: true,
        preferredLanguage: 'en-US',
        disableStartForAll: false
    }
};
EOF

sed -i "s/JITSI_DOMAIN_PLACEHOLDER/${JITSI_DOMAIN}/g" /etc/jitsi/meet/${JITSI_DOMAIN}-config.js

# Configure Jibri
echo "=== Configuring Jibri ==="
useradd -r -g jibri -G adm,audio,video,plugdev jibri 2>/dev/null || true

mkdir -p /config/recordings
chown -R jibri:jibri /config/recordings
chmod 755 /config/recordings

# Create finalize recording script
cat > /config/finalize_recording.sh << 'EOF'
#!/bin/bash
# Finalize recording script - customize this for S3 upload
RECORDING_FILE="$1"
BASENAME=$(basename "$RECORDING_FILE")

# Log the recording
logger -t jibri "Recording finalized: $RECORDING_FILE"

# TODO: Add S3 upload command here
# Example: aws s3 cp "$RECORDING_FILE" "s3://your-bucket/recordings/$BASENAME"

# Clean up local file after upload (optional)
# rm "$RECORDING_FILE"
EOF

chmod +x /config/finalize_recording.sh
chown jibri:jibri /config/finalize_recording.sh

# Configure Jibri service
cat > /etc/jitsi/jibri/jibri.conf << 'EOF'
jibri {
  recording {
    recordings-directory = "/config/recordings"
    finalize-script = "/config/finalize_recording.sh"
  }
  
  streaming {
    rtmp-allow-list = ["*.youtube.com", "*.facebook.com"]
  }
  
  api {
    http {
      host = "0.0.0.0"
      port = 2222
    }
    xmpp {
      environments = [{
        name = "LMS Environment"
        xmpp-server-hosts = ["JITSI_DOMAIN_PLACEHOLDER"]
        xmpp-domain = "JITSI_DOMAIN_PLACEHOLDER"
        
        control-muc {
          domain = "internal.auth.JITSI_DOMAIN_PLACEHOLDER"
          room-name = "JibriBrewery"
          nickname = "jibri"
        }
        
        control-login {
          domain = "auth.JITSI_DOMAIN_PLACEHOLDER"
          username = "jibri"
          password = "jibri_secret_change_me"
        }
        
        call-login {
          domain = "recorder.JITSI_DOMAIN_PLACEHOLDER"
          username = "recorder"
          password = "recorder_secret_change_me"
        }
        
        strip-from-room-domain = "conference."
        usage-timeout = 0
        trust-all-xmpp-certs = true
      }]
    }
  }
  
  ffmpeg {
    resolution = "1920x1080"
    video-encoder = "x264"
    audio-encoder = "aac"
    audio-source = "pulse"
    audio-device = "default"
    audio-sample-rate = 44100
    audio-channels = 2
  }
  
  chrome {
    flags = [
      "--use-fake-ui-for-media-stream",
      "--start-maximized",
      "--kiosk",
      "--enabled",
      "--disable-infobars",
      "--autoplay-policy=no-user-gesture-required"
    ]
  }
  
  stats {
    enable-stats-d = true
  }
  
  webhook {
    subscribers = []
  }
  
  jwt-info {
    # JWT configuration if needed
  }
}
EOF

sed -i "s/JITSI_DOMAIN_PLACEHOLDER/${JITSI_DOMAIN}/g" /etc/jitsi/jibri/jibri.conf

# Install Chrome and ChromeDriver for Jibri
echo "=== Installing Chrome for Jibri ==="
wget -q -O - https://dl-ssl.google.com/linux/linux_signing_key.pub | apt-key add -
echo "deb http://dl.google.com/linux/chrome/deb/ stable main" | tee /etc/apt/sources.list.d/google-chrome.list
apt-get update
apt-get install -y google-chrome-stable

# Install ChromeDriver
CHROME_VERSION=$(google-chrome --version | grep -oP '\d+\.\d+\.\d+')
CHROMEDRIVER_VERSION=$(curl -s "https://chromedriver.storage.googleapis.com/LATEST_RELEASE_${CHROME_VERSION%%.*}")
wget -q "https://chromedriver.storage.googleapis.com/${CHROMEDRIVER_VERSION}/chromedriver_linux64.zip"
unzip chromedriver_linux64.zip -d /usr/local/bin/
chmod +x /usr/local/bin/chromedriver
rm chromedriver_linux64.zip

# Setup ALSA loopback for audio
echo "=== Setting up audio loopback ==="
echo "options snd-aloop index=1" > /etc/modprobe.d/alsa-loopback.conf
modprobe snd-aloop

# Add jibri user to required groups
usermod -a -G adm,audio,video,plugdev jibri

# Restart all services
echo "=== Restarting services ==="
systemctl restart prosody
systemctl restart jicofo
systemctl restart jitsi-videobridge2
systemctl restart jibri

# Get Let's Encrypt SSL certificate
echo "=== Obtaining SSL certificate ==="
/usr/share/jitsi-meet/scripts/install-letsencrypt-cert.sh << EOF
${ADMIN_EMAIL}
EOF

# Open firewall ports
echo "=== Configuring firewall ==="
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 10000/udp
ufw allow 4443/tcp
ufw allow 5222/tcp
ufw allow 5347/tcp
ufw allow 2222/tcp  # Jibri API

# Health check endpoint
cat > /usr/local/bin/jitsi-health-check.sh << 'EOF'
#!/bin/bash
# Health check script

echo "=== Jitsi Health Check ==="
echo "Date: $(date)"
echo ""

echo "=== Service Status ==="
systemctl is-active prosody && echo "✓ Prosody running" || echo "✗ Prosody not running"
systemctl is-active jicofo && echo "✓ Jicofo running" || echo "✗ Jicofo not running"
systemctl is-active jitsi-videobridge2 && echo "✓ JVB running" || echo "✗ JVB not running"
systemctl is-active jibri && echo "✓ Jibri running" || echo "✗ Jibri not running"
systemctl is-active nginx && echo "✓ Nginx running" || echo "✗ Nginx not running"

echo ""
echo "=== Disk Usage ==="
df -h /config/recordings

echo ""
echo "=== Memory Usage ==="
free -h

echo ""
echo "=== Active Connections ==="
netstat -tuln | grep -E '5222|5347|8080|8443|10000|2222'
EOF

chmod +x /usr/local/bin/jitsi-health-check.sh

# Create info file
cat > /root/jitsi-setup-info.txt << EOF
========================================
Jitsi Meet Server Setup Complete
========================================

Domain: ${JITSI_DOMAIN}
App ID: ${JITSI_APP_ID}
App Secret: ${JITSI_APP_SECRET}

Services:
- Prosody (XMPP): Running on port 5222
- Jicofo (Focus): Running
- JVB (Video Bridge): Running on port 10000/udp
- Jibri (Recording): Running on port 2222
- Nginx (Web): Running on ports 80/443

Configuration Files:
- Prosody: /etc/prosody/conf.d/${JITSI_DOMAIN}.cfg.lua
- Jicofo: /etc/jitsi/jicofo/jicofo.conf
- Jitsi Meet: /etc/jitsi/meet/${JITSI_DOMAIN}-config.js
- Jibri: /etc/jitsi/jibri/jibri.conf

Health Check:
Run: /usr/local/bin/jitsi-health-check.sh

Next Steps:
1. Update DNS A record for ${JITSI_DOMAIN} to point to this server
2. Test JWT token generation from your Laravel backend
3. Configure S3 upload in /config/finalize_recording.sh
4. Test recording functionality

Security Notes:
- Keep your JITSI_APP_SECRET secure
- Restrict access to port 2222 (Jibri) to backend only
- Monitor /config/recordings for disk space

========================================
EOF

echo ""
echo "========================================"
echo "Jitsi Meet Server Setup Complete!"
echo "========================================"
echo "Domain: ${JITSI_DOMAIN}"
echo "App ID: ${JITSI_APP_ID}"
echo ""
echo "View details: cat /root/jitsi-setup-info.txt"
echo "Run health check: /usr/local/bin/jitsi-health-check.sh"
echo ""
echo "IMPORTANT: Ensure DNS A record for ${JITSI_DOMAIN}"
echo "points to this server's IP address before testing."
echo "========================================"
