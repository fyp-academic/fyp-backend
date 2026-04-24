<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The callback that should be used to create the verify email URL.
     */
    public static ?\Closure $createUrlCallback = null;

    /**
     * The callback that should be used to build the mail message.
     */
    public static ?\Closure $toMailCallback = null;

    /**
     * Get the notification's channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $verificationUrl);
        }

        return $this->buildMailMessage($notifiable, $verificationUrl);
    }

    /**
     * Get the verify email URL for the given notifiable.
     */
    protected function verificationUrl(object $notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable);
        }

        // Generate a signed URL that will be validated by the backend
        // But points to the frontend URL for user interaction
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // Extract the signature and expiry from the signed URL
        $parsedUrl = parse_url($signedUrl);
        parse_str($parsedUrl['query'] ?? '', $queryParams);

        // Build frontend URL with signature parameters
        $frontendUrl = $this->getFrontendUrl($notifiable);

        return $frontendUrl . '?' . http_build_query([
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'signature' => $queryParams['signature'] ?? '',
            'expires' => $queryParams['expires'] ?? '',
        ]);
    }

    /**
     * Get the appropriate frontend URL based on user role.
     */
    protected function getFrontendUrl(object $notifiable): string
    {
        $role = $notifiable->role ?? 'student';

        return match ($role) {
            'instructor' => Config::get('app.frontend_instructor_url') . '/verify-email',
            'admin' => Config::get('app.frontend_instructor_url') . '/verify-email',
            default => Config::get('app.frontend_student_url') . '/verify-email',
        };
    }

    /**
     * Build the mail message.
     */
    protected function buildMailMessage(object $notifiable, string $url): MailMessage
    {
        return (new MailMessage)
            ->subject(Lang::get('Verify Email Address'))
            ->line(Lang::get('Please click the button below to verify your email address.'))
            ->action(Lang::get('Verify Email Address'), $url)
            ->line(Lang::get('If you did not create an account, no further action is required.'));
    }

    /**
     * Set a callback that should be used when creating the email verification URL.
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
