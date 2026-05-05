#!/bin/bash
# CODAGENZ PRODUCTION DEPLOYMENT SCRIPT
# Run this on your VPS after SSH: ssh natakahii@server.natakahii.com
# Backend path: /var/www/fyp-backend

set -e

echo "========================================"
echo "CODAGENZ PRODUCTION DEPLOYMENT"
echo "========================================"
echo ""

# Configuration
BACKEND_PATH="/var/www/fyp-backend"
WEBSOCKET_PATH="/var/www/fyp-backend/websocket"
PHP_VERSION="8.2"  # Update if using different version

echo "Step 1: Navigate to backend directory"
cd "$BACKEND_PATH"

echo "Step 2: Pull latest code from git"
git pull origin main || echo "Git pull failed or not a git repo, continuing..."

echo "Step 3: Install PHP dependencies (production only)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "Step 4: Run database migrations"
php artisan migrate --force

echo "Step 5: Clear and cache configuration"
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Step 6: Create storage link if not exists"
php artisan storage:link || echo "Storage link already exists"

echo "Step 7: Set correct permissions"
chown -R www-data:www-data "$BACKEND_PATH/storage"
chown -R www-data:www-data "$BACKEND_PATH/bootstrap/cache"
chmod -R 775 "$BACKEND_PATH/storage"
chmod -R 775 "$BACKEND_PATH/bootstrap/cache"

echo "Step 8: Restart PHP-FPM"
systemctl restart php${PHP_VERSION}-fpm || systemctl restart php-fpm

echo "Step 9: Restart Nginx"
systemctl restart nginx

echo "Step 10: Setup WebSocket server (Node.js)"
if [ -d "$WEBSOCKET_PATH" ]; then
    cd "$WEBSOCKET_PATH"
    
    echo "Installing Node.js dependencies..."
    npm install --production
    
    echo "Creating PM2 ecosystem file..."
    cat > ecosystem.config.js << 'EOF'
module.exports = {
  apps: [{
    name: 'jitsi-websocket',
    script: './server.js',
    instances: 1,
    exec_mode: 'fork',
    env: {
      NODE_ENV: 'production',
      PORT: 3001,
      SOCKET_PORT: 3001,
      LARAVEL_API_URL: 'https://api.codagenz.com/api/v1',
      REDIS_HOST: '127.0.0.1',
      REDIS_PORT: 6379,
      REDIS_PASSWORD: process.env.REDIS_PASSWORD,
      FRONTEND_URLS: 'https://apesguide.codagenz.com,https://apesudom.codagenz.com'
    },
    log_file: '/var/log/jitsi-websocket/combined.log',
    out_file: '/var/log/jitsi-websocket/out.log',
    error_file: '/var/log/jitsi-websocket/error.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    merge_logs: true,
    max_memory_restart: '500M',
    restart_delay: 3000,
    max_restarts: 10,
    min_uptime: '10s',
    watch: false,
    autorestart: true
  }]
};
EOF

    # Create log directory
    mkdir -p /var/log/jitsi-websocket
    chown -R www-data:www-data /var/log/jitsi-websocket
    
    echo "Checking if PM2 is installed..."
    if ! command -v pm2 &> /dev/null; then
        echo "Installing PM2 globally..."
        npm install -g pm2
    fi
    
    echo "Starting/Restarting WebSocket server with PM2..."
    pm2 delete jitsi-websocket 2>/dev/null || true
    pm2 start ecosystem.config.js
    pm2 save
    
    echo "Setting up PM2 startup script..."
    pm2 startup systemd -u www-data --hp /var/www
fi

echo ""
echo "========================================"
echo "DEPLOYMENT COMPLETE!"
echo "========================================"
echo ""
echo "Backend deployed to: https://api.codagenz.com"
echo "WebSocket server running on port 3001"
echo ""
echo "Check WebSocket status:"
echo "  pm2 status"
echo "  pm2 logs jitsi-websocket"
echo ""
echo "Check backend health:"
echo "  curl https://api.codagenz.com/api/v1/health"
echo ""
echo "Next Steps:"
echo "1. Ensure .env has all JITSI_* variables set"
echo "2. Test JWT generation: php scripts/test-jwt-token.php"
echo "3. Deploy frontends: apesguide.codagenz.com & apesudom.codagenz.com"
echo "========================================"
