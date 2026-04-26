<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Policies\RolePolicy;

class AdminOrInstructor
{
    /**
     * Handle an incoming request.
     * Allows admin (full access) or instructor (only their assigned programmes).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !RolePolicy::isAdminOrInstructor($user)) {
            return response()->json(['message' => 'Forbidden. Admin or Instructor access required.'], 403);
        }

        // For instructors, attach their assigned programme IDs to the request
        if (RolePolicy::isInstructor($user)) {
            $assignedProgrammes = RolePolicy::getAssignedProgrammeIds($user);
            $request->attributes->set('instructor_programme_ids', $assignedProgrammes);
        }

        return $next($request);
    }
}
