<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;

class CustomVerifyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The verification code to include in the email.
     */
    public ?string $code = null;

    /**
     * Create a notification instance.
     */
    public function __construct(?string $code = null)
    {
        $this->code = $code;
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
     */
    public function toMail(object $notifiable): MailMessage
    {
        return $this->buildMailMessage($notifiable);
    }

    /**
     * Build the mail message with the OTP code.
     */
    protected function buildMailMessage(object $notifiable): MailMessage
    {
        $code = $this->code ?? 'N/A';

        return (new MailMessage)
            ->subject(Lang::get('Your Email Verification Code'))
            ->line(Lang::get('Use the following code to verify your email address. This code will expire in 10 minutes.'))
            ->line(' ')
            ->line('**' . $code . '**')
            ->line(' ')
            ->line(Lang::get('If you did not create an account, no further action is required.'));
    }
}
