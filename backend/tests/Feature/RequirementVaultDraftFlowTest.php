<?php

namespace Tests\Feature;

use App\Jobs\ProcessRequirementSubmissionPipeline;
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

class RequirementVaultDraftFlowTest extends TestCase
{
    use FaceDescriptorFixtures;
    use RefreshDatabase;

    public function test_document_upload_stores_draft_and_is_hidden_from_staff_queue(): void
    {
        Storage::fake('local');
        [$student, $grantee] = $this->activeStudent('STU-DRAFT');
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        $this->seedSchoolIdSlot($student, $grantee);

        $pdf = UploadedFile::fake()->createWithContent(
            'course-history.pdf',
            "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n",
        );

        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'course_history',
                'file' => $pdf,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.original_name', 'course-history.pdf');

        $this->assertDatabaseHas('document_submissions', [
            'student_id' => $student->student_id,
            'slot_key' => 'course_history',
            'status' => 'draft',
        ]);

        $this->actingAs($staff)
            ->getJson('/api/document-submissions')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $draftRows = collect(
            $this->actingAs($staff)
                ->getJson('/api/document-submissions?status=draft')
                ->assertOk()
                ->json('data')
        );
        $this->assertTrue(
            $draftRows->contains(fn ($row) => ($row['slot_key'] ?? '') === 'course_history'),
            'Draft filter should include the uploaded course_history submission.'
        );
        $this->assertTrue(
            $draftRows->contains(fn ($row) => ($row['slot_key'] ?? '') === 'school_id'),
            'Draft filter may also include the seeded school_id identity draft.'
        );
    }

    public function test_draft_replace_updates_file_and_keeps_draft_status(): void
    {
        Storage::fake('local');
        [$student, $grantee] = $this->activeStudent('STU-REPLACE');
        $this->seedSchoolIdSlot($student, $grantee);

        $first = UploadedFile::fake()->createWithContent(
            'course-v1.pdf',
            "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n",
        );
        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'course_history',
                'file' => $first,
            ])
            ->assertOk();

        $original = DocumentSubmission::query()
            ->where('student_id', $student->student_id)
            ->where('slot_key', 'course_history')
            ->firstOrFail();
        $oldPath = $original->stored_path;

        $second = UploadedFile::fake()->createWithContent(
            'course-v2.pdf',
            "%PDF-1.4\n%âãÏÓ\n1 0 obj<< /Title (v2) >>endobj\ntrailer<<>>\n%%EOF\n",
        );
        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'course_history',
                'file' => $second,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.original_name', 'course-v2.pdf');

        $updated = $original->fresh();
        $this->assertSame('draft', $updated->status);
        $this->assertSame('course-v2.pdf', $updated->original_name);
        $this->assertNotSame($oldPath, $updated->stored_path);
        $this->assertFalse(Storage::disk('local')->exists($oldPath));
        $this->assertTrue(Storage::disk('local')->exists($updated->stored_path));
    }

    public function test_confirm_rejects_when_any_required_slot_is_missing(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$student, $grantee] = $this->activeStudent('STU-MISS');
        $this->seedSchoolIdSlot($student, $grantee);

        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'course_history',
                'file' => UploadedFile::fake()->createWithContent(
                    'course.pdf',
                    "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n",
                ),
            ])
            ->assertOk();

        // Missing grade_slip + specimen_signatures — confirm must fail.
        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/confirm')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['submission']);

        $this->assertSame('not_submitted', $grantee->fresh()->submission_status);
        Queue::assertNothingPushed();
        $this->assertDatabaseHas('document_submissions', [
            'grantee_id' => $grantee->id,
            'slot_key' => 'school_id',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('document_submissions', [
            'grantee_id' => $grantee->id,
            'slot_key' => 'course_history',
            'status' => 'draft',
        ]);
    }

    public function test_confirm_without_liveness_promotes_drafts_and_queues_pipeline(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$student, $grantee] = $this->activeStudent('STU-CONFIRM');
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        $this->seedAllDraftSlots($student, $grantee);

        $this->actingAs($staff)
            ->getJson('/api/document-submissions')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/confirm')
            ->assertOk()
            ->assertJsonPath('grantee.submission_status', 'docs_submitted')
            ->assertJsonPath('submitted', true);

        foreach (['school_id', 'course_history', 'grade_slip', 'specimen_signatures'] as $slot) {
            $this->assertDatabaseHas('document_submissions', [
                'grantee_id' => $grantee->id,
                'slot_key' => $slot,
                'status' => 'pending_review',
            ]);
        }

        Queue::assertPushed(ProcessRequirementSubmissionPipeline::class, function ($job) use ($grantee) {
            return $job->granteeId === $grantee->id && $job->batchId === $grantee->batch_id;
        });

        $staffRows = collect($this->actingAs($staff)->getJson('/api/document-submissions')->json('data'));
        $this->assertTrue($staffRows->contains(fn ($row) => $row['slot_key'] === 'course_history'));
        $this->assertFalse($staffRows->contains(fn ($row) => ($row['status'] ?? '') === 'draft'));

        $packages = collect(
            $this->actingAs($staff)->getJson('/api/document-submission-packages')->json('data')
        );
        $this->assertCount(1, $packages);
        $this->assertSame($grantee->id, $packages[0]['grantee_id']);
        $this->assertSame('4/4', $packages[0]['progress']);
        $this->assertSame(
            ['School ID', 'Course History', 'Grade Slip', 'Specimen'],
            collect($packages[0]['documents'])->pluck('tab_label')->all()
        );
    }

    public function test_confirm_rejects_when_profile_name_mismatches_grantee(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$student, $grantee] = $this->activeStudent('STU-NAME');
        $this->seedAllDraftSlots($student, $grantee);
        $grantee->update(['full_name' => 'Completely Different Person']);

        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/confirm')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name_match']);

        Queue::assertNothingPushed();
        $this->assertSame('not_submitted', $grantee->fresh()->submission_status);
    }

    public function test_identity_check_logs_without_promoting_drafts(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$student, $grantee] = $this->activeStudent('STU-LIVE');
        $descriptor = $this->faceDescriptor(3);
        $this->seedSchoolIdSlot($student, $grantee, $descriptor);

        $response = $this->actingAs($student)
            ->post('/api/student/requirement-vault/identity-check', [
                'challenge_sequence' => ['blink', 'turn_left', 'turn_right'],
                'face_descriptor' => $descriptor,
                'result' => 'no_match',
                'distance' => 0.99,
                'consent_accepted' => true,
                'liveness_confirmed' => true,
                'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('data.result', 'match')
            ->assertJsonPath('submitted', false);

        $this->assertLessThan(FaceDescriptorMath::threshold(), (float) $response->json('data.distance'));
        $this->assertSame('not_submitted', $grantee->fresh()->submission_status);
        Queue::assertNothingPushed();
        $this->assertDatabaseHas('document_submissions', [
            'grantee_id' => $grantee->id,
            'slot_key' => 'school_id',
            'status' => 'draft',
        ]);
    }

    public function test_confirm_still_works_after_optional_liveness_log(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$student, $grantee] = $this->activeStudent('STU-AFTER-LIVE');
        $this->seedAllDraftSlots($student, $grantee);

        RequirementIdentityCheck::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'batch_id' => $grantee->batch_id,
            'document_submission_id' => DocumentSubmission::query()
                ->where('slot_key', 'school_id')
                ->where('grantee_id', $grantee->id)
                ->value('id'),
            'challenge_sequence' => ['blink', 'turn_left', 'turn_right'],
            'result' => 'match',
            'distance' => 0.2,
            'distances' => [
                'vs_submission_id' => 0.2,
                'vs_id_reference' => 0.2,
                'vs_onboarding_selfie' => 0.2,
            ],
            'selfie_path' => 'identity/'.$grantee->id.'/submission_selfie.jpg',
            'liveness_confirmed' => true,
            'confidence_score' => 80,
            'manual_review_required' => false,
            'consent_accepted_at' => now(),
            'checked_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/confirm')
            ->assertOk()
            ->assertJsonPath('grantee.submission_status', 'docs_submitted');

        foreach (['school_id', 'course_history', 'grade_slip', 'specimen_signatures'] as $slot) {
            $this->assertDatabaseHas('document_submissions', [
                'grantee_id' => $grantee->id,
                'slot_key' => $slot,
                'status' => 'pending_review',
            ]);
        }

        Queue::assertPushed(ProcessRequirementSubmissionPipeline::class);
    }

    private function seedAllDraftSlots(User $student, Grantee $grantee, ?array $descriptor = null): void
    {
        $this->seedSchoolIdSlot($student, $grantee, $descriptor);

        foreach (['course_history' => 'course.pdf', 'grade_slip' => 'grades.pdf'] as $slot => $name) {
            $this->actingAs($student)
                ->post('/api/student/requirement-vault/document', [
                    'slot_key' => $slot,
                    'file' => UploadedFile::fake()->createWithContent(
                        $name,
                        "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n",
                    ),
                ])
                ->assertOk()
                ->assertJsonPath('data.status', 'draft');
        }

        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'specimen_signatures',
                'file' => UploadedFile::fake()->image('specimens.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    private function seedSchoolIdSlot(User $student, Grantee $grantee, ?array $descriptor = null): void
    {
        $this->openBatchWindow($grantee);
        $face = $descriptor ?? $this->faceDescriptor(1);
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'completed',
            'id_reference_face_path' => 'identity/'.$grantee->id.'/id_reference_face.jpg',
            'onboarding_selfie_path' => 'identity/'.$grantee->id.'/onboarding_selfie.jpg',
            'id_reference_face_descriptor' => $face,
            'onboarding_selfie_descriptor' => $face,
            'onboarding_completed_at' => now(),
            'id_ocr_payload' => [
                'extracted_name' => $student->name,
            ],
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
            'face_descriptor_payload' => $face,
            'metadata_payload' => [
                'ocr' => [
                    'extracted_name' => $student->name,
                ],
            ],
        ]);
        Storage::disk('local')->put('identity/'.$grantee->id.'/id_scan_submission.jpg', "\xFF\xD8\xFFfake");
    }

    /**
     * @return array{0: User, 1: Grantee}
     */
    private function activeStudent(string $studentId): array
    {
        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => $studentId,
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
            'student_id' => $studentId,
            'student_number' => '2026-'.$studentId,
            'full_name' => $student->name,
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '1',
            'status' => 'verified',
            'submission_status' => 'not_submitted',
        ]);

        return [$student, $grantee];
    }

    private function openBatchWindow(Grantee $grantee): void
    {
        $grantee->batch?->update([
            'is_active' => true,
            'window_status' => 'active',
            'status' => 'active',
            'submission_deadline' => now()->addDays(7),
        ]);
    }
}
