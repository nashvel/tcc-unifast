<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\User;
use App\Support\VaultFileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RequirementVaultSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_download_another_students_submission_file(): void
    {
        Storage::fake('local');
        [$owner] = $this->activeStudent('STU-OWNER');
        [$intruder] = $this->activeStudent('STU-INTRUDER');

        $submission = DocumentSubmission::create([
            'student_id' => $owner->student_id,
            'student_name' => $owner->name,
            'document_type' => 'Course History',
            'slot_key' => 'course_history',
            'original_name' => 'history.pdf',
            'stored_path' => 'submissions/secret.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'status' => 'pending_review',
            'risk_level' => 'low',
        ]);
        Storage::disk('local')->put('submissions/secret.pdf', '%PDF-1.4 secret');

        $this->actingAs($intruder)
            ->get("/api/student/requirement-vault/files/{$submission->id}/primary")
            ->assertForbidden();

        $this->actingAs($owner)
            ->get("/api/student/requirement-vault/files/{$submission->id}/primary")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_signed_document_url_rejects_tampered_signature(): void
    {
        Storage::fake('local');
        $submission = DocumentSubmission::create([
            'student_id' => 'STU-1',
            'student_name' => 'Maria Santos',
            'document_type' => 'Grade Slip',
            'slot_key' => 'grade_slip',
            'original_name' => 'grades.pdf',
            'stored_path' => 'submissions/grades.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'status' => 'pending_review',
            'risk_level' => 'low',
        ]);
        Storage::disk('local')->put('submissions/grades.pdf', '%PDF-1.4 grades');

        $url = URL::temporarySignedRoute(
            'signed.document-files.show',
            now()->addMinutes(5),
            ['submission' => $submission->id, 'variant' => 'primary'],
        );

        $this->get($url)->assertOk();

        $tampered = preg_replace('/signature=[^&]+/', 'signature=invalid', $url);
        $this->get($tampered)->assertForbidden();
    }

    public function test_upload_rejects_non_pdf_payload_for_course_history(): void
    {
        Storage::fake('local');
        [$student, $grantee] = $this->activeStudent('STU-PDF');
        $this->openBatchWindow($grantee);
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'completed',
            'id_reference_face_path' => 'identity/'.$grantee->id.'/id_reference_face.jpg',
            'onboarding_selfie_path' => 'identity/'.$grantee->id.'/onboarding_selfie.jpg',
            'onboarding_completed_at' => now(),
        ]);


        $exeAsPdf = UploadedFile::fake()->createWithContent('course-history.pdf', 'MZ-not-a-pdf');

        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'course_history',
                'file' => $exeAsPdf,
            ])
            ->assertUnprocessable();
    }

    public function test_confirm_is_rejected_when_already_submitted(): void
    {
        [$student, $grantee] = $this->activeStudent('STU-DONE');
        $this->openBatchWindow($grantee);
        $grantee->update(['submission_status' => 'docs_submitted', 'submitted_at' => now()]);

        $this->actingAs($student)
            ->postJson('/api/student/requirement-vault/confirm')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['submission']);
    }

    public function test_vault_file_urls_are_authenticated_not_public_storage_paths(): void
    {
        Storage::fake('local');
        [$student, $grantee] = $this->activeStudent('STU-URL');
        $this->openBatchWindow($grantee);
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'completed',
            'id_reference_face_path' => 'identity/'.$grantee->id.'/id_reference_face.jpg',
            'onboarding_selfie_path' => 'identity/'.$grantee->id.'/onboarding_selfie.jpg',
            'onboarding_completed_at' => now(),
        ]);
        Storage::disk('local')->put('identity/'.$grantee->id.'/id_reference_face.jpg', "\xFF\xD8\xFFfake");
        Storage::disk('local')->put('identity/'.$grantee->id.'/onboarding_selfie.jpg', "\xFF\xD8\xFFfake");

        $submission = DocumentSubmission::create([
            'student_id' => $student->student_id,
            'grantee_id' => $grantee->id,
            'batch_id' => $grantee->batch_id,
            'slot_key' => 'course_history',
            'student_name' => $student->name,
            'document_type' => 'Course History',
            'original_name' => 'history.pdf',
            'stored_path' => 'submissions/history.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'status' => 'pending_review',
            'risk_level' => 'low',
        ]);
        Storage::disk('local')->put('submissions/history.pdf', '%PDF-1.4 history');

        $response = $this->actingAs($student)
            ->getJson('/api/student/requirement-vault')
            ->assertOk();

        $fileUrl = $response->json('slots.course_history.file_url');
        $this->assertIsString($fileUrl);
        $this->assertStringContainsString('/api/student/requirement-vault/files/'.$submission->id.'/primary', $fileUrl);
        $this->assertStringNotContainsString('/storage/submissions/', $fileUrl);
        $this->assertStringNotContainsString('signature=', $fileUrl);

        $refUrl = $response->json('onboarding_refs.id_reference_face_url');
        $this->assertIsString($refUrl);
        $this->assertStringContainsString('/api/student/identity-onboarding/photos/id_reference_face.jpg', $refUrl);
    }

    public function test_path_traversal_relative_paths_are_rejected(): void
    {
        $this->assertNull(VaultFileStorage::tryNormalizeRelativePath('../etc/passwd'));
        $this->assertNull(VaultFileStorage::tryNormalizeRelativePath('submissions/../../secrets.txt'));
    }

    public function test_document_upload_persists_in_vault_show(): void
    {
        Storage::fake('local');
        [$student, $grantee] = $this->activeStudent('STU-PERSIST');
        $this->openBatchWindow($grantee);
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'completed',
            'id_reference_face_path' => 'identity/'.$grantee->id.'/id_reference_face.jpg',
            'onboarding_selfie_path' => 'identity/'.$grantee->id.'/onboarding_selfie.jpg',
            'onboarding_completed_at' => now(),
        ]);


        $pdf = UploadedFile::fake()->createWithContent('course-history.pdf', "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'course_history',
                'file' => $pdf,
            ])
            ->assertOk()
            ->assertJsonPath('data.slot_key', 'course_history')
            ->assertJsonPath('data.original_name', 'course-history.pdf');

        $gradePdf = UploadedFile::fake()->createWithContent('grade-slip.pdf', "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'grade_slip',
                'file' => $gradePdf,
            ])
            ->assertOk()
            ->assertJsonPath('data.slot_key', 'grade_slip');

        $specimen = UploadedFile::fake()->image('specimens.jpg', 640, 480);
        $this->actingAs($student)
            ->post('/api/student/requirement-vault/document', [
                'slot_key' => 'specimen_signatures',
                'file' => $specimen,
            ])
            ->assertOk()
            ->assertJsonPath('data.slot_key', 'specimen_signatures')
            ->assertJsonPath('data.original_name', 'specimens.jpg');

        $this->assertDatabaseHas('document_submissions', [
            'student_id' => $student->student_id,
            'grantee_id' => $grantee->id,
            'batch_id' => $grantee->batch_id,
            'slot_key' => 'course_history',
            'original_name' => 'course-history.pdf',
            'status' => 'draft',
        ]);

        $course = DocumentSubmission::query()
            ->where('student_id', $student->student_id)
            ->where('slot_key', 'course_history')
            ->firstOrFail();
        $this->assertMatchesRegularExpression(
            '#^documents/'.$grantee->id.'/'.$grantee->batch_id.'/[a-f0-9]{32}\.pdf$#',
            $course->stored_path,
        );
        $this->assertTrue(Storage::disk('local')->exists($course->stored_path));

        $specimenRow = DocumentSubmission::query()
            ->where('student_id', $student->student_id)
            ->where('slot_key', 'specimen_signatures')
            ->firstOrFail();
        $this->assertMatchesRegularExpression(
            '#^documents/'.$grantee->id.'/'.$grantee->batch_id.'/[a-f0-9]{32}\.jpg$#',
            $specimenRow->stored_path,
        );

        $this->actingAs($student)
            ->getJson('/api/student/requirement-vault')
            ->assertOk()
            ->assertJsonPath('slots.course_history.original_name', 'course-history.pdf')
            ->assertJsonPath('slots.course_history.status', 'draft')
            ->assertJsonPath('slots.course_history.slot_key', 'course_history')
            ->assertJsonPath('slots.grade_slip.original_name', 'grade-slip.pdf')
            ->assertJsonPath('slots.specimen_signatures.original_name', 'specimens.jpg')
;
    }

    public function test_identity_photo_download_uses_db_path_not_guessable_filename(): void
    {
        Storage::fake('local');
        [$student, $grantee] = $this->activeStudent('STU-IDPHOTO');
        $hashed = 'identity/'.$grantee->id.'/'.bin2hex(random_bytes(16)).'_id_reference_face.jpg';
        GranteeIdentityProfile::create([
            'user_id' => $student->id,
            'grantee_id' => $grantee->id,
            'status' => 'completed',
            'id_reference_face_path' => $hashed,
            'onboarding_selfie_path' => null,
            'onboarding_completed_at' => now(),
        ]);
        Storage::disk('local')->put($hashed, "\xFF\xD8\xFFfake");

        $this->actingAs($student)
            ->get('/api/student/identity-onboarding/photos/id_reference_face.jpg')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        [$intruder] = $this->activeStudent('STU-IDPHOTO-X');
        $this->actingAs($intruder)
            ->get('/api/student/identity-onboarding/photos/id_reference_face.jpg')
            ->assertNotFound();
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
