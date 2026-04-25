<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class OtpVerificationMail extends Mailable implements ShouldQueue
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
            subject: 'Your APES UDOM Verification Code',
            from: new Address(
                config('mail.from.address', 'noreply@codagenz.com'),
                config('mail.from.name', 'APES UDOM')
            ),
            replyTo: [
                new Address(
                    config('mail.reply_to.address', 'support@codagenz.com'),
                    config('mail.reply_to.name', 'APES UDOM Support')
                ),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.otp-verification',
            text: 'emails.otp-verification-text',
            with: [
                'userName' => $this->userName,
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
