<?php

namespace Tests\Unit;

use App\Services\IdCardOcrService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdCardOcrServiceTest extends TestCase
{
    #[Test]
    public function extract_text_allow_empty_treats_empty_text_as_success(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/ocr/image' => Http::response([
                'result' => ['cleaned_text' => ''],
            ], 200),
        ]);

        $result = (new IdCardOcrService)->extractTextAllowEmpty(
            UploadedFile::fake()->image('back.jpg'),
        );

        $this->assertSame('', $result['text']);
        $this->assertTrue($result['text_empty']);
        $this->assertSame('local_ocr', $result['provider']);
        $this->assertNotNull($result['warning']);
        $this->assertFalse($result['qr']['found']);
    }

    #[Test]
    public function extract_text_parses_qr_from_local_payload(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/ocr/image' => Http::response([
                'result' => ['cleaned_text' => 'hello'],
                'qr_code' => [
                    'found' => true,
                    'value' => 'https://registrar.tcc.edu.ph/x',
                    'type' => 'QRCODE',
                    'engine' => 'opencv',
                ],
            ], 200),
        ]);

        $result = (new IdCardOcrService)->extractText(
            UploadedFile::fake()->image('front.jpg'),
        );

        $this->assertTrue($result['qr']['found']);
        $this->assertSame('https://registrar.tcc.edu.ph/x', $result['qr']['value']);
        $this->assertSame('opencv', $result['qr']['engine']);
    }

    #[Test]
    public function match_against_expected_maps_failures_to_id_frame(): void
    {
        $match = (new IdCardOcrService)->matchAgainstExpected(
            ['text' => 'No Match Here', 'provider' => 'local_ocr', 'raw' => null],
            ['full_name' => 'Maria Santos', 'student_id' => 'STU-1'],
        );

        $this->assertFalse($match['ok']);
        $this->assertArrayHasKey('id_frame', $match['errors']);
        $this->assertArrayNotHasKey('ocr_name', $match['errors']);
    }

    #[Test]
    public function extract_text_allow_empty_throws_when_service_returns_error(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/ocr/image' => Http::response([
                'error' => ['message' => 'Tesseract OCR is not available.'],
            ], 503),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Local OCR failed: Tesseract OCR is not available.');

        (new IdCardOcrService)->extractTextAllowEmpty(
            UploadedFile::fake()->image('back.jpg'),
        );
    }
}
