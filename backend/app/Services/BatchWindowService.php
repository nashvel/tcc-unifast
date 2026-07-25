<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Batch;
use App\Models\BatchNotification;
use App\Models\Grantee;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class BatchWindowService
{
    public function windowForStudent(User $user): array
    {
        $grantee = Grantee::query()->with('batch')->where('user_id', $user->id)
            ->orWhere('student_id', $user->student_id)
            ->first();

        if (! $grantee || ! $grantee->batch) {
            return [
                'open' => false,
                'status' => 'unassigned',
                'message' => 'Your batch is not assigned to an active submission window.',
                'batch' => null,
            ];
        }

        $batch = $grantee->batch;
        $status = $batch->computedWindowStatus();
        $open = $status === 'active';

        return [
            'open' => $open,
            'status' => $status,
            'message' => match ($status) {
                'active' => 'Your submission window is open.',
                'expired' => 'Your submission window closed on '.$batch->submission_deadline?->toDayDateTimeString().'.',
                'closed' => 'Your submission window is closed.',
                default => 'Your submission window is not yet open.',
            },
            'batch' => [
                'id' => $batch->id,
                'name' => $batch->name,
                'academic_year' => $batch->academic_year,
                'semester' => $batch->semester,
                'submission_deadline' => $batch->submission_deadline,
                'window_status' => $status,
            ],
        ];
    }

    public function notifyBatch(Batch $batch, string $type, string $title, string $body): array
    {
        $sent = 0;
        $failed = [];

        foreach ($batch->grantees()->with('user')->get() as $grantee) {
            if (! $grantee->user) {
                continue;
            }

            $notification = BatchNotification::create([
                'batch_id' => $batch->id,
                'user_id' => $grantee->user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
            ]);

            event(new NotificationCreated($notification));

            try {
                Mail::raw($body, fn ($message) => $message
                    ->to($grantee->email, $grantee->full_name)
                    ->subject($title));
                $sent++;
            } catch (\Throwable $exception) {
                report($exception);
                $failed[] = ['email' => $grantee->email, 'message' => $exception->getMessage()];
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
