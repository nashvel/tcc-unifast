<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StudentDocumentOcrEndpointTest extends TestCase
{
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
        Http::fake(['ocr.test/ocr/image' => Http::response(['success' => true, 'document_type' => 'image', 'engine' => 'tesseract', 'result' => ['cleaned_text' => 'Student ID TCC-001', 'average_confidence' => 91.2]])]);
        $this->post('/api/student/submissions/ocr', ['document_type' => 'Course History', 'file' => UploadedFile::fake()->create('course-history.jpg', 100, 'image/jpeg')])
            ->assertOk()->assertJsonPath('ocr.result.cleaned_text', 'Student ID TCC-001');
        Http::assertSent(fn ($request) => $request->url() === 'http://ocr.test/ocr/image');
    }

    public function test_it_proxies_pdf_submissions_to_python_ocr(): void
    {
        config()->set('services.ocr', ['url' => 'http://ocr.test', 'timeout' => 30]);
        Http::fake(['ocr.test/ocr/pdf' => Http::response(['success' => true, 'document_type' => 'pdf', 'engine' => 'tesseract', 'result' => ['combined_text' => 'COR']])]);
        $this->post('/api/student/submissions/ocr', ['document_type' => 'COR', 'file' => UploadedFile::fake()->create('cor.pdf', 100, 'application/pdf')])
            ->assertOk()->assertJsonPath('ocr.document_type', 'pdf');
    }

    public function test_it_rejects_unsupported_files(): void
    {
        Http::fake();
        $this->post('/api/student/submissions/ocr', ['document_type' => 'Course History', 'file' => UploadedFile::fake()->create('unsupported-file.exe', 10, 'application/octet-stream')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        Http::assertNothingSent();
    }
}
