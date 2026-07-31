<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class PdfMetadataService
{
    public function __construct(
        private readonly PdfMetadataAnalyzer $analyzer,
    ) {}

    /**
     * Prefer OCR-service pdf_metadata; fall back to local PyMuPDF script.
     *
     * @param  array<string, mixed>  $ocrPayload
     * @return array{suspicious: bool, reasons: list<string>, fields: array<string, mixed>, source: string}
     */
    public function analyzeFromOcrOrFile(array $ocrPayload, ?string $absolutePath): array
    {
        $fromOcr = data_get($ocrPayload, 'pdf_metadata');
        if (is_array($fromOcr) && $fromOcr !== []) {
            $analysis = $this->analyzer->analyze($fromOcr);

            return [...$analysis, 'source' => 'ocr_service'];
        }

        if ($absolutePath && is_file($absolutePath)) {
            $local = $this->extractWithPython($absolutePath);
            if ($local !== []) {
                $analysis = $this->analyzer->analyze($local);

                return [...$analysis, 'source' => 'pymupdf_local'];
            }
        }

        return ['suspicious' => false, 'reasons' => [], 'fields' => [], 'source' => 'unavailable'];
    }

    /**
     * @return array<string, mixed>
     */
    public function extractWithPython(string $absolutePath): array
    {
        $python = $this->resolvePythonBinary();
        $script = base_path('python/pdf_metadata.py');
        if (! is_file($script)) {
            return [];
        }

        try {
            $result = Process::timeout(30)->run([$python, $script, $absolutePath]);
        } catch (\Throwable $exception) {
            Log::warning('pdf_metadata.process_failed', ['error' => $exception->getMessage()]);

            return [];
        }

        $payload = json_decode(trim($result->output()), true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (! is_array($payload) || ! ($payload['success'] ?? false)) {
            Log::warning('pdf_metadata.failed', [
                'exit' => $result->exitCode(),
                'stderr' => Str::limit(trim($result->errorOutput()), 300),
                'json_error' => json_last_error_msg(),
            ]);

            return [];
        }

        return is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
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
