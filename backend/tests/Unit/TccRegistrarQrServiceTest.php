<?php

namespace Tests\Unit;

use App\Services\TccRegistrarQrService;
use Tests\TestCase;

class TccRegistrarQrServiceTest extends TestCase
{
    public function test_is_valid_accepts_configured_registrar_domains(): void
    {
        $service = new TccRegistrarQrService;

        $this->assertTrue($service->isValid('https://registrar.tcc.edu.ph/verify/abc'));
        $this->assertFalse($service->isValid('https://evil.example.com/fake'));
        $this->assertFalse($service->isValid(null));
    }

    public function test_extract_parses_url_path_student_id(): void
    {
        $service = new TccRegistrarQrService;
        $extracted = $service->extract('https://registrar.tcc.edu.ph/verify/STU-42');

        $this->assertTrue($extracted['parseable']);
        $this->assertSame('url', $extracted['kind']);
        $this->assertSame('registrar.tcc.edu.ph', $extracted['host']);
        $this->assertSame('/verify/STU-42', $extracted['path']);
        $this->assertSame('STU-42', $extracted['student_id']);
        $this->assertSame([], $extracted['query']);
    }

    public function test_extract_parses_query_student_id(): void
    {
        $service = new TccRegistrarQrService;
        $extracted = $service->extract('https://registrar.tcc.edu.ph/verify?sid=STU-1&ay=2026-2027');

        $this->assertTrue($extracted['parseable']);
        $this->assertSame('STU-1', $extracted['student_id']);
        $this->assertSame('2026-2027', $extracted['query']['ay'] ?? null);
        $this->assertSame('STU-1', $extracted['query']['sid'] ?? null);
    }

    public function test_extract_empty_payload(): void
    {
        $service = new TccRegistrarQrService;
        $extracted = $service->extract('');

        $this->assertFalse($extracted['parseable']);
        $this->assertNull($extracted['raw']);
        $this->assertNull($extracted['student_id']);
    }

    public function test_extract_plain_text_is_soft_unparseable(): void
    {
        $service = new TccRegistrarQrService;
        $extracted = $service->extract('plain text token STU-99 embedded');

        $this->assertFalse($extracted['parseable']);
        $this->assertSame('text', $extracted['kind']);
        $this->assertSame('plain text token STU-99 embedded', $extracted['raw']);
        $this->assertSame('STU-99', $extracted['student_id']);
    }
}
