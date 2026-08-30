<?php

namespace App\Services;

use App\Models\DocumentSubmission;
use App\Support\VaultFileStorage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DocumentSubmissionPresenter
{
    /**
     * Identity is verified once during onboarding; the vault has no school_id slot.
     *
     * @var list<string>
     */
    public const EXPECTED_SLOTS = [
        'course_history',
        'grade_slip',
        'specimen_signatures',
    ];

    /** @var array<string, string> */
    private const SLOT_TAB_LABELS = [
        'course_history' => 'Course History',
        'grade_slip' => 'Grade Slip',
        'specimen_signatures' => 'ID (Back-to-Back) & Specimen',
    ];

    public function submission(DocumentSubmission $item, ?int $viewerId = null): array
    {
        $latestCheck = $item->identityChecks()->latest('checked_at')->first();
        $data = $item->toArray();
        unset(
            $data['face_descriptor_payload'],
            $data['stored_path'],
            $data['secondary_stored_path'],
        );

        if (isset($data['metadata_payload']) && is_array($data['metadata_payload'])) {
            unset($data['metadata_payload']['frame_path']);
        }

        return array_merge($data, [
            'file_url' => VaultFileStorage::authSubmissionUrl($item, 'primary'),
            'secondary_file_url' => VaultFileStorage::authSubmissionUrl($item, 'secondary'),
            'file_preview_url' => VaultFileStorage::signedSubmissionUrl($item, 'primary', $viewerId),
            'secondary_file_preview_url' => VaultFileStorage::signedSubmissionUrl($item, 'secondary', $viewerId),
            'identity_check' => $latestCheck ? [
                'result' => $latestCheck->result,
                'distance' => $latestCheck->distance,
                'confidence_score' => $latestCheck->confidence_score,
                'manual_review_required' => $latestCheck->manual_review_required,
                'challenge_sequence' => $latestCheck->challenge_sequence,
                'checked_at' => $latestCheck->checked_at,
            ] : null,
        ]);
    }

    /**
     * @param  Collection<int, DocumentSubmission>  $documents
     * @return array<string, mixed>
     */
    public function package(Collection $documents): array
    {
        $ordered = $documents
            ->sortBy(function (DocumentSubmission $doc): int {
                $index = array_search((string) $doc->slot_key, self::EXPECTED_SLOTS, true);

                return $index === false ? 99 : $index;
            })
            ->values();

        /** @var DocumentSubmission $first */
        $first = $ordered->first();
        $statuses = $ordered->pluck('status')->filter()->values()->all();
        $riskRank = ['high' => 3, 'medium' => 2, 'low' => 1];
        $highestRisk = $ordered
            ->sortByDesc(fn (DocumentSubmission $doc) => $riskRank[$doc->risk_level] ?? 0)
            ->first();

        $submitted = $ordered->count();
        $expected = count(self::EXPECTED_SLOTS);
        $reviewed = $ordered
            ->filter(fn (DocumentSubmission $doc) => in_array($doc->status, ['approved', 'rejected', 'resubmission'], true))
            ->count();

        $submittedAtRaw = $ordered->max('created_at');
        $submittedAt = $submittedAtRaw
            ? Carbon::parse($submittedAtRaw)->toISOString()
            : null;

        return [
            'grantee_id' => (int) $first->grantee_id,
            'batch_id' => (int) $first->batch_id,
            'batch_name' => $first->batch?->name,
            'student_name' => $first->student_name,
            'student_id' => $first->student_id,
            'status' => $this->packageOverallStatus($statuses),
            'risk_level' => $highestRisk?->risk_level ?? 'low',
            'identity_review_required' => $ordered->contains(
                fn (DocumentSubmission $doc) => (bool) $doc->identity_review_required
            ),
            'submitted_at' => $submittedAt,
            'slots_expected' => $expected,
            'slots_submitted' => $submitted,
            'slots_reviewed' => $reviewed,
            'progress' => "{$submitted}/{$expected}",
            'documents' => $ordered->map(function (DocumentSubmission $doc): array {
                $slot = (string) ($doc->slot_key ?? '');

                return [
                    'id' => $doc->id,
                    'slot_key' => $doc->slot_key,
                    'document_type' => $doc->document_type,
                    'tab_label' => self::SLOT_TAB_LABELS[$slot] ?? $doc->document_type,
                    'status' => $doc->status,
                    'risk_level' => $doc->risk_level,
                    'identity_review_required' => (bool) $doc->identity_review_required,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  list<string>  $statuses
     */
    private function packageOverallStatus(array $statuses): string
    {
        $unique = array_values(array_unique($statuses));
        if ($unique === []) {
            return 'pending_review';
        }
        if (count($unique) === 1) {
            return $unique[0];
        }
        if (in_array('rejected', $unique, true)) {
            return 'rejected';
        }
        if (in_array('resubmission', $unique, true)) {
            return 'resubmission';
        }
        if (in_array('pending_review', $unique, true) || in_array('processing', $unique, true)) {
            return 'pending_review';
        }

        return 'partially_reviewed';
    }
}
