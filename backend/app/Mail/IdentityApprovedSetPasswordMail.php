<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when staff approve a borderline face match.
 *
 * Carries a fresh activation link because the student has no password yet and
 * their original link has very likely expired while waiting for review.
 */
class IdentityApprovedSetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $activationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your identity was approved — set your password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.identity-approved-set-password',
        );
    }
}
