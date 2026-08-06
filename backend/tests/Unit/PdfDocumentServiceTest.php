<?php

namespace Tests\Unit;

use App\Services\PdfDocumentService;
use App\Services\PdfMetadataAnalyzer;
use App\Services\PdfMetadataService;
use Tests\TestCase;

class PdfDocumentServiceTest extends TestCase
{
    public function test_pymupdf_extracts_text_and_metadata_from_pdf(): void
    {
        $venv = base_path('python/.venv/Scripts/python.exe');
        if (! is_file($venv) && ! is_file(base_path('python/.venv/bin/python'))) {
            $this->markTestSkipped('Python venv with PyMuPDF is not installed under backend/python/.venv');
        }

        $path = storage_path('framework/testing/pdf_extract_sample.pdf');
        @mkdir(dirname($path), 0777, true);
        // Minimal PDF with a text operator so PyMuPDF can extract content.
        $pdf = <<<'PDF'
%PDF-1.4
1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj
2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj
3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R /Resources<< /Font<< /F1 5 0 R >> >> >>endobj
4 0 obj<< /Length 55 >>stream
BT /F1 24 Tf 50 70 Td (Student Name: Maria Santos) Tj ET
endstream
endobj
5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj
trailer<< /Root 1 0 R >>
%%EOF
PDF;
        file_put_contents($path, $pdf);

        $service = new PdfDocumentService(new PdfMetadataService(new PdfMetadataAnalyzer));
        $result = $service->process($path, 'sample.pdf');

        @unlink($path);

        $this->assertSame('ok', $result['status']);
        $this->assertSame('pymupdf', $result['provider']);
        $this->assertNotEmpty($result['text'] ?? '');
        $this->assertIsArray($result['pdf_metadata'] ?? null);
        $this->assertNotEmpty($result['pdf_metadata'] ?? []);
        $this->assertIsArray($result['pdf_metadata_analysis'] ?? null);
        $this->assertNotSame('unavailable', $result['pdf_metadata_analysis']['source'] ?? 'unavailable');
        $this->assertArrayHasKey('page_count', $result['pdf_metadata']);
        $this->assertArrayHasKey('engine', $result['pdf_metadata']);
    }

    public function test_metadata_is_scanned_even_when_extract_script_missing(): void
    {
        $venv = base_path('python/.venv/Scripts/python.exe');
        if (! is_file($venv) && ! is_file(base_path('python/.venv/bin/python'))) {
            $this->markTestSkipped('Python venv with PyMuPDF is not installed under backend/python/.venv');
        }

        $path = storage_path('framework/testing/pdf_metadata_only_sample.pdf');
        @mkdir(dirname($path), 0777, true);
        $pdf = <<<'PDF'
%PDF-1.4
1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj
2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj
3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R /Resources<< /Font<< /F1 5 0 R >> >> >>endobj
4 0 obj<< /Length 44 >>stream
BT /F1 12 Tf 40 100 Td (Hello World) Tj ET
endstream
endobj
5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj
trailer<< /Root 1 0 R /Info 6 0 R >>
6 0 obj<< /Title (Meta Only) /Creator (Microsoft Word) /Producer (Adobe PDF Library) /CreationDate (D:20240101120000) /ModDate (D:20240102120000) >>endobj
%%EOF
PDF;
        file_put_contents($path, $pdf);

        $service = new PdfDocumentService(new PdfMetadataService(new PdfMetadataAnalyzer));
        $result = $service->process($path, 'meta-only.pdf');

        @unlink($path);

        $this->assertIsArray($result['pdf_metadata_analysis'] ?? null);
        $this->assertNotSame('unavailable', $result['pdf_metadata_analysis']['source'] ?? 'unavailable');
        $this->assertTrue($result['pdf_metadata_analysis']['suspicious'] ?? false);
        $this->assertNotEmpty($result['pdf_metadata']['creator'] ?? null);
    }

    public function test_pymupdf_keeps_summer_and_next_1st_as_separate_terms(): void
    {
        $venv = base_path('python/.venv/Scripts/python.exe');
        if (! is_file($venv) && ! is_file(base_path('python/.venv/bin/python'))) {
            $this->markTestSkipped('Python venv with PyMuPDF is not installed under backend/python/.venv');
        }

        $path = base_path('tests/fixtures/ch_brandon_page2_summer_then_1st.pdf');
        if (! is_file($path)) {
            $this->markTestSkipped('Fixture missing: tests/fixtures/ch_brandon_page2_summer_then_1st.pdf');
        }

        $service = new PdfDocumentService(new PdfMetadataService(new PdfMetadataAnalyzer));
        $result = $service->process($path, 'ch_brandon_page2.pdf');

        $this->assertSame('ok', $result['status']);
        $terms = $result['terms'] ?? null;
        $this->assertIsArray($terms);
        $this->assertCount(2, $terms, 'Summer and following 1st must stay separate term blocks');

        $byTerm = [];
        foreach ($terms as $term) {
            $label = (string) ($term['academic_term'] ?? '');
            $byTerm[$label] = array_values(array_map(
                static fn (array $c): string => (string) ($c['code'] ?? ''),
                is_array($term['courses'] ?? null) ? $term['courses'] : [],
            ));
        }

        $this->assertArrayHasKey('2025-2026 Summer', $byTerm);
        $this->assertArrayHasKey('2026-2027 1st', $byTerm);
        $this->assertSame(['IT Elec 4', 'IT Elec 5', 'IT Elec 6'], $byTerm['2025-2026 Summer']);
        $this->assertSame(['IT 128', 'IT 129', 'IT 130', 'IT 131', 'IT 132'], $byTerm['2026-2027 1st']);

        // Flat courses[] must carry academic_term so UI/parser can recover if needed.
        $courses = $result['courses'] ?? [];
        $this->assertIsArray($courses);
        $summerCodes = [];
        $firstCodes = [];
        foreach ($courses as $course) {
            $term = (string) ($course['academic_term'] ?? '');
            $code = (string) ($course['code'] ?? '');
            if ($term === '2025-2026 Summer') {
                $summerCodes[] = $code;
            }
            if ($term === '2026-2027 1st') {
                $firstCodes[] = $code;
            }
        }
        $this->assertSame(['IT Elec 4', 'IT Elec 5', 'IT Elec 6'], $summerCodes);
        $this->assertSame(['IT 128', 'IT 129', 'IT 130', 'IT 131', 'IT 132'], $firstCodes);
    }
}
