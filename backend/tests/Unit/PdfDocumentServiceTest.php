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
        $this->assertIsArray($result['pdf_metadata_analysis'] ?? null);
        $this->assertNotSame('unavailable', $result['pdf_metadata_analysis']['source'] ?? 'unavailable');
    }
}
