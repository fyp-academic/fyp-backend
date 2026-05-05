<?php
/**
 * JWT Token Test Script for Jitsi Integration
 * 
 * Usage:
 *   php test-jwt-token.php [room-name] [user-id] [user-name]
 * 
 * Examples:
 *   php test-jwt-token.php test-room-123
 *   php test-jwt-token.php session-abc user-1 "John Doe"
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Configuration - CODAGENZ PRODUCTION
$appId = $_ENV['JITSI_APP_ID'] ?? 'apes-lms-production';
$appSecret = $_ENV['JITSI_APP_SECRET'] ?? '';
$domain = $_ENV['JITSI_DOMAIN'] ?? 'meet.codagenz.com';

// Command line arguments
$roomName = $argv[1] ?? 'test-room-' . time();
$userId = $argv[2] ?? 'test-user-' . time();
$userName = $argv[3] ?? 'Test User';
$isModerator = ($argv[4] ?? 'false') === 'true';

echo "========================================\n";
echo "Jitsi JWT Token Generator & Tester\n";
echo "========================================\n\n";

// Validate configuration
if (empty($appSecret)) {
    echo "ERROR: JITSI_APP_SECRET is not set in .env file!\n";
    echo "Please add: JITSI_APP_SECRET=your-32-char-secret\n";
    exit(1);
}

if (strlen($appSecret) < 32) {
    echo "WARNING: JITSI_APP_SECRET is less than 32 characters!\n";
    echo "Current length: " . strlen($appSecret) . " characters\n";
    echo "JWT tokens may not work properly with short secrets.\n\n";
}

echo "Configuration:\n";
echo "  Domain: $domain\n";
echo "  App ID: $appId\n";
echo "  Secret: " . (empty($appSecret) ? 'NOT SET' : substr($appSecret, 0, 8) . "..." . substr($appSecret, -8)) . "\n";
echo "  Secret Length: " . strlen($appSecret) . " characters\n";
echo "\n  CODAGENZ DOMAINS:\n";
echo "    API:    https://api.codagenz.com\n";
echo "    Admin:  https://apesguide.codagenz.com\n";
echo "    Student: https://apesudom.codagenz.com\n\n";

// Generate token
$now = time();

$payload = [
    'context' => [
        'user' => [
            'id' => (string) $userId,
            'name' => $userName,
            'email' => $userId . '@test.local',
            'avatar' => '',
            'moderator' => $isModerator,
        ],
        'room' => [
            'regex' => false,
        ],
    ],
    'aud' => 'jitsi',
    'iss' => $appId,
    'sub' => $domain,
    'room' => $roomName,
    'exp' => $now + 7200, // 2 hours
    'nbf' => $now - 10,   // allow 10s clock skew
];

echo "Token Payload:\n";
echo "  User ID: $userId\n";
echo "  User Name: $userName\n";
echo "  Is Moderator: " . ($isModerator ? 'Yes' : 'No') . "\n";
echo "  Room: $roomName\n";
echo "  Expires: " . date('Y-m-d H:i:s', $payload['exp']) . "\n\n";

try {
    $token = JWT::encode($payload, $appSecret, 'HS256');
    
    echo "========================================\n";
    echo "Generated JWT Token:\n";
    echo "========================================\n";
    echo wordwrap($token, 80, "\n", true) . "\n\n";
    
    // Decode and verify token
    $decoded = JWT::decode($token, new Key($appSecret, 'HS256'));
    
    echo "========================================\n";
    echo "Token Verification: SUCCESS\n";
    echo "========================================\n";
    echo "Decoded Payload:\n";
    print_r((array) $decoded);
    
    // Generate Jitsi Meet URL
    $jitsiUrl = "https://$domain/$roomName?jwt=$token";
    
    echo "\n========================================\n";
    echo "Jitsi Meet URL:\n";
    echo "========================================\n";
    echo wordwrap($jitsiUrl, 80, "\n", true) . "\n\n";
    
    // Test instructions
    echo "========================================\n";
    echo "Test Instructions:\n";
    echo "========================================\n";
    echo "1. Open the Jitsi Meet URL above in your browser\n";
    echo "2. You should join the room as '$userName'\n";
    echo "3. Check if you have moderator rights: " . ($isModerator ? 'Yes' : 'No') . "\n\n";
    
    echo "========================================\n";
    echo "API Test Command:\n";
    echo "========================================\n";
    echo "curl -X POST https://api.codagenz.com/api/v1/sessions/$roomName/token \\\n";
    echo "  -H 'Authorization: Bearer YOUR_LMS_TOKEN' \\\n";
    echo "  -H 'Accept: application/json'\n\n";
    
    // Save token to file for testing
    $tokenFile = __DIR__ . '/../storage/jitsi-test-token.txt';
    file_put_contents($tokenFile, $token);
    echo "Token saved to: $tokenFile\n\n";
    
} catch (Exception $e) {
    echo "ERROR: Failed to generate token\n";
    echo "Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test decoding with wrong secret (should fail)
echo "========================================\n";
echo "Security Test: Wrong Secret\n";
echo "========================================\n";
try {
    $wrongSecret = 'wrong-secret-that-should-not-work-12345';
    JWT::decode($token, new Key($wrongSecret, 'HS256'));
    echo "WARNING: Token decoded with wrong secret! Check your setup.\n";
} catch (Exception $e) {
    echo "PASS: Token correctly rejected with wrong secret\n";
    echo "Error (expected): " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "Test Complete!\n";
echo "========================================\n";
