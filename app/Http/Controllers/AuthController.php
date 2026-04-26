<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use App\Mail\PasswordResetOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Events\PasswordReset;
use App\Models\User;
use App\Models\DegreeProgramme;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Nationality mapping from registration number prefix.
     */
    private const NATIONALITY_MAP = [
        'T' => ['country' => 'Tanzania', 'flag' => '🇹🇿'],
        'K' => ['country' => 'Kenya', 'flag' => '🇰🇪'],
        'B' => ['country' => 'Burundi', 'flag' => '🇧🇮'],
        'R' => ['country' => 'Rwanda', 'flag' => '🇷🇼'],
        'U' => ['country' => 'Uganda', 'flag' => '🇺🇬'],
    ];

    /**
     * Education level mapping.
     */
    private const EDUCATION_LEVEL_MAP = [
        '03' => "Bachelor's Degree",
        '02' => 'Diploma',
    ];

    /**
     * Parse registration number and extract structured data.
     * Format: XYY-LL-NNNNN (e.g., T23-03-09759)
     */
    private function parseRegistrationNumber(string $regNo): array
    {
        $pattern = '/^([TKBRU])(\d{2})-(\d{2})-(\d{5})$/';
        if (!preg_match($pattern, $regNo, $matches)) {
            return ['valid' => false];
        }

        $nationalityCode = $matches[1];
        $yearDigits = $matches[2];
        $levelCode = $matches[3];
        $uniqueId = $matches[4];

        $nationality = self::NATIONALITY_MAP[$nationalityCode] ?? null;
        $educationLevel = self::EDUCATION_LEVEL_MAP[$levelCode] ?? null;

        if (!$nationality || !$educationLevel) {
            return ['valid' => false];
        }

        $registrationYear = 2000 + (int) $yearDigits;
        $currentYear = (int) now()->format('Y');
        $yearOfStudy = max(1, min($currentYear - $registrationYear + 1, 5));

        return [
            'valid' => true,
            'nationality_code' => $nationalityCode,
            'nationality' => $nationality['country'],
            'flag' => $nationality['flag'],
            'registration_year' => $registrationYear,
            'education_level_code' => $levelCode,
            'education_level' => $educationLevel,
            'unique_id' => $uniqueId,
            'year_of_study' => $yearOfStudy,
        ];
    }

    /**
     * Generate a secure 6-digit verification code, store it hashed, and send it.
     */
    private function generateAndSendVerificationCode(User $user): string
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'verification_code' => Hash::make($code),
            'verification_code_expires_at' => now()->addMinutes(10),
        ])->save();

        $user->sendEmailVerificationNotification($code);

        Log::info('Verification code generated for user: ' . $user->email);

        return $code;
    }

    /**
     * POST /api/v1/auth/register
     * Create a new platform account and trigger email verification.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'                   => 'required|string|max:255',
            'email'                  => 'required|string|email|max:255|unique:users',
            'password'               => 'required|string|min:8|confirmed',
            'role'                   => 'sometimes|string|in:student,instructor,admin',
            'registration_number'    => 'sometimes|nullable|string|max:30|unique:users',
            'degree_programme_id'  => 'sometimes|nullable|string|exists:degree_programmes,id',
            'college_id'             => 'sometimes|nullable|string|exists:colleges,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = $request->input('role', 'student');
        $regNo = $request->input('registration_number');
        $parsed = null;

        // Parse registration number if provided (especially for students)
        if ($regNo) {
            $parsed = $this->parseRegistrationNumber($regNo);
            if (!$parsed['valid']) {
                return response()->json([
                    'errors' => [
                        'registration_number' => ['Invalid registration number format. Expected format: XYY-LL-NNNNN (e.g., T23-03-09759)'],
                    ],
                ], 422);
            }
        }

        $userData = [
            'id'                    => Str::uuid()->toString(),
            'name'                  => $request->name,
            'email'                 => $request->email,
            'password'              => Hash::make($request->password),
            'role'                  => $role,
            'registration_number'   => $regNo,
            'degree_programme_id'   => $request->input('degree_programme_id'),
            'year_of_study'         => $parsed['year_of_study'] ?? null,
            'education_level'       => $parsed['education_level'] ?? $request->input('education_level'),
            'nationality'           => $parsed['nationality'] ?? $request->input('nationality'),
            'country'               => $parsed['nationality'] ?? $request->input('country'),
        ];

        $user = User::create($userData);

        $this->generateAndSendVerificationCode($user);

        return response()->json([
            'message' => 'Account created successfully. Please check your email for the verification code.',
            'user'    => $user->makeHidden(['verification_code', 'verification_code_expires_at']),
            'parsed_registration' => $parsed,
            'requires_verification' => true,
        ], 201);
    }

    /**
     * POST /api/v1/auth/parse-registration
     * Public endpoint to parse a registration number and return extracted data.
     */
    public function parseRegistration(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'registration_number' => 'required|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $parsed = $this->parseRegistrationNumber($request->registration_number);

        if (!$parsed['valid']) {
            return response()->json([
                'errors' => [
                    'registration_number' => ['Invalid format. Expected: XYY-LL-NNNNN (e.g., T23-03-09759)'],
                ],
            ], 422);
        }

        return response()->json(['data' => $parsed]);
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

        /** @var \App\Models\User $user */
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
     * Generate a secure 6-digit password reset code, store it hashed on user.
     */
    private function generatePasswordResetCode(User $user): string
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'password_reset_code' => Hash::make($code),
            'password_reset_expires_at' => now()->addMinutes(10),
        ])->save();

        Log::info('Password reset code generated for user: ' . $user->email);

        return $code;
    }

    /**
     * POST /api/v1/auth/forgot-password
     * Send a password-reset code to the given email address.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $code = $this->generatePasswordResetCode($user);

            Mail::to($user->email)->send(new PasswordResetOtpMail(
                userName: $user->name,
                code: $code,
                expiresInMinutes: 10,
            ));

            Log::info('Password reset code sent to: ' . $request->email);
        }

        // Always return success to prevent email enumeration
        return response()->json([
            'message' => 'If this email exists in our system, a password reset code has been sent.'
        ]);
    }

    /**
     * POST /api/v1/auth/reset-password
     * Reset the user's password using the code from email.
     * Invalidates code immediately after use or on failure.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'                 => 'required|email',
            'otp'                   => 'required|string|size:6',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid email or code.'], 403);
        }

        // Check if code exists
        if (!$user->password_reset_code) {
            return response()->json(['message' => 'Invalid or expired code.'], 403);
        }

        // Check expiry
        if ($user->password_reset_expires_at && now()->isAfter($user->password_reset_expires_at)) {
            // Clear expired code
            $user->forceFill([
                'password_reset_code' => null,
                'password_reset_expires_at' => null,
            ])->save();
            return response()->json(['message' => 'Code has expired. Please request a new one.'], 403);
        }

        // Verify code
        if (!Hash::check($request->otp, $user->password_reset_code)) {
            // Invalid code - clear it immediately for security
            $user->forceFill([
                'password_reset_code' => null,
                'password_reset_expires_at' => null,
            ])->save();
            return response()->json(['message' => 'Invalid code. Please request a new code.'], 403);
        }

        // Valid code - clear it and reset password
        $user->forceFill([
            'password' => Hash::make($request->password),
            'password_reset_code' => null,
            'password_reset_expires_at' => null,
        ])->save();

        $user->tokens()->delete();

        event(new PasswordReset($user));

        Log::info('Password reset successful for: ' . $user->email);

        return response()->json(['message' => 'Password reset successfully.']);
    }

    /**
     * POST /api/v1/auth/verify-email-code
     * Verify email using a one-time code sent to the user's email.
     */
    public function verifyEmailCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid email or verification code.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
                'verified' => true,
            ]);
        }

        // Check if code exists
        if (!$user->verification_code) {
            return response()->json([
                'message' => 'No verification code found. Please request a new one.',
            ], 403);
        }

        // Check code expiry
        if (!$user->verification_code_expires_at || now()->isAfter($user->verification_code_expires_at)) {
            // Clear expired code
            $user->forceFill([
                'verification_code' => null,
                'verification_code_expires_at' => null,
            ])->save();
            return response()->json([
                'message' => 'Verification code has expired. Please request a new one.',
                'expired' => true,
            ], 403);
        }

        // Check code validity (hashed)
        if (!Hash::check($request->code, $user->verification_code)) {
            // Invalid code - clear it immediately for security
            $user->forceFill([
                'verification_code' => null,
                'verification_code_expires_at' => null,
            ])->save();
            return response()->json([
                'message' => 'Invalid verification code. Please request a new code.',
            ], 403);
        }

        // Mark as verified and invalidate the code
        $user->forceFill([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ])->save();

        event(new Verified($user));

        Log::info('Email verified via OTP for user: ' . $user->email);

        return response()->json([
            'message' => 'Email verified successfully.',
            'verified' => true,
        ]);
    }

    /**
     * POST /api/v1/auth/verify-email/resend
     * Resend verification code.
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
                    'message' => 'If this email exists and needs verification, a new code has been sent.'
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
        $lastSend = Cache::get('verification_email_' . $user->id);
        if ($lastSend && now()->diffInSeconds($lastSend) < 60) {
            return response()->json([
                'message' => 'Please wait before requesting another code.',
                'retry_after' => 60 - now()->diffInSeconds($lastSend),
            ], 429);
        }

        Cache::put('verification_email_' . $user->id, now(), 60);

        $this->generateAndSendVerificationCode($user);

        Log::info('Verification code resent to: ' . $user->email);

        return response()->json([
            'message' => 'Verification code resent successfully.',
            'email' => $user->email,
        ]);
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
