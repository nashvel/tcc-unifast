<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\BatchNotification;
use App\Models\Grantee;
use App\Models\User;

class StaffSubmissionNotifier
{
    /**
     * In-app notify all head + staff users when a requirement pipeline completes.
     *
     * @param  array<string, mixed>  $eligibility
     */
    public function notifyPipelineComplete(Grantee $grantee, int $batchId, array $eligibility, string $riskBadge): int
    {
        $status = (string) ($eligibility['status'] ?? 'pending');
        $failed = (string) ($eligibility['failed_subjects'] ?? 'n/a');
        $program = (string) ($eligibility['program_code'] ?? $grantee->program ?? '—');
        $name = (string) ($grantee->full_name ?: $grantee->student_id);

        $title = 'New submission ready for review';
        $body = sprintf(
            '%s (%s) · %s · eligibility: %s · failed subjects: %s · risk: %s',
            $name,
            $grantee->student_id,
            $program,
            $status,
            $failed,
            $riskBadge,
        );

        $count = 0;
        $recipients = User::query()
            ->whereIn('role', ['head', 'staff'])
            ->get(['id']);

        foreach ($recipients as $user) {
            $notification = BatchNotification::create([
                'batch_id' => $batchId,
                'user_id' => $user->id,
                'type' => 'submission_pipeline_complete',
                'title' => $title,
                'body' => $body,
            ]);
            event(new NotificationCreated($notification));
            $count++;
        }

        return $count;
    }
}
