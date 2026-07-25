<?php

namespace Tests\Unit;

use App\Services\PdfMetadataAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PdfMetadataAnalyzerTest extends TestCase
{
    #[Test]
    public function it_flags_office_creator_and_modified_date_mismatch(): void
    {
        $analysis = (new PdfMetadataAnalyzer)->analyze([
            'creator' => 'Microsoft Word',
            'producer' => 'Adobe PDF Library 15.0',
            'creationDate' => 'D:20260725195500',
            'modDate' => 'D:20260726000000',
            'encryption' => null,
            'is_encrypted' => false,
        ]);

        $this->assertTrue($analysis['suspicious']);
        $this->assertNotEmpty($analysis['reasons']);
    }

    #[Test]
    public function it_accepts_clean_sis_like_metadata(): void
    {
        $analysis = (new PdfMetadataAnalyzer)->analyze([
            'creator' => 'TCC SIS',
            'producer' => 'TCC Registrar PDF',
            'creationDate' => 'D:20260725195500',
            'modDate' => 'D:20260725195500',
            'encryption' => null,
            'is_encrypted' => false,
        ]);

        $this->assertFalse($analysis['suspicious']);
        $this->assertSame([], $analysis['reasons']);
    }
}
