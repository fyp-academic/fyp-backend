<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service to render email templates from the React frontend.
 *
 * This service calls the frontend's email rendering endpoint to convert
 * React email components into HTML strings that can be sent via Laravel Mail.
 */
class EmailRenderingService
{
    /**
     * The base URL of the frontend dev server.
     * Configured via EMAIL_RENDERER_URL in .env
     */
    protected string $rendererUrl;

    public function __construct()
    {
        $this->rendererUrl = config('services.email_renderer.url', 'http://localhost:5173');
    }

    /**
     * Render an email template to HTML.
     *
     * @param string $type The email template type (email-verification, course-update, ai-recommendation)
     * @param array $data The data to pass to the template
     * @return string The rendered HTML
     * @throws \RuntimeException If rendering fails
     */
    public function render(string $type, array $data): string
    {
        $endpoint = rtrim($this->rendererUrl, '/') . '/__email/render';

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout(10)
                ->post($endpoint, [
                    'type' => $type,
                    'data' => $data,
                ]);

            if (!$response->successful()) {
                Log::error('Email rendering failed', [
                    'type' => $type,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException("Email rendering failed: HTTP {$response->status()}");
            }

            $result = $response->json();

            if (!isset($result['html'])) {
                Log::error('Email rendering returned no HTML', [
                    'type' => $type,
                    'response' => $result,
                ]);
                throw new \RuntimeException('Email rendering returned no HTML');
            }

            return $result['html'];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Email renderer connection failed', [
                'type' => $type,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                "Cannot connect to email renderer at {$endpoint}. " .
                "Ensure the frontend dev server is running.",
                0,
                $e
            );
        } catch (\Exception $e) {
            Log::error('Email rendering error', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if the email renderer is available.
     */
    public function isAvailable(): bool
    {
        try {
            $endpoint = rtrim($this->rendererUrl, '/') . '/__email/render';
            $response = Http::timeout(2)->options($endpoint);
            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }
}
