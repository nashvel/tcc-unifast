<?php

namespace App\Services\RequirementVault;

use App\Models\AuditLog;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\RequirementIdentityCheck;
use App\Models\User;
use App\Support\FaceDescriptorMath;
use App\Support\SecureUpload;
use App\Support\VaultFileStorage;
use Illuminate\Validation\ValidationException;

class StoreVaultIdentityCheckService
{
    /** @var list<string> */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(
        User $user,
        Grantee $grantee,
        int $batchId,
        DocumentSubmission $schoolId,
        array $validated,
        ?string $ipAddress,
    ): RequirementIdentityCheck {
        SecureUpload::assertAllowedMime($validated['selfie'], self::IMAGE_MIMES, 'selfie');

        $identity = GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->first();
        if (! is_array($schoolId->face_descriptor_payload)
            || ! is_array($identity?->id_reference_face_descriptor)
            || ! is_array($identity?->onboarding_selfie_descriptor)) {
            throw ValidationException::withMessages([
                'face_descriptor' => 'Stored face references are missing. Re-scan School ID and complete onboarding.',
            ]);
        }

        $live = FaceDescriptorMath::normalize($validated['face_descriptor']);
        $submissionDescriptor = FaceDescriptorMath::normalize(
            $schoolId->face_descriptor_payload,
            'submission_face_descriptor',
        );
        $referenceDescriptor = FaceDescriptorMath::normalize(
            $identity->id_reference_face_descriptor,
            'id_reference_face_descriptor',
        );
        $selfieDescriptor = FaceDescriptorMath::normalize(
            $identity->onboarding_selfie_descriptor,
            'onboarding_selfie_descriptor',
        );

        $threshold = FaceDescriptorMath::threshold();
        $distances = [
            'vs_submission_id' => FaceDescriptorMath::euclidean($submissionDescriptor, $live),
            'vs_id_reference' => FaceDescriptorMath::euclidean($referenceDescriptor, $live),
            'vs_onboarding_selfie' => FaceDescriptorMath::euclidean($selfieDescriptor, $live),
        ];
        $manualReview = ! collect($distances)->every(fn (float $distance): bool => $distance < $threshold);
        $serverDistance = max($distances);
        $checkedAt = now();

        $check = RequirementIdentityCheck::create([
            'user_id' => $user->id,
            'grantee_id' => $grantee->id,
            'batch_id' => $batchId,
            'document_submission_id' => $schoolId->id,
            'challenge_sequence' => $validated['challenge_sequence'],
            'result' => $manualReview ? 'no_match' : 'match',
            'distance' => $serverDistance,
            'distances' => $distances,
            'selfie_path' => VaultFileStorage::storeIdentity(
                $validated['selfie'],
                $grantee->id,
                'submission_selfie',
            ),
            'liveness_confirmed' => true,
            'confidence_score' => max(0, min(100, (1 - $serverDistance) * 100)),
            'manual_review_required' => $manualReview,
            'consent_accepted_at' => $checkedAt,
            'checked_at' => $checkedAt,
            'ip_address' => $ipAddress,
        ]);

        if ($manualReview) {
            $schoolId->update([
                'identity_review_required' => true,
                'identity_review_reason' => 'Submission liveness failed one or more face matches — flagged for manual review.',
                'risk_level' => 'high',
            ]);
        }

        AuditLog::create([
            'actor' => $user->name,
            'role' => 'Student',
            'action' => 'identity_check_logged',
            'module' => 'Requirements Submission',
            'target' => "Submission #{$schoolId->id}",
            'context' => [
                'result' => $check->result,
                'distances' => $distances,
                'distance_source' => 'server',
                'manual_review_required' => $manualReview,
            ],
            'ip_address' => $ipAddress,
        ]);

        return $check;
    }
}
