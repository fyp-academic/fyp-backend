<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Events\PasswordReset;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/register
     * Create a new platform account and trigger email verification.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'password'   => 'required|string|min:8|confirmed',
            'role'       => 'sometimes|string|in:student,instructor,admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->input('role', 'student'),
        ]);

        event(new Registered($user));

        return response()->json([
            'message' => 'Account created successfully. Please check your email to verify your account before logging in.',
            'user'    => $user,
            'requires_verification' => true,
        ], 201);
    }

    /**
     * POST /api/v1/auth/login
     * Issue a Sanctum token for authenticated API access.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = Auth::user();

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email address before logging in.',
                'requires_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    /**
     * POST /api/v1/auth/forgot-password
     * Send a password-reset link to the given email address.
     * Uses custom notification for frontend URL generation.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Custom password reset using the User model's sendPasswordResetNotification
        $user = User::where('email', $request->email)->first();

        if ($user) {
            $token = Password::createToken($user);
            $user->sendPasswordResetNotification($token);

            Log::info('Password reset link sent to: ' . $request->email);
        }

        // Always return success to prevent email enumeration
        return response()->json([
            'message' => 'If this email exists in our system, a password reset link has been sent.'
        ]);
    }

    /**
     * POST /api/v1/auth/reset-password
     * Reset the user's password using the signed token from email.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token'                 => 'required|string',
            'email'                 => 'required|email',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 400);
    }

    /**
     * GET /api/v1/auth/verify-email/{id}/{hash}
     * Validate the signed URL and redirect to frontend with token.
     * This endpoint is called when user clicks the email link.
     */
    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse|JsonResponse
    {
        $user = User::findOrFail($id);

        // Validate the hash matches the user's email
        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return $this->redirectToFrontend('error', 'Invalid verification link.');
        }

        // Check if already verified
        if ($user->hasVerifiedEmail()) {
            return $this->redirectToFrontend('info', 'Email already verified.');
        }

        // Check if the URL signature is valid (Laravel's signed URL validation)
        if (!$request->hasValidSignature()) {
            return $this->redirectToFrontend('error', 'Verification link has expired or is invalid.');
        }

        // Redirect to frontend with verification parameters
        // Frontend will then POST to /api/v1/auth/verify-email/confirm
        $frontendUrl = $this->getFrontendUrl($user) . '/verify-email';

        return redirect($frontendUrl . '?' . http_build_query([
            'id' => $id,
            'hash' => $hash,
            'signature' => $request->query('signature'),
            'expires' => $request->query('expires'),
            'status' => 'pending',
        ]));
    }

    /**
     * POST /api/v1/auth/verify-email/confirm
     * Confirm email verification (called by frontend after redirect).
     * Secure: requires POST, CSRF protection via signature validation.
     */
    public function confirmVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string',
            'hash' => 'required|string',
            'signature' => 'required|string',
            'expires' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid verification data.'], 422);
        }

        $user = User::findOrFail($request->id);

        // Validate the hash
        if (!hash_equals((string) $request->hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link.'], 403);
        }

        // Check if already verified
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
                'verified' => true,
            ]);
        }

        // Validate the signed URL by reconstructing it
        $temporarySignedUrl = URL::temporarySignedRoute(
            'verification.verify',
            $request->expires,
            [
                'id' => $request->id,
                'hash' => $request->hash,
            ]
        );

        // Verify the signature matches
        $parsedUrl = parse_url($temporarySignedUrl);
        parse_str($parsedUrl['query'] ?? '', $queryParams);

        if (($queryParams['signature'] ?? '') !== $request->signature) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 403);
        }

        // Check if expired
        if (now()->timestamp > (int) $request->expires) {
            return response()->json(['message' => 'Verification link has expired.'], 403);
        }

        // Mark as verified
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        Log::info('Email verified for user: ' . $user->email);

        return response()->json([
            'message' => 'Email verified successfully.',
            'verified' => true,
        ]);
    }

    /**
     * POST /api/v1/auth/verify-email/resend
     * Resend verification email.
     * Supports both authenticated and unauthenticated requests.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        // If authenticated, use current user
        if ($request->user()) {
            $user = $request->user();
        } else {
            // If not authenticated, require email parameter
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                // Don't reveal if email exists
                return response()->json([
                    'message' => 'If this email exists and needs verification, a new link has been sent.'
                ]);
            }
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
                'verified' => true,
            ], 400);
        }

        // Throttle resends to prevent abuse (1 per minute)
        $lastSend = cache()->get('verification_email_' . $user->id);
        if ($lastSend && now()->diffInSeconds($lastSend) < 60) {
            return response()->json([
                'message' => 'Please wait before requesting another email.',
                'retry_after' => 60 - now()->diffInSeconds($lastSend),
            ], 429);
        }

        cache()->put('verification_email_' . $user->id, now(), 60);

        $user->sendEmailVerificationNotification();

        Log::info('Verification email resent to: ' . $user->email);

        return response()->json([
            'message' => 'Verification email resent successfully.',
            'email' => $user->email,
        ]);
    }

    /**
     * Helper: Get the appropriate frontend URL based on user role.
     */
    private function getFrontendUrl(User $user): string
    {
        return match ($user->role) {
            'instructor' => Config::get('app.frontend_instructor_url'),
            'admin' => Config::get('app.frontend_instructor_url'),
            default => Config::get('app.frontend_student_url'),
        };
    }

    /**
     * Helper: Redirect to frontend with status message.
     */
    private function redirectToFrontend(string $type, string $message): RedirectResponse
    {
        // Default to student frontend if user not found
        $frontendUrl = Config::get('app.frontend_student_url') . '/verify-email';

        return redirect($frontendUrl . '?' . http_build_query([
            'status' => $type,
            'message' => $message,
        ]));
    }

    /**
     * GET /api/v1/auth/me
     * Return the full profile for the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * POST /api/v1/auth/logout
     * Revoke the current Sanctum API token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
