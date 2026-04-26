<?php

namespace App\Mail;

use App\Services\EmailRenderingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $code,
        public readonly int $expiresInMinutes = 10,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Password Reset Code - APES UDOM',
        );
    }

    public function content(): Content
    {
        $renderer = app(EmailRenderingService::class);

        $html = $renderer->render('password-reset-otp', [
            'userName'         => $this->userName,
            'code'             => $this->code,
            'expiresInMinutes' => $this->expiresInMinutes,
        ]);

        return new Content(
            html: $html,
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
