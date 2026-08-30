<?php

namespace Tests\Feature;

use App\Jobs\ProcessRequirementSubmissionPipeline;
use App\Models\Batch;
use App\Models\BatchNotification;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FaceDescriptorFixtures;
use Tests\TestCase;

class RequirementVaultResubmitFlowTest extends TestCase
{
    use FaceDescriptorFixtures;
    use RefreshDatabase;

    public function test_staff_return_exposes_notes_and_allows_single_slot_resubmit(): void
    {
        Storage::fake('local');
        Queue::fake();

        [$student, $grantee] = $this->activeStudent('STU-RESUB');
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        $this->seedSubmittedPackage($student, $grantee);

        $gradeSlip = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('slot_key', 'grade_slip')
            ->firstOrFail();

        $this->actingAs($staff)
            ->postJson("/api/document-submissions/{$gradeSlip->id}/review", [
                'decision' => 'resubmission',
                'notes' => 'Blurry scan — please re-upload a clearer Grade Slip.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resubmission')
            ->assertJsonPath('data.review_notes', 'Blurry scan — please re-upload a clearer Grade Slip.');

        $this->assertSame('resubmission_requested', $grantee->fresh()->submission_status);
        $this->assertDatabaseHas('batch_notifications', [
            'user_id' => $student->id,
            'type' => 'resubmission_requested',
        ]);
        $this->assertTrue(
            BatchNotification::query()
                ->where('user_id', $student->id)
                ->where('type', 'resubmission_requested')
                ->where('body', 'like', '%Blurry scan%')
                ->exists()
        );

        $vault = $this->actingAs($student)
            ->getJson('/api/student/requirement-vault')
            ->assertOk();

        $this->assertSame('resubmission_requested', $vault->json('grantee.submission_status'));
        $this->assertSame('resubmission', $vault->json('slots.grade_slip.status'));
        $this->assertSame(
            'Blurry scan — please re-upload a clearer Grade Slip.',
            $vault->json('slots.grade_slip.review_notes')
        );
        $this->assertSame('pending_review', $vault->json('slots.course_history.status'));
        $this->assertNull($vault->json('slots.course_history.review_notes'));

        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'course_history',
                'file' => UploadedFile::fake()->createWithContent(
                    'blocked.pdf',
                    "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n",
                ),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['submission']);

        $replacement = UploadedFile::fake()->createWithContent(
            'grade-slip-v2.pdf',
            "%PDF-1.4\n%âãÏÓ\n1 0 obj<< /Title (resubmit) >>endobj\ntrailer<<>>\n%%EOF\n",
        );

        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'grade_slip',
                'file' => $replacement,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.original_name', 'grade-slip-v2.pdf')
            ->assertJsonPath('data.review_notes', 'Blurry scan — please re-upload a clearer Grade Slip.');

        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/confirm')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['submission']);

        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/resubmit-slot', [
                'slot_key' => 'grade_slip',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('grantee.submission_status', 'docs_submitted')
            ->assertJsonPath('resubmitted', true);

        $this->assertDatabaseHas('document_submissions', [
            'id' => $gradeSlip->id,
            'status' => 'pending_review',
        ]);
        $this->assertDatabaseHas('document_submissions', [
            'grantee_id' => $grantee->id,
            'slot_key' => 'course_history',
            'status' => 'pending_review',
        ]);
        $this->assertSame('docs_submitted', $grantee->fresh()->submission_status);

        Queue::assertPushed(ProcessRequirementSubmissionPipeline::class, function ($job) use ($grantee) {
            return $job->granteeId === $grantee->id && $job->batchId === $grantee->batch_id;
        });
    }

    public function test_legacy_missing_slot_upload_during_resubmission_reaches_staff(): void
    {
        Storage::fake('local');
        Queue::fake();

        [$student, $grantee] = $this->activeStudent('STU-LEGACY');
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        $this->seedSubmittedPackage($student, $grantee);

        // Simulate legacy incomplete package: remove specimen before staff return.
        DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('slot_key', 'specimen_signatures')
            ->delete();

        $gradeSlip = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('slot_key', 'grade_slip')
            ->firstOrFail();

        $this->actingAs($staff)
            ->postJson("/api/document-submissions/{$gradeSlip->id}/review", [
                'decision' => 'resubmission',
                'notes' => 'Please re-upload Grade Slip.',
            ])
            ->assertOk();

        // Incomplete package stays hidden from Document Validation.
        $this->actingAs($staff)
            ->getJson('/api/document-submission-packages')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Filling the never-submitted slot goes straight to pending_review.
        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'specimen_signatures',
                'file' => UploadedFile::fake()->image('specimens.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_review');

        // Non-returned pending slots stay locked.
        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'course_history',
                'file' => UploadedFile::fake()->createWithContent(
                    'blocked.pdf',
                    "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n",
                ),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['submission']);

        $this->assertDatabaseHas('document_submissions', [
            'grantee_id' => $grantee->id,
            'slot_key' => 'specimen_signatures',
            'status' => 'pending_review',
        ]);
        $this->assertDatabaseHas('document_submissions', [
            'grantee_id' => $grantee->id,
            'slot_key' => 'course_history',
            'status' => 'pending_review',
        ]);
    }

    public function test_resubmit_slot_requires_draft_after_return(): void
    {
        Storage::fake('local');
        Queue::fake();

        [$student, $grantee] = $this->activeStudent('STU-RESUB-DRAFT');
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        $this->seedSubmittedPackage($student, $grantee);

        $gradeSlip = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('slot_key', 'grade_slip')
            ->firstOrFail();

        $this->actingAs($staff)
            ->postJson("/api/document-submissions/{$gradeSlip->id}/review", [
                'decision' => 'resubmission',
                'notes' => 'Needs clearer copy.',
            ])
            ->assertOk();

        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/resubmit-slot', [
                'slot_key' => 'grade_slip',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slot_key']);

        Queue::assertNotPushed(ProcessRequirementSubmissionPipeline::class);
    }

    private function seedSubmittedPackage(User $student, Grantee $grantee): void
    {
        $this->openBatchWindow($grantee);
        $face = $this->faceDescriptor(1);
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

        foreach ([
            'course_history' => ['Course History', 'course.pdf', 'application/pdf'],
            'grade_slip' => ['Grade Slip', 'grades.pdf', 'application/pdf'],
            'specimen_signatures' => ['ID (Back-to-Back) & Specimen', 'specimens.jpg', 'image/jpeg'],
        ] as $slot => [$label, $name, $mime]) {
            DocumentSubmission::create([
                'student_id' => $student->student_id,
                'grantee_id' => $grantee->id,
                'batch_id' => $grantee->batch_id,
                'slot_key' => $slot,
                'student_name' => $student->name,
                'document_type' => $label,
                'original_name' => $name,
                'stored_path' => 'vault/'.$grantee->id.'/'.$name,
                'mime_type' => $mime,
                'file_size' => 10,
                'status' => 'pending_review',
                'risk_level' => 'low',
            ]);
            Storage::disk('local')->put('vault/'.$grantee->id.'/'.$name, $mime === 'application/pdf'
                ? "%PDF-1.4\n%%EOF\n"
                : "\xFF\xD8\xFFfake");
        }

        $grantee->update([
            'submission_status' => 'docs_submitted',
            'submitted_at' => now(),
        ]);
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
            'name' => 'Resubmit Student '.$studentId,
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
