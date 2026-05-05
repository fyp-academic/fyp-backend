<?php

namespace App\Services\Jitsi;

use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JitsiTokenService
{
    private string $appId;
    private string $appSecret;
    private string $domain;

    public function __construct()
    {
        $this->appId = config('services.jitsi.app_id', env('JITSI_APP_ID', 'lms-production'));
        $this->appSecret = config('services.jitsi.app_secret', env('JITSI_APP_SECRET'));
        $this->domain = config('services.jitsi.domain', env('JITSI_DOMAIN', 'meet.codagenz.com'));
    }

    /**
     * Generate a JWT token for Jitsi authentication
     *
     * @param array $user User data with id, name, email, avatar
     * @param string $roomName The Jitsi room name
     * @param bool $isModerator Whether user has moderator privileges
     * @return string The JWT token
     */
    public function generateToken(array $user, string $roomName, bool $isModerator = false): string
    {
        $now = Carbon::now();

        $context = [
            'user' => [
                'id' => (string) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'] ?? '',
                'avatar' => $user['avatar'] ?? '',
                'moderator' => $isModerator,
            ],
            'room' => [
                'regex' => false,
            ],
        ];

        if ($isModerator) {
            $context['features'] = [
                'recording' => true,
                'livestreaming' => true,
            ];
        }

        $payload = [
            'context' => $context,
            'aud' => $this->appId,
            'iss' => $this->appId,
            'sub' => $this->domain,
            'room' => $roomName,
            'exp' => $now->copy()->addHours(2)->timestamp,
            'nbf' => $now->timestamp - 10, // allow 10s clock skew
        ];

        return JWT::encode($payload, $this->appSecret, 'HS256');
    }

    /**
     * Generate a token specifically for admin/observer access
     */
    public function generateAdminToken(string $roomName): string
    {
        $now = Carbon::now();

        $payload = [
            'context' => [
                'user' => [
                    'id' => 'admin',
                    'name' => 'System Admin',
                    'email' => 'admin@system.local',
                    'avatar' => '',
                    'moderator' => true,
                    'observer' => true,
                ],
                'room' => [
                    'regex' => false,
                ],
                'features' => [
                    'recording' => true,
                    'livestreaming' => true,
                ],
            ],
            'aud' => $this->appId,
            'iss' => $this->appId,
            'sub' => $this->domain,
            'room' => $roomName,
            'exp' => $now->copy()->addHour()->timestamp,
            'nbf' => $now->timestamp - 10,
        ];

        return JWT::encode($payload, $this->appSecret, 'HS256');
    }

    /**
     * Generate Jitsi meeting URL with token
     */
    public function generateMeetingUrl(string $roomName, string $token): string
    {
        return "https://{$this->domain}/{$roomName}?jwt={$token}";
    }

    /**
     * Decode and verify a token
     */
    public function decodeToken(string $token): array
    {
        return (array) JWT::decode($token, new Key($this->appSecret, 'HS256'));
    }

    /**
     * Get the Jitsi domain
     */
    public function getDomain(): string
    {
        return $this->domain;
    }
}
