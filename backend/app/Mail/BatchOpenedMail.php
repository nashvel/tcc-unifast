<?php

namespace App\Mail;

use App\Models\Batch;
use App\Models\Grantee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BatchOpenedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Batch $batch,
        public Grantee $grantee,
        public string $title,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.batch-opened',
        );
    }
}
