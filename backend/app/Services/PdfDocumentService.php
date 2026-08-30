<?php

namespace App\Services;

use App\Support\OcrServiceRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Course History / Grade Slip PDFs: PyMuPDF text + metadata first.
 * Tesseract (/ocr/pdf) only if the PDF has no useful text layer (scanned rare case).
 * School ID images use IdCardOcrService (Tesseract), not this class.
 *
 * Metadata is always scanned via PdfMetadataService (pdf_metadata.py) even when
 * the large pdf_extract.py JSON payload fails to parse — text and metadata are
 * independent so staff Document Validation still gets creator/producer/dates.
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
     *   pdf_metadata?: array<string, mixed>,
     *   pdf_metadata_analysis?: array<string, mixed>
     * }
     */
    public function process(string $absolutePath, string $originalName = 'document.pdf'): array
    {
        if ($absolutePath === '' || ! is_file($absolutePath)) {
            return [
                'status' => 'skipped',
                'error' => 'PDF file missing',
                'pdf_metadata' => [],
                'pdf_metadata_analysis' => ['suspicious' => false, 'reasons' => [], 'notes' => [], 'fields' => [], 'source' => 'unavailable'],
            ];
        }

        // Metadata first — must not depend on pdf_extract.py JSON succeeding.
        $analysis = $this->pdfMetadata->analyzeFromOcrOrFile([], $absolutePath);
        $metaPayload = is_array($analysis['fields'] ?? null) ? $analysis['fields'] : [];

        $pymupdf = $this->extractWithPyMuPdf($absolutePath);
        $rawText = trim((string) ($pymupdf['combined_text'] ?? $pymupdf['extracted_text'] ?? ''));
        $formatted = trim((string) ($pymupdf['formatted_table_text'] ?? ''));
        $courses = is_array($pymupdf['courses'] ?? null) ? $pymupdf['courses'] : [];
        $terms = is_array($pymupdf['terms'] ?? null) ? $pymupdf['terms'] : [];
        // Prefer aligned course table for staff OCR panel; keep raw text for search/fallback.
        $text = $formatted !== '' ? $formatted : $rawText;
        $hasUsefulText = (bool) ($pymupdf['has_useful_text'] ?? (strlen(preg_replace('/\W+/', '', $rawText !== '' ? $rawText : $text) ?? '') >= 10));

        $extractMeta = is_array($pymupdf['pdf_metadata'] ?? null) ? $pymupdf['pdf_metadata'] : [];
        if ($extractMeta !== []) {
            // Prefer fuller extract metadata (page_count/engine) when text extract succeeded.
            $fromExtract = $this->pdfMetadata->analyzeFromOcrOrFile(
                ['pdf_metadata' => $extractMeta],
                null,
            );
            if (($fromExtract['source'] ?? '') !== 'unavailable') {
                $analysis = $fromExtract;
                $metaPayload = $extractMeta;
            }
        } elseif ($metaPayload === [] && ($analysis['source'] ?? '') === 'unavailable') {
            // Last resort already attempted via analyzeFromOcrOrFile above.
            $metaPayload = is_array($analysis['fields'] ?? null) ? $analysis['fields'] : [];
        }

        if ($hasUsefulText && ($text !== '' || $courses !== [] || $terms !== [])) {
            return [
                'status' => 'ok',
                'provider' => 'pymupdf',
                'method' => 'pymupdf_text_layer',
                'text' => $text,
                'raw_text' => $rawText,
                'formatted_table_text' => $formatted !== '' ? $formatted : null,
                'courses' => $courses,
                'terms' => $terms,
                'pdf_metadata' => $metaPayload !== [] ? $metaPayload : ($analysis['fields'] ?? []),
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
            $finalAnalysis = ($fallbackAnalysis['source'] ?? '') !== 'unavailable' ? $fallbackAnalysis : $analysis;
            $finalMeta = is_array($fallbackAnalysis['fields'] ?? null) && $fallbackAnalysis['fields'] !== []
                ? $fallbackAnalysis['fields']
                : ($metaPayload !== [] ? $metaPayload : ($finalAnalysis['fields'] ?? []));

            return [
                'status' => 'ok',
                'provider' => 'tesseract_fallback',
                'method' => 'tesseract_ocr',
                'text' => $fallback['text'] ?? '',
                'pdf_metadata' => $finalMeta,
                'pdf_metadata_analysis' => $finalAnalysis,
            ];
        }

        return [
            'status' => $fallback['status'] ?? 'failed',
            'provider' => 'pymupdf',
            'method' => 'pymupdf_text_layer',
            'text' => $text,
            'error' => $fallback['error'] ?? 'No useful PDF text layer and Tesseract fallback unavailable',
            'pdf_metadata' => $metaPayload !== [] ? $metaPayload : ($analysis['fields'] ?? []),
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

        $payload = $this->decodeJsonObject(trim($result->output()));
        if (! is_array($payload) || ! ($payload['success'] ?? false)) {
            Log::warning('pdf_extract.failed', [
                'python' => $python,
                'exit' => $result->exitCode(),
                'stderr' => Str::limit(trim($result->errorOutput()), 300),
                'stdout_head' => Str::limit(trim($result->output()), 200),
                'json_error' => json_last_error_msg(),
            ]);

            return [];
        }

        return is_array($payload['result'] ?? null) ? $payload['result'] : [];
    }

    /**
     * Parse PyMuPDF CLI JSON. Windows/PyMuPDF may prepend/append non-JSON noise;
     * isolate the outermost object and tolerate invalid UTF-8 sequences.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $rawOut): ?array
    {
        if ($rawOut === '') {
            return null;
        }

        $candidates = [$rawOut];
        $start = strpos($rawOut, '{');
        $end = strrpos($rawOut, '}');
        if ($start !== false && $end !== false && $end >= $start) {
            $sliced = substr($rawOut, $start, $end - $start + 1);
            if ($sliced !== $rawOut) {
                $candidates[] = $sliced;
            }
        }

        foreach ($candidates as $candidate) {
            $payload = json_decode($candidate, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_array($payload)) {
                return $payload;
            }
        }

        return null;
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
                ->withHeaders(OcrServiceRequest::headers())
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

        foreach ([
            base_path('python/.venv/Scripts/python.exe'),
            base_path('python/.venv/bin/python'),
            base_path('ocr-service/.venv/Scripts/python.exe'),
            base_path('ocr-service/.venv/bin/python'),
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
    }
}
