<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSubmissionPackagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_packages_list_groups_by_grantee_and_batch(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        [$granteeA, $batch] = $this->seedPackage('STU-A', 4);
        [$granteeB] = $this->seedPackage('STU-B', 2, $batch);

        // Drafts must stay out of the staff package queue.
        DocumentSubmission::create([
            'student_id' => 'STU-DRAFT',
            'grantee_id' => $granteeA->id,
            'batch_id' => $batch->id,
            'slot_key' => 'course_history',
            'student_name' => 'Draft Only',
            'document_type' => 'Course History',
            'original_name' => 'draft.pdf',
            'stored_path' => 'documents/draft.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'status' => 'draft',
            'risk_level' => 'low',
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/api/document-submission-packages')
            ->assertOk();

        $rows = collect($response->json('data'));
        $this->assertCount(1, $rows);

        $packageA = $rows->firstWhere('grantee_id', $granteeA->id);
        $this->assertNotNull($packageA);
        $this->assertSame($batch->id, $packageA['batch_id']);
        $this->assertSame('4/4', $packageA['progress']);
        $this->assertSame(4, $packageA['slots_submitted']);
        $this->assertSame(
            ['School ID', 'Course History', 'Grade Slip', 'Specimen'],
            collect($packageA['documents'])->pluck('tab_label')->all()
        );

        // Incomplete (2/4) packages must not appear in Document Validation.
        $this->assertNull($rows->firstWhere('grantee_id', $granteeB->id));
    }

    public function test_staff_packages_hide_incomplete_packages_from_list(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        $this->seedPackage('STU-INC', 2);

        $this->actingAs($staff)
            ->getJson('/api/document-submission-packages')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_staff_package_show_returns_incomplete_package_with_available_slots(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        [$granteeIncomplete, $batch] = $this->seedPackage('STU-INC-SHOW', 2);

        $this->actingAs($staff)
            ->getJson("/api/document-submission-packages/{$granteeIncomplete->id}/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('data.student_id', 'STU-INC-SHOW')
            ->assertJsonPath('data.progress', '2/4')
            ->assertJsonPath('data.slots_submitted', 2)
            ->assertJsonPath('data.slots_expected', 4)
            ->assertJsonCount(2, 'data.documents')
            ->assertJsonPath('data.documents.0.tab_label', 'School ID')
            ->assertJsonPath('data.documents.1.tab_label', 'Course History');
    }

    public function test_staff_package_show_404_when_no_staff_visible_docs(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        [$grantee, $batch] = $this->seedPackage('STU-EMPTY', 0);

        $this->actingAs($staff)
            ->getJson("/api/document-submission-packages/{$grantee->id}/{$batch->id}")
            ->assertNotFound();
    }

    public function test_staff_package_show_returns_ordered_tabs(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        [$grantee, $batch] = $this->seedPackage('STU-SHOW', 4);

        $this->actingAs($staff)
            ->getJson("/api/document-submission-packages/{$grantee->id}/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('data.student_id', 'STU-SHOW')
            ->assertJsonPath('data.progress', '4/4')
            ->assertJsonPath('data.documents.0.tab_label', 'School ID')
            ->assertJsonPath('data.documents.1.tab_label', 'Course History')
            ->assertJsonPath('data.documents.2.tab_label', 'Grade Slip')
            ->assertJsonPath('data.documents.3.tab_label', 'Specimen');
    }

    public function test_students_cannot_list_packages(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU-BLOCK',
            'account_status' => 'active',
        ]);

        $this->actingAs($student)
            ->getJson('/api/document-submission-packages')
            ->assertForbidden();
    }

    /**
     * @return array{0: Grantee, 1: Batch}
     */
    private function seedPackage(string $studentId, int $slotCount, ?Batch $batch = null): array
    {
        $batch ??= Batch::create([
            'name' => 'AY 2026-1',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'status' => 'active',
            'window_status' => 'active',
            'is_active' => true,
            'submission_deadline' => now()->addDays(14),
        ]);

        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => $studentId,
            'account_status' => 'active',
            'name' => "Student {$studentId}",
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
            'submission_status' => 'docs_submitted',
        ]);

        $slots = [
            'school_id' => 'School ID',
            'course_history' => 'Course History',
            'grade_slip' => 'Grade Slip',
            'specimen_signatures' => '3 Specimen Signatures',
        ];

        foreach (array_slice($slots, 0, $slotCount, true) as $slotKey => $label) {
            DocumentSubmission::create([
                'student_id' => $studentId,
                'grantee_id' => $grantee->id,
                'batch_id' => $batch->id,
                'slot_key' => $slotKey,
                'student_name' => $student->name,
                'document_type' => $label,
                'original_name' => $slotKey.'.pdf',
                'stored_path' => "documents/{$grantee->id}/{$slotKey}.pdf",
                'mime_type' => 'application/pdf',
                'file_size' => 10,
                'status' => 'pending_review',
                'risk_level' => 'low',
            ]);
        }

        return [$grantee, $batch];
    }
}
