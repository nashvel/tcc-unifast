<?php

namespace App\Services\RequirementVault;

use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\RequirementIdentityCheck;
use App\Support\VaultFileStorage;

class RequirementVaultPresenter
{
    private const SCHOOL_ID_SLOT = 'school_id';

    /**
     * @param  array<string, mixed>  $window
     * @return array<string, mixed>
     */
    public function show(array $window, ?Grantee $grantee): array
    {
        $identity = $grantee
            ? GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first()
            : null;

        return [
            'window' => $window,
            'grantee' => $grantee ? $this->grantee($grantee) : null,
            'slots' => $grantee ? $this->slots($grantee) : [],
            'identity_check' => $grantee ? $this->latestIdentityCheck($grantee) : null,
            'onboarding_refs' => ($identity && $grantee) ? $this->onboardingRefs($identity) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function grantee(Grantee $grantee): array
    {
        return [
            'id' => $grantee->id,
            'student_id' => $grantee->student_id,
            'full_name' => $grantee->full_name,
            'submission_status' => $grantee->submission_status ?? 'not_submitted',
            'submitted_at' => $grantee->submitted_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function slots(Grantee $grantee): array
    {
        $rows = DocumentSubmission::query()
            ->where('batch_id', $grantee->batch_id)
            ->where(function ($query) use ($grantee): void {
                $query->where('grantee_id', $grantee->id);
                if ($grantee->student_id) {
                    $query->orWhere('student_id', $grantee->student_id);
                }
            })
            ->orderBy('id')
            ->get();

        $slots = [];
        foreach ($rows as $item) {
            $slotKey = (string) $item->slot_key;
            if ($slotKey === '') {
                continue;
            }

            try {
                $slots[$slotKey] = $this->document($item);
            } catch (\Throwable $exception) {
                report($exception);
                $slots[$slotKey] = $this->document($item, includeFaceDescriptor: false);
            }
        }

        return $slots;
    }

    /**
     * @return array<string, mixed>
     */
    public function document(DocumentSubmission $item, bool $includeFaceDescriptor = true): array
    {
        $faceDescriptor = null;
        if ($includeFaceDescriptor && $item->slot_key === self::SCHOOL_ID_SLOT) {
            try {
                $faceDescriptor = $item->face_descriptor_payload;
            } catch (\Throwable $exception) {
                report($exception);
                $faceDescriptor = null;
            }
        }

        return [
            'id' => $item->id,
            'slot_key' => $item->slot_key,
            'document_type' => $item->document_type,
            'original_name' => $item->original_name,
            'secondary_original_name' => $item->secondary_original_name,
            'status' => $item->status,
            'risk_level' => $item->risk_level,
            'face_quality_score' => $item->face_quality_score,
            'identity_review_required' => (bool) $item->identity_review_required,
            'identity_review_reason' => $item->identity_review_reason,
            'review_notes' => $item->review_notes,
            'created_at' => $item->created_at,
            'file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'primary'),
            'secondary_file_url' => VaultFileStorage::authStudentSubmissionUrl($item, 'secondary'),
            'face_descriptor' => $faceDescriptor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function identityCheck(RequirementIdentityCheck $check): array
    {
        $selfieFilename = $check->selfie_path ? basename((string) $check->selfie_path) : null;

        return [
            'id' => $check->id,
            'challenge_sequence' => $check->challenge_sequence,
            'result' => $check->result,
            'distance' => $check->distance,
            'distances' => $check->distances,
            'selfie_url' => $selfieFilename
                ? VaultFileStorage::authIdentityUrl($selfieFilename)
                : null,
            'liveness_confirmed' => (bool) $check->liveness_confirmed,
            'confidence_score' => $check->confidence_score,
            'manual_review_required' => $check->manual_review_required,
            'consent_accepted_at' => $check->consent_accepted_at,
            'checked_at' => $check->checked_at,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestIdentityCheck(Grantee $grantee): ?array
    {
        $check = RequirementIdentityCheck::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $grantee->batch_id)
            ->latest('checked_at')
            ->first();

        return $check ? $this->identityCheck($check) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function onboardingRefs(GranteeIdentityProfile $identity): array
    {
        return [
            'id_reference_face_url' => $identity->id_reference_face_path
                ? VaultFileStorage::authIdentityUrl('id_reference_face.jpg')
                : null,
            'id_onboarding_frame_url' => is_string(data_get($identity->id_ocr_payload, 'frame_path'))
                && data_get($identity->id_ocr_payload, 'frame_path') !== ''
                ? VaultFileStorage::authIdentityUrl('id_onboarding_frame.jpg')
                : null,
            'onboarding_selfie_url' => $identity->onboarding_selfie_path
                ? VaultFileStorage::authIdentityUrl('onboarding_selfie.jpg')
                : null,
            'completed' => $identity->isComplete(),
        ];
    }
}
