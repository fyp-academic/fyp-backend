<?php

namespace App\Models;

use App\Notifications\CustomVerifyEmail;
use App\Notifications\CustomResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
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
        'bio',
        'department',
        'institution',
        'country',
        'timezone',
        'language',
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
            'password' => 'hashed',
        ];
    }

    /**
     * Send email verification
     */
    public function sendEmailVerificationNotification()
    {
        Log::info('Sending verification email to: ' . $this->email);

        $this->notify(new CustomVerifyEmail());

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
}