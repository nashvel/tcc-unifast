<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when staff reject a face match.
 *
 * Goes to the address of record — which is the recovery path if the rejected
 * attempt came from someone other than the real grantee.
 */
class IdentityRejectedRetryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $activationUrl,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action needed: identity verification could not be completed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.identity-rejected-retry',
        );
    }
}
