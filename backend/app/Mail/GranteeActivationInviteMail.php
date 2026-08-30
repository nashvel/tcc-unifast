<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GranteeActivationInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * No temporary password: the activation link is the sole proof of invitation,
     * and the password is only created after identity verification.
     */
    public function __construct(
        public User $user,
        public string $activationUrl,
        public ?string $intro = null,
        public ?string $customSubject = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customSubject ?: 'Activate your TCC UniFAST TES student portal account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.grantee-activation-invite-html',
            text: 'mail.grantee-activation-invite',
        );
    }
}
