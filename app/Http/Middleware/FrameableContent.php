<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows the H5P/SCORM player + editor wrapper pages to be embedded in an
 * <iframe> from the student/instructor SPAs (different sub-origins).
 *
 * X-Frame-Options can only say DENY/SAMEORIGIN, so it is removed and replaced
 * with CSP `frame-ancestors`, which allow-lists the app origins. The web server
 * must also be configured not to re-add X-Frame-Options on these routes.
 */
class FrameableContent
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Build the allow-list from the configured app origins (http for local dev,
        // https for production). Both are valid CSP frame-ancestors host-sources.
        $origins = collect(config('cors.allowed_origins', []))
            ->filter(fn ($o) => is_string($o) && (str_starts_with($o, 'http://') || str_starts_with($o, 'https://')))
            ->values()
            ->all();

        $ancestors = trim("'self' " . implode(' ', $origins));

        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Content-Security-Policy', "frame-ancestors {$ancestors}");

        return $response;
    }
}
