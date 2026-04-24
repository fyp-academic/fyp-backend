<?php

namespace App\Mail;

use App\Services\EmailRenderingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $resetUrl,
        public readonly string $expiresIn = '60 minutes',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset your APES UDOM password',
        );
    }

    public function content(): Content
    {
        $renderer = app(EmailRenderingService::class);

        $html = $renderer->render('password-reset', [
            'userName'  => $this->userName,
            'resetUrl'  => $this->resetUrl,
            'expiresIn' => $this->expiresIn,
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
