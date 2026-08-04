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
        $this->assertStringContainsString('Front of School ID', $match['errors']['id_frame']);
        $this->assertStringContainsString('OCR saw:', $match['errors']['id_frame']);
    }

    #[Test]
    public function match_accepts_fuzzy_name_when_student_id_matches(): void
    {
        $match = (new IdCardOcrService)->matchAgainstExpected(
            [
                'text' => "RAFAEL BALACU1T\nStudent No. 20231909\nBSIT",
                'provider' => 'local_ocr',
                'raw' => null,
            ],
            ['full_name' => 'Rafael Balacuit', 'student_id' => '20231909'],
        );

        $this->assertTrue($match['ok'], json_encode($match['errors']));
        $this->assertSame('Rafael Balacuit', $match['extracted_name']);
        $this->assertSame('20231909', $match['extracted_student_id']);
    }

    #[Test]
    public function match_accepts_single_name_token_when_student_id_matches(): void
    {
        $cases = [
            "Lio fia SiN Bana junk\nID Number:20231909\nBirthday fragments\nBALACUIT",
            "aa oN a , | RAFAEL , yt j*\n| O Number 20231909 Ra",
            "xBALACUITy\nStudent No. 2023 1909",
        ];

        foreach ($cases as $text) {
            $match = (new IdCardOcrService)->matchAgainstExpected(
                ['text' => $text, 'provider' => 'local_ocr', 'raw' => null],
                ['full_name' => 'Rafael Balacuit', 'student_id' => '20231909'],
            );

            $this->assertTrue($match['ok'], 'Failed for: '.$text.' → '.json_encode($match['errors']));
            $this->assertSame('Rafael Balacuit', $match['extracted_name']);
            $this->assertSame('20231909', $match['extracted_student_id']);
        }
    }

    #[Test]
    public function match_rejects_total_name_garbage_even_when_student_id_matches(): void
    {
        $match = (new IdCardOcrService)->matchAgainstExpected(
            [
                'text' => "Lio fia SiN Bana junk glare OCR\nID Number:20231909\nBirthday 01/01/2000",
                'provider' => 'local_ocr',
                'raw' => null,
            ],
            ['full_name' => 'Rafael Balacuit', 'student_id' => '20231909'],
        );

        $this->assertFalse($match['ok']);
        $this->assertArrayHasKey('id_frame', $match['errors']);
        $this->assertStringContainsString('name is unreadable', $match['errors']['id_frame']);
        $this->assertStringContainsString('student ID matches', $match['errors']['id_frame']);
        $this->assertStringContainsString('OCR saw:', $match['errors']['id_frame']);
        $this->assertStringNotContainsString('OCR name does not match', $match['errors']['id_frame']);
    }

    #[Test]
    public function match_rejects_fuzzy_name_when_student_id_wrong(): void
    {
        $match = (new IdCardOcrService)->matchAgainstExpected(
            [
                'text' => "RAFAEL BALACU1T\nStudent No. 99999999\nBSIT",
                'provider' => 'local_ocr',
                'raw' => null,
            ],
            ['full_name' => 'Rafael Balacuit', 'student_id' => '20231909'],
        );

        $this->assertFalse($match['ok']);
        $this->assertArrayHasKey('id_frame', $match['errors']);
        $this->assertStringContainsString('Student ID', $match['errors']['id_frame']);
        // Fuzzy name must not apply without ID hard-match.
        $this->assertStringContainsString('OCR name does not match', $match['errors']['id_frame']);
        $this->assertStringContainsString('OCR saw:', $match['errors']['id_frame']);
        $this->assertStringContainsString('Expected student ID 20231909', $match['errors']['id_frame']);
    }

    #[Test]
    public function match_accepts_labeled_id_number_line_with_name(): void
    {
        $match = (new IdCardOcrService)->matchAgainstExpected(
            [
                'text' => "RAFAEL BALACUIT\nID Number: 20231909\nBSIT",
                'provider' => 'local_ocr',
                'raw' => null,
            ],
            ['full_name' => 'Rafael Balacuit', 'student_id' => '20231909'],
        );

        $this->assertTrue($match['ok'], json_encode($match['errors']));
        $this->assertSame('Rafael Balacuit', $match['extracted_name']);
        $this->assertSame('20231909', $match['extracted_student_id']);
    }

    #[Test]
    public function match_accepts_student_id_with_ocr_digit_spacing_and_letter_confusions(): void
    {
        $cases = [
            "RAFAEL BALACUIT\nStudent No. 2023 1909\nBSIT",
            "RAFAEL BALACUIT\nStudent No. 2023-1909\nBSIT",
            "RAFAEL BALACUIT\nStudent No. 20 23 19 09\nBSIT",
            "RAFAEL BALACUIT\nStudent No. 2O231909\nBSIT",
            "RAFAEL BALACUIT\nStudent No. 202S1909\nBSIT",
            "RAFAEL BALACUIT\nO Nuymbee202S 190 9\nBSIT",
            "aa oN a\n, 202371\nV4), | RAFAEL BALACUIT, yt j*\n| O Nuymbee202S 190 9 Ra",
        ];

        foreach ($cases as $text) {
            $match = (new IdCardOcrService)->matchAgainstExpected(
                ['text' => $text, 'provider' => 'local_ocr', 'raw' => null],
                ['full_name' => 'Rafael Balacuit', 'student_id' => '20231909'],
            );

            $this->assertTrue($match['ok'], 'Failed for: '.$text.' → '.json_encode($match['errors']));
            $this->assertSame('20231909', $match['extracted_student_id']);
        }
    }

    #[Test]
    public function match_accepts_single_digit_ocr_slip_when_name_matches(): void
    {
        $match = (new IdCardOcrService)->matchAgainstExpected(
            [
                'text' => "RAFAEL BALACUIT\nStudent No. 20231908\nBSIT",
                'provider' => 'local_ocr',
                'raw' => null,
            ],
            ['full_name' => 'Rafael Balacuit', 'student_id' => '20231909'],
        );

        $this->assertTrue($match['ok'], json_encode($match['errors']));
        $this->assertSame('20231909', $match['extracted_student_id']);
    }

    #[Test]
    public function match_rejects_unrelated_student_id_even_when_name_matches(): void
    {
        $match = (new IdCardOcrService)->matchAgainstExpected(
            [
                'text' => "RAFAEL BALACUIT\nStudent No. 99999999\nBSIT",
                'provider' => 'local_ocr',
                'raw' => null,
            ],
            ['full_name' => 'Rafael Balacuit', 'student_id' => '20231909'],
        );

        $this->assertFalse($match['ok']);
        $this->assertStringContainsString('name matches KYC / masterlist', $match['errors']['id_frame']);
        $this->assertStringContainsString('OCR student ID does not match', $match['errors']['id_frame']);
        $this->assertStringContainsString('Expected student ID 20231909', $match['errors']['id_frame']);
        $this->assertStringContainsString('OCR read', $match['errors']['id_frame']);
        $this->assertStringNotContainsString('program', strtolower($match['errors']['id_frame']));
    }

    #[Test]
    public function parse_back_fields_extracts_sy_contact_and_phone(): void
    {
        $fields = (new IdCardOcrService)->parseBackFields(
            "School Year: 2025-2026\nEmergency Contact: Ana Reyes\nRelationship: Guardian\nContact: +63 917 123 4567"
        );

        $this->assertSame('2025-2026', $fields['school_year']);
        $this->assertSame('Ana Reyes', $fields['emergency_contact_name']);
        $this->assertSame('Guardian', $fields['emergency_contact_relationship']);
        $this->assertNotEmpty($fields['emergency_contact_phone']);
    }

    #[Test]
    public function parse_back_fields_returns_nulls_for_empty_text(): void
    {
        $fields = (new IdCardOcrService)->parseBackFields('');

        $this->assertNull($fields['school_year']);
        $this->assertNull($fields['emergency_contact_name']);
        $this->assertNull($fields['emergency_contact_relationship']);
        $this->assertNull($fields['emergency_contact_phone']);
    }

    #[Test]
    public function normalize_school_year_handles_prefixes_and_short_end(): void
    {
        $ocr = new IdCardOcrService;

        $this->assertSame('2026-2027', $ocr->normalizeSchoolYear('SY 2026-2027'));
        $this->assertSame('2026-2027', $ocr->normalizeSchoolYear('2026–27'));
        $this->assertSame('2026-2027', $ocr->normalizeSchoolYear('2026/2027'));
        $this->assertNull($ocr->normalizeSchoolYear(''));
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
