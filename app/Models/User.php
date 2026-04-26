<?php

namespace App\Models;

use App\Mail\OtpVerificationMail;
use App\Notifications\CustomResetPassword;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * IMPORTANT: UUID setup
     */
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'registration_number',
        'degree_programme_id',
        'year_of_study',
        'education_level',
        'nationality',
        'bio',
        'department',
        'institution',
        'country',
        'timezone',
        'language',
        'verification_code',
        'verification_code_expires_at',
        'password_reset_code',
        'password_reset_expires_at',
    ];

    /**
     * Hidden attributes
     */
    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
        'password_reset_code',
    ];

    /**
     * Attribute casting
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_code_expires_at' => 'datetime',
            'password_reset_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determine if the user has verified their email address.
     */
    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    /**
     * Send OTP email verification via branded Mailable.
     */
    public function sendEmailVerificationNotification(?string $code = null): void
    {
        if (empty($code)) {
            return;
        }

        Log::info('Sending OTP verification email to: ' . $this->email);

        Mail::to($this->email)->send(new OtpVerificationMail(
            userName: $this->name,
            code: $code,
            expiresInMinutes: 10,
        ));

        Log::info('OTP verification email dispatched');
    }

    /**
     * FIXED: Password reset notification
     * DO NOT type-hint or add return type
     */
    public function sendPasswordResetNotification($token)
    {
        Log::info('Sending password reset email to: ' . $this->email);

        $this->notify(new CustomResetPassword($token));

        Log::info('Password reset email dispatched');
    }

    /**
     * Relationships
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'owner_user_id');
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(UserPreference::class);
    }

    public function degreeProgramme(): BelongsTo
    {
        return $this->belongsTo(DegreeProgramme::class);
    }

    public function assignedDegreeProgrammes(): BelongsToMany
    {
        return $this->belongsToMany(DegreeProgramme::class, 'degree_programme_instructor', 'instructor_id', 'degree_programme_id');
    }
}