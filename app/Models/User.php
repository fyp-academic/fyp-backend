<?php

namespace App\Models;

use App\Notifications\CustomVerifyEmail;
use App\Notifications\CustomResetPassword;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, HasUuids, MustVerifyEmail, Notifiable;

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
    ];

    /**
     * Hidden attributes
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_code_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Send email verification
     */
    public function sendEmailVerificationNotification(?string $code = null)
    {
        Log::info('Sending verification email to: ' . $this->email);

        $this->notify(new CustomVerifyEmail($code));

        Log::info('Verification email dispatched');
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