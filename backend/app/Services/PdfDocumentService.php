<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Course History / Grade Slip PDFs: PyMuPDF text + metadata first.
 * Tesseract (/ocr/pdf) only if the PDF has no useful text layer (scanned rare case).
 * School ID images use IdCardOcrService (Tesseract), not this class.
 */
class PdfDocumentService
{
    public function __construct(
        private readonly PdfMetadataService $pdfMetadata,
    ) {}

    /**
     * @return array{
     *   status: string,
     *   text?: string|null,
     *   provider?: string,
     *   method?: string,
     *   error?: string,
     *   pdf_metadata_analysis?: array<string, mixed>
     * }
     */
    public function process(string $absolutePath, string $originalName = 'document.pdf'): array
    {
        if ($absolutePath === '' || ! is_file($absolutePath)) {
            return [
                'status' => 'skipped',
                'error' => 'PDF file missing',
                'pdf_metadata_analysis' => ['suspicious' => false, 'reasons' => [], 'fields' => [], 'source' => 'unavailable'],
            ];
        }

        $pymupdf = $this->extractWithPyMuPdf($absolutePath);
        $text = trim((string) ($pymupdf['combined_text'] ?? $pymupdf['extracted_text'] ?? ''));
        $hasUsefulText = (bool) ($pymupdf['has_useful_text'] ?? (strlen(preg_replace('/\W+/', '', $text) ?? '') >= 10));
        $metaPayload = is_array($pymupdf['pdf_metadata'] ?? null) ? $pymupdf['pdf_metadata'] : [];
        $analysis = $this->pdfMetadata->analyzeFromOcrOrFile(
            $metaPayload !== [] ? ['pdf_metadata' => $metaPayload] : [],
            $absolutePath,
        );

        if ($hasUsefulText && $text !== '') {
            return [
                'status' => 'ok',
                'provider' => 'pymupdf',
                'method' => 'pymupdf_text_layer',
                'text' => $text,
                'pdf_metadata_analysis' => $analysis,
            ];
        }

        // Rare: scanned PDF with no text layer → Tesseract via OCR service.
        $fallback = $this->tesseractFallback($absolutePath, $originalName);
        if (($fallback['status'] ?? '') === 'ok') {
            $fallbackAnalysis = $this->pdfMetadata->analyzeFromOcrOrFile(
                $fallback['ocr_payload'] ?? [],
                $absolutePath,
            );

            return [
                'status' => 'ok',
                'provider' => 'tesseract_fallback',
                'method' => 'tesseract_ocr',
                'text' => $fallback['text'] ?? '',
                'pdf_metadata_analysis' => $fallbackAnalysis['source'] !== 'unavailable' ? $fallbackAnalysis : $analysis,
            ];
        }

        return [
            'status' => $fallback['status'] ?? 'failed',
            'provider' => 'pymupdf',
            'method' => 'pymupdf_text_layer',
            'text' => $text,
            'error' => $fallback['error'] ?? 'No useful PDF text layer and Tesseract fallback unavailable',
            'pdf_metadata_analysis' => $analysis,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractWithPyMuPdf(string $absolutePath): array
    {
        $python = $this->resolvePythonBinary();
        $script = base_path('python/pdf_extract.py');
        if (! is_file($script)) {
            return [];
        }

        try {
            $result = Process::timeout((int) config('services.ocr.timeout', 120))
                ->run([$python, $script, $absolutePath]);
        } catch (\Throwable $exception) {
            Log::warning('pdf_extract.process_failed', ['error' => $exception->getMessage()]);

            return [];
        }

        $payload = json_decode(trim($result->output()), true);
        if (! is_array($payload) || ! ($payload['success'] ?? false)) {
            Log::warning('pdf_extract.failed', [
                'exit' => $result->exitCode(),
                'stderr' => Str::limit(trim($result->errorOutput()), 300),
            ]);

            return [];
        }

        return is_array($payload['result'] ?? null) ? $payload['result'] : [];
    }

    /**
     * @return array{status: string, text?: string, error?: string, ocr_payload?: array<string, mixed>}
     */
    private function tesseractFallback(string $absolutePath, string $originalName): array
    {
        $baseUrl = rtrim((string) config('services.ocr.url'), '/');
        if ($baseUrl === '') {
            return ['status' => 'skipped', 'error' => 'OCR service not configured for scanned-PDF fallback'];
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.ocr.timeout', 120))
                ->attach('file', fopen($absolutePath, 'r'), $originalName)
                ->post($baseUrl.'/ocr/pdf');
        } catch (ConnectionException $exception) {
            return ['status' => 'unavailable', 'error' => $exception->getMessage()];
        }

        if ($response->failed()) {
            return [
                'status' => 'failed',
                'error' => (string) data_get($response->json(), 'error.message', 'Tesseract PDF fallback failed'),
            ];
        }

        $payload = $response->json() ?? [];

        return [
            'status' => 'ok',
            'text' => (string) data_get($payload, 'result.combined_text', ''),
            'ocr_payload' => $payload,
        ];
    }

    private function resolvePythonBinary(): string
    {
        $configured = trim((string) config('services.gradeslip_qr.python', ''));
        if ($configured !== '') {
            return $configured;
        }

        $venv = base_path('python/.venv/Scripts/python.exe');
        if (is_file($venv)) {
            return $venv;
        }

        $ocrVenv = base_path('ocr-service/.venv/Scripts/python.exe');
        if (is_file($ocrVenv)) {
            return $ocrVenv;
        }

        return 'python';
    }
}
