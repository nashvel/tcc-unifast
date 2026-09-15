<?php

namespace App\Mail;

use App\Models\Batch;
use App\Models\Grantee;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GranteeActivationDeadlineReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $deadlineFormatted;

    public string $daysRemaining;

    public function __construct(
        public User $user,
        public Grantee $grantee,
        public Batch $batch,
        public string $activationUrl,
        ?string $deadlineFormatted = null,
        ?string $daysRemaining = null,
    ) {
        $deadline = $batch->submission_deadline;
        $this->deadlineFormatted = $deadlineFormatted
            ?? ($deadline ? $deadline->timezone('Asia/Manila')->format('F j, Y \a\t g:i A (T)') : 'the announced deadline');

        if ($daysRemaining !== null) {
            $this->daysRemaining = $daysRemaining;
        } elseif ($deadline) {
            $diffHours = (int) now()->diffInHours($deadline, false);
            if ($diffHours <= 0) {
                $this->daysRemaining = 'Deadline is today!';
            } elseif ($diffHours < 24) {
                $this->daysRemaining = "Only {$diffHours} hours remaining";
            } else {
                $diffDays = (int) ceil($diffHours / 24);
                $this->daysRemaining = "Only {$diffDays} day".($diffDays > 1 ? 's' : '').' remaining';
            }
        } else {
            $this->daysRemaining = 'Deadline approaching';
        }
    }

    public function envelope(): Envelope
    {
        $batchName = $this->batch->name ?: 'UniFAST TES';

        return new Envelope(
            subject: "URGENT: Activation & Submission Deadline Approaching for {$batchName} ({$this->daysRemaining})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.grantee-activation-deadline-reminder-html',
            text: 'mail.grantee-activation-deadline-reminder',
        );
    }
}
