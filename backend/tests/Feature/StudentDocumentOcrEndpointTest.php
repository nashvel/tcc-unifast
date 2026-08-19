<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Grantee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentDocumentOcrEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for isolated persistence tests.');
        }
    }

    public function test_it_proxies_image_submissions_to_python_ocr(): void
    {
        config()->set('services.ocr', ['url' => 'http://ocr.test', 'timeout' => 30]);
        Storage::fake('public');

        $student = $this->activeStudent('OCR-IMG');

        Http::fake(['ocr.test/ocr/image' => Http::response(['success' => true, 'document_type' => 'image', 'engine' => 'tesseract', 'result' => ['cleaned_text' => 'Student ID TCC-001', 'average_confidence' => 91.2]])]);
        $this->actingAs($student)->post('/api/student/submissions/ocr', ['document_type' => 'Course History', 'file' => UploadedFile::fake()->create('course-history.jpg', 100, 'image/jpeg')])
            ->assertOk()->assertJsonPath('ocr.result.cleaned_text', 'Student ID TCC-001');
        Http::assertSent(fn ($request) => $request->url() === 'http://ocr.test/ocr/image');
    }

    public function test_it_proxies_pdf_submissions_to_python_ocr(): void
    {
        config()->set('services.ocr', ['url' => 'http://ocr.test', 'timeout' => 30]);
        Storage::fake('public');

        $student = $this->activeStudent('OCR-PDF');

        Http::fake(['ocr.test/ocr/pdf' => Http::response(['success' => true, 'document_type' => 'pdf', 'engine' => 'tesseract', 'result' => ['combined_text' => 'COR']])]);
        $this->actingAs($student)->post('/api/student/submissions/ocr', ['document_type' => 'COR', 'file' => UploadedFile::fake()->create('cor.pdf', 100, 'application/pdf')])
            ->assertOk()->assertJsonPath('ocr.document_type', 'pdf');
    }

    public function test_it_rejects_unsupported_files(): void
    {
        Storage::fake('public');

        $student = $this->activeStudent('OCR-BAD');

        Http::fake();
        $this->actingAs($student)->post('/api/student/submissions/ocr', ['document_type' => 'Course History', 'file' => UploadedFile::fake()->create('unsupported-file.exe', 10, 'application/octet-stream')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        Http::assertNothingSent();
    }

    private function activeStudent(string $studentId): User
    {
        $student = User::factory()->create([
            'name' => 'OCR Student',
            'role' => 'student',
            'student_id' => $studentId,
            'account_status' => 'active',
        ]);

        $batch = Batch::create([
            'name' => 'Active OCR Batch',
            'academic_year' => '2026-2027',
            'semester' => '1st Semester',
            'status' => 'open',
            'is_active' => true,
            'submission_deadline' => now()->addWeek(),
        ]);

        Grantee::create([
            'user_id' => $student->id,
            'batch_id' => $batch->id,
            'student_id' => $studentId,
            'student_number' => $studentId,
            'full_name' => $student->name,
            'email' => $student->email,
            'program' => 'BSIT',
            'year_level' => '1st Year',
            'status' => 'verified',
        ]);

        return $student;
    }
}
