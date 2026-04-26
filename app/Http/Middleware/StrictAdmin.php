<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Policies\RolePolicy;

class StrictAdmin
{
    /**
     * Only allow admin role - for sensitive operations (colleges, degree programmes, categories, instructors).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !RolePolicy::isAdmin($user)) {
            return response()->json(['message' => 'Forbidden. Admin access required.'], 403);
        }

        return $next($request);
    }
}
