<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Policies\RolePolicy;

class InstructorAccess
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

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Admin has full access
        if (RolePolicy::isAdmin($user)) {
            return $next($request);
        }

        // Instructor must have at least one assigned programme
        if (RolePolicy::isInstructor($user)) {
            $assignedProgrammes = RolePolicy::getAssignedProgrammeIds($user);

            if (empty($assignedProgrammes)) {
                return response()->json([
                    'message' => 'Forbidden. You must be assigned to at least one degree programme to access this resource.',
                ], 403);
            }

            // Store assigned programme IDs in request for downstream use
            $request->attributes->set('instructor_programme_ids', $assignedProgrammes);

            return $next($request);
        }

        return response()->json([
            'message' => 'Forbidden. Admin or Instructor access required.',
        ], 403);
    }
}
