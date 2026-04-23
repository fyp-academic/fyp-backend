<?php

namespace App\Mail;

use App\Services\EmailRenderingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $verificationUrl,
        public readonly string $expiresIn = '60 minutes',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your APES UDOM email address',
        );
    }

    public function content(): Content
    {
        $renderer = app(EmailRenderingService::class);

        $html = $renderer->render('email-verification', [
            'userName'        => $this->userName,
            'verificationUrl' => $this->verificationUrl,
            'expiresIn'       => $this->expiresIn,
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
