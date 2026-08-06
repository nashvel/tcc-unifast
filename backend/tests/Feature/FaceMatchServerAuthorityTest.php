<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\RequirementIdentityCheck;
use App\Models\User;
use App\Support\FaceDescriptorMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FaceDescriptorFixtures;
use Tests\TestCase;

class FaceMatchServerAuthorityTest extends TestCase
{
    use FaceDescriptorFixtures;
    use RefreshDatabase;

    public function test_vault_identity_check_ignores_spoofed_client_match_distances(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$student, $grantee] = $this->activeStudent();
        $reference = $this->faceDescriptor(0);
        $selfie = $this->faceDescriptor(0);
        $submissionDescriptor = $this->faceDescriptor(0);
        $liveMismatch = $this->faceDescriptor(20);

        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'completed',
            'id_reference_face_path' => 'identity/'.$grantee->id.'/id_reference_face.jpg',
            'onboarding_selfie_path' => 'identity/'.$grantee->id.'/onboarding_selfie.jpg',
            'id_reference_face_descriptor' => $reference,
            'onboarding_selfie_descriptor' => $selfie,
            'onboarding_completed_at' => now(),
        ]);

        $schoolId = DocumentSubmission::create([
            'student_id' => $student->student_id,
            'grantee_id' => $grantee->id,
            'batch_id' => $grantee->batch_id,
            'slot_key' => 'school_id',
            'student_name' => $student->name,
            'document_type' => 'School ID',
            'original_name' => 'id.jpg',
            'stored_path' => 'identity/'.$grantee->id.'/id_scan_submission.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 10,
            'status' => 'draft',
            'risk_level' => 'low',
            'face_descriptor_payload' => $submissionDescriptor,
        ]);
        Storage::disk('local')->put('identity/'.$grantee->id.'/id_scan_submission.jpg', "\xFF\xD8\xFFfake");

        $response = $this->actingAs($student)->post('/api/student/requirement-vault/identity-check', [
            'challenge_sequence' => ['blink', 'turn_left', 'turn_right'],
            'face_descriptor' => $liveMismatch,
            'result' => 'match',
            'distance' => 0.01,
            'distances' => [
                'vs_submission_id' => 0.01,
                'vs_id_reference' => 0.01,
                'vs_onboarding_selfie' => 0.01,
            ],
            'consent_accepted' => true,
            'liveness_confirmed' => true,
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertOk();

        $this->assertSame('no_match', $response->json('data.result'));
        $this->assertTrue((bool) $response->json('data.manual_review_required'));
        $this->assertGreaterThanOrEqual(
            FaceDescriptorMath::threshold(),
            (float) $response->json('data.distance'),
        );
        $this->assertFalse((bool) $response->json('submitted'));
        $this->assertSame('not_submitted', $grantee->fresh()->submission_status);

        $check = RequirementIdentityCheck::query()->latest('id')->first();
        $this->assertNotNull($check);
        $this->assertSame('no_match', $check->result);
        $this->assertGreaterThanOrEqual(FaceDescriptorMath::threshold(), (float) $check->distance);
        $this->assertSame($schoolId->id, $check->document_submission_id);
        Queue::assertNothingPushed();
    }

    public function test_vault_identity_check_accepts_matching_live_descriptor(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$student, $grantee] = $this->activeStudent();
        $same = $this->faceDescriptor(3);

        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'completed',
            'id_reference_face_path' => 'identity/'.$grantee->id.'/id_reference_face.jpg',
            'onboarding_selfie_path' => 'identity/'.$grantee->id.'/onboarding_selfie.jpg',
            'id_reference_face_descriptor' => $same,
            'onboarding_selfie_descriptor' => $same,
            'onboarding_completed_at' => now(),
        ]);

        DocumentSubmission::create([
            'student_id' => $student->student_id,
            'grantee_id' => $grantee->id,
            'batch_id' => $grantee->batch_id,
            'slot_key' => 'school_id',
            'student_name' => $student->name,
            'document_type' => 'School ID',
            'original_name' => 'id.jpg',
            'stored_path' => 'identity/'.$grantee->id.'/id_scan_submission.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 10,
            'status' => 'draft',
            'risk_level' => 'low',
            'face_descriptor_payload' => $same,
        ]);

        $this->actingAs($student)->post('/api/student/requirement-vault/identity-check', [
            'challenge_sequence' => ['blink', 'turn_left', 'turn_right'],
            'face_descriptor' => $same,
            'result' => 'no_match',
            'distance' => 0.99,
            'distances' => [
                'vs_submission_id' => 0.99,
                'vs_id_reference' => 0.99,
                'vs_onboarding_selfie' => 0.99,
            ],
            'consent_accepted' => true,
            'liveness_confirmed' => true,
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertOk()
            ->assertJsonPath('data.result', 'match')
            ->assertJsonPath('data.manual_review_required', false)
            ->assertJsonPath('submitted', false);

        $this->assertSame('not_submitted', $grantee->fresh()->submission_status);
        Queue::assertNothingPushed();
    }

    /**
     * @return array{0: User, 1: Grantee}
     */
    private function activeStudent(): array
    {
        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-FACE',
            'account_status' => 'active',
        ]);
        $batch = Batch::create([
            'name' => 'AY 2026-1',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'status' => 'active',
            'window_status' => 'active',
            'is_active' => true,
            'submission_deadline' => now()->addDays(14),
        ]);
        $grantee = Grantee::create([
            'user_id' => $student->id,
            'batch_id' => $batch->id,
            'student_id' => 'STU-FACE',
            'student_number' => '2026-FACE',
            'full_name' => $student->name,
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '1',
            'status' => 'verified',
            'submission_status' => 'not_submitted',
        ]);

        return [$student, $grantee];
    }
}
