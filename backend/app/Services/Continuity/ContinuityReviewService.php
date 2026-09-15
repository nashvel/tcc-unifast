<?php

namespace App\Services\Continuity;

use App\Models\ContinuityRecordState;
use App\Models\ContinuityReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContinuityReviewService
{
    public function __construct(private ModuleRegistry $modules, private WorkspaceConnectionService $audit) {}

    public function decide(ContinuityReview $review, User $user, array $choices, string $note): ContinuityReview
    {
        return DB::transaction(function () use ($review, $user, $choices, $note) {
            $review = ContinuityReview::lockForUpdate()->findOrFail($review->id);
            abort_unless($review->status === 'pending', 409, 'This review has already been decided.');
            $state = ContinuityRecordState::findOrFail($review->record_state_id);
            $record = $this->modules->query($review->module)->lockForUpdate()->findOrFail($state->record_id);
            $current = $this->modules->snapshot($review->module, $record);
            $payload = $review->payload;
            $pending = array_keys([...$payload['conflicts'], ...$payload['approvals']]);
            abort_unless(count($choices) === count($pending) && ! array_diff($pending, array_keys($choices)), 422, 'Choose a resolution for every pending field.');
            $updates = [];
            foreach ($pending as $field) {
                abort_unless(in_array($choices[$field], ['system', 'mirror'], true), 422);
                if ($choices[$field] === 'mirror') {
                    abort_unless(in_array($field, $this->modules->editable($review->module), true), 422, 'Use the existing module workflow for identity, financial or status decisions.');
                    abort_unless($current[$field] === $payload['system'][$field], 409, 'The live record changed since this review. Refresh and review again.');
                    $updates[$field] = $payload['mirror'][$field];
                }
            }
            if ($updates) {
                validator($updates, array_fill_keys(array_keys($updates), ['nullable', 'string', 'max:2000']))->validate();
                $record->fill($updates)->save();
            }
            $review->update(['status' => $updates ? 'merged' : 'rejected', 'reviewed_by' => $user->id, 'note' => $note, 'reviewed_at' => now()]);
            $this->audit->audit($user, 'workspace.review_decided', $review->id);

            return $review;
        });
    }
}
