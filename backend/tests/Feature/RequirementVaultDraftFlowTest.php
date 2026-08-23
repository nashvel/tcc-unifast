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

    }

    public function test_draft_replace_updates_file_and_keeps_draft_status(): void
    {
        Storage::fake('local');
        [$student, $grantee] = $this->activeStudent('STU-REPLACE');

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

        foreach (['course_history', 'grade_slip', 'specimen_signatures'] as $slot) {
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
        $this->assertSame('3/3', $packages[0]['progress']);
        $this->assertSame(
            ['Course History', 'Grade Slip', 'ID (Back-to-Back) & Specimen'],
            collect($packages[0]['documents'])->pluck('tab_label')->all()
        );
    }




    private function seedAllDraftSlots(User $student, Grantee $grantee, ?array $descriptor = null): void
    {

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
