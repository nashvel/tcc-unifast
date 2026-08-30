<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation for staff / admin / developer collaborators.
 *
 * Separate from GranteeActivationInviteMail because these accounts do not pass
 * through the biometric funnel: their ownership is established by an admin
 * authorising the invite, so they set a password directly from the link.
 * See §2.4 of docs/identity-first-activation-implementation-plan.md.
 */
class StaffInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $activationUrl,
        public ?string $invitedBy = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to the TCC UniFAST TES admin portal',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.staff-invite',
        );
    }
}
