<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Models\User;

class UserController extends Controller
{
    /**
     * GET /api/v1/users
     * Return all users for admin management.
     */
    public function index(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'role', 'registration_number', 'degree_programme_id', 'year_of_study', 'education_level', 'nationality', 'department', 'institution', 'email_verified_at', 'created_at')
            ->with('degreeProgramme.college')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $users]);
    }
}
