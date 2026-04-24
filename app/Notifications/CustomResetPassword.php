<?php

namespace App\Notifications;

use App\Mail\PasswordResetMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;

class CustomResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The password reset token.
     */
    public string $token;

    /**
     * The callback that should be used to create the reset password URL.
     */
    public static ?\Closure $createUrlCallback = null;

    /**
     * The callback that should be used to build the mail message.
     */
    public static ?\Closure $toMailCallback = null;

    /**
     * Create a notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     * Uses React email template via PasswordResetMail.
     */
    public function toMail(object $notifiable): MailMessage|PasswordResetMail
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        $resetUrl = $this->resetUrl($notifiable);
        $expiresIn = Config::get('auth.passwords.' . Config::get('auth.defaults.passwords') . '.expire', 60) . ' minutes';

        // Use React email template via Mailable
        return new PasswordResetMail(
            userName: $notifiable->name,
            resetUrl: $resetUrl,
            expiresIn: $expiresIn,
        );
    }

    /**
     * Get the reset password URL for the given notifiable.
     */
    protected function resetUrl(object $notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        // Build frontend URL with token and email
        $role = $notifiable->role ?? 'student';

        $frontendUrl = match ($role) {
            'instructor' => Config::get('app.frontend_instructor_url') . '/reset-password',
            'admin' => Config::get('app.frontend_instructor_url') . '/reset-password',
            default => Config::get('app.frontend_student_url') . '/reset-password',
        };

        return $frontendUrl . '?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    /**
     * Build the mail message.
     */
    protected function buildMailMessage(object $notifiable, string $url): MailMessage
    {
        return (new MailMessage)
            ->subject(Lang::get('Reset Password Notification'))
            ->line(Lang::get('You are receiving this email because we received a password reset request for your account.'))
            ->action(Lang::get('Reset Password'), $url)
            ->line(Lang::get('This password reset link will expire in :count minutes.', ['count' => Config::get('auth.passwords.' . Config::get('auth.defaults.passwords') . '.expire')]))
            ->line(Lang::get('If you did not request a password reset, no further action is required.'));
    }

    /**
     * Set a callback that should be used when creating the reset password URL.
     */
    public static function createUrlUsing(\Closure $callback): void
    {
        static::$createUrlCallback = $callback;
    }

    /**
     * Set a callback that should be used when building the notification mail message.
     */
    public static function toMailUsing(\Closure $callback): void
    {
        static::$toMailCallback = $callback;
    }
}
