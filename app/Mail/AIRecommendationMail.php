<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailRenderingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AIRecommendationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  User   $user            Recipient
     * @param  string $courseName      Course context
     * @param  string $profileType     Learner archetype (H | A | T | C | mixed)
     * @param  string $riskTier        green | amber | red | critical
     * @param  array  $recommendations Array of {title, description, impact_level} objects
     * @param  string $actionUrl       Link to the AI Insights or learner profile page
     */
    public function __construct(
        public readonly User   $user,
        public readonly string $courseName,
        public readonly string $profileType,
        public readonly string $riskTier,
        public readonly array  $recommendations,
        public readonly string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your personalised AI insights for {$this->courseName}",
        );
    }

    public function content(): Content
    {
        $renderer = app(EmailRenderingService::class);

        $html = $renderer->render('ai-recommendation', [
            'userName'        => $this->user->name,
            'courseName'      => $this->courseName,
            'profileType'     => $this->profileType,
            'riskTier'        => $this->riskTier,
            'recommendations' => $this->recommendations,
            'actionUrl'       => $this->actionUrl,
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
