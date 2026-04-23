<?php

namespace App\Models;

use App\Mail\EmailVerificationMail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $verificationUrl = $this->generateVerificationUrl();

        Mail::to($this->email)->send(new EmailVerificationMail(
            userName: $this->name,
            verificationUrl: $verificationUrl,
            expiresIn: '60 minutes'
        ));
    }

    /**
     * Generate the verification URL.
     */
    private function generateVerificationUrl(): string
    {
        return route('verification.verify', [
            'id' => $this->getKey(),
            'hash' => sha1($this->getEmailForVerification()),
        ], false);
    }

    public $incrementing = false;
    protected $keyType   = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
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
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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
