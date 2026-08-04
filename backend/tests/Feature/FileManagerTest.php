<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\MasterlistImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_requirements_tab_returns_hierarchy_fields_and_urls(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        [$grantee, $batch] = $this->seedSubmission();

        MasterlistImport::create([
            'batch_id' => $batch->id,
            'uploaded_by' => $staff->id,
            'original_name' => 'should-not-appear.csv',
            'stored_path' => 'masterlist-imports/hidden.csv',
            'status' => 'previewed',
            'total_rows' => 1,
            'valid_rows' => 1,
            'invalid_rows' => 0,
            'imported_rows' => 0,
        ]);

        $this->actingAs($staff)
            ->getJson('/api/files?tab=requirements')
            ->assertOk()
            ->assertJsonPath('tab', 'requirements')
            ->assertJsonPath('data.0.kind', 'requirement')
            ->assertJsonPath('data.0.batch_id', $batch->id)
            ->assertJsonPath('data.0.batch_name', $batch->name)
            ->assertJsonPath('data.0.grantee_id', $grantee->id)
            ->assertJsonPath('data.0.student_id', $grantee->student_id)
            ->assertJsonPath('data.0.slot_key', 'school_id')
            ->assertJsonPath('data.0.document_type', 'School ID')
            ->assertJsonPath('data.0.preview_url', '/api/document-submissions/'.DocumentSubmission::query()->first()->id.'/file/primary')
            ->assertJsonMissing(['kind' => 'import']);
    }

    public function test_imports_tab_returns_only_masterlist_rows(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        [$grantee, $batch] = $this->seedSubmission();

        Storage::disk('local')->put('masterlist-imports/demo.csv', "student_id,full_name\n1,Test\n");
        $import = MasterlistImport::create([
            'batch_id' => $batch->id,
            'uploaded_by' => $staff->id,
            'original_name' => 'demo.csv',
            'stored_path' => 'masterlist-imports/demo.csv',
            'status' => 'previewed',
            'total_rows' => 1,
            'valid_rows' => 1,
            'invalid_rows' => 0,
            'imported_rows' => 0,
        ]);

        $this->actingAs($staff)
            ->getJson('/api/files?tab=imports')
            ->assertOk()
            ->assertJsonPath('tab', 'imports')
            ->assertJsonPath('data.0.kind', 'import')
            ->assertJsonPath('data.0.import_id', $import->id)
            ->assertJsonPath('data.0.download_url', '/api/files/imports/'.$import->id.'/download')
            ->assertJsonMissing(['kind' => 'requirement']);

        $this->actingAs($staff)
            ->get('/api/files/imports/'.$import->id.'/download')
            ->assertOk();
    }

    public function test_student_cannot_list_files(): void
    {
        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active']);

        $this->actingAs($student)
            ->getJson('/api/files')
            ->assertForbidden();
    }

    /**
     * @return array{0: Grantee, 1: Batch}
     */
    private function seedSubmission(): array
    {
        $batch = Batch::create([
            'name' => 'TES AY 2026-2027 1st',
            'academic_year' => '2026-2027',
            'semester' => '1st',
            'status' => 'open',
            'submission_deadline' => now()->addMonth()->toDateString(),
        ]);

        $student = User::factory()->create([
            'role' => 'student',
            'student_id' => '20231909',
            'account_status' => 'active',
            'name' => 'Rafael Balacuit',
        ]);

        $grantee = Grantee::create([
            'user_id' => $student->id,
            'batch_id' => $batch->id,
            'student_id' => '20231909',
            'student_number' => '2023-1909',
            'full_name' => 'Rafael Balacuit',
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '3',
            'status' => 'verified',
            'submission_status' => 'docs_submitted',
        ]);

        DocumentSubmission::create([
            'student_id' => '20231909',
            'grantee_id' => $grantee->id,
            'batch_id' => $batch->id,
            'slot_key' => 'school_id',
            'student_name' => 'Rafael Balacuit',
            'document_type' => 'School ID',
            'original_name' => 'id_front.jpg',
            'stored_path' => "documents/{$grantee->id}/id_front.jpg",
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
            'status' => 'pending_review',
            'risk_level' => 'low',
        ]);

        return [$grantee, $batch];
    }
}
