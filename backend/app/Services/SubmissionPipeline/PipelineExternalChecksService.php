<?php

namespace App\Services\SubmissionPipeline;

use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Services\GradeslipQrService;
use App\Support\VaultFileStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PipelineExternalChecksService
{
    public function __construct(
        private readonly GradeslipQrService $gradeslipQr,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function runGradeslipQr(DocumentSubmission $doc): array
    {
        if (! $doc->stored_path || ! VaultFileStorage::exists($doc->stored_path)) {
            return [
                'status' => 'skipped',
                'success' => false,
                'found' => false,
                'domain_valid' => false,
                'error' => 'Grade slip file missing',
                'error_code' => 'file_missing',
            ];
        }

        $absolute = VaultFileStorage::absolutePath($doc->stored_path);
        $result = $this->gradeslipQr->decode($absolute);

        // Refresh first so QR merge never wipes pdf_document / pdf_metadata.
        $doc->refresh();
        $meta = is_array($doc->metadata_payload) ? $doc->metadata_payload : [];
        $meta['gradeslip_qr'] = [
            'status' => $result['status'] ?? null,
            'success' => $result['success'] ?? false,
            'found' => $result['found'] ?? false,
            'domain_valid' => $result['domain_valid'] ?? false,
            'raw_payload' => $result['raw_payload'] ?? null,
            'parsed_fields' => $result['parsed_fields'] ?? [],
            'error' => $result['error'] ?? null,
            'error_code' => $result['error_code'] ?? null,
            'engine' => $result['engine'] ?? 'pyzbar',
        ];
        $doc->update(['metadata_payload' => $meta]);

        return $result;
    }

    /**
     * @return array{status: string, notes?: string}
     */
    public function runAuthenticityCheck(?DocumentSubmission $schoolId): array
    {
        $url = trim((string) config('services.identity.authenticity_service_url'));
        if ($url === '') {
            return ['status' => 'disabled'];
        }

        if (! $schoolId?->stored_path) {
            return ['status' => 'skipped'];
        }

        try {
            $absolute = VaultFileStorage::absolutePath($schoolId->stored_path);
            $response = Http::timeout(30)
                ->attach('file', fopen($absolute, 'r'), 'id_scan.jpg')
                ->post($url);
            if ($response->failed()) {
                return ['status' => 'failed', 'notes' => 'Authenticity service returned an error.'];
            }

            return [
                'status' => (string) data_get($response->json(), 'status', 'ok'),
                'notes' => (string) data_get($response->json(), 'message'),
            ];
        } catch (\Throwable $exception) {
            return ['status' => 'failed', 'notes' => $exception->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $signals
     * @param  array<string, mixed>  $eligibility
     */
    public function triggerN8n(
        Grantee $grantee,
        int $batchId,
        int $score,
        string $badge,
        array $signals,
        array $eligibility,
    ): string {
        $webhook = trim((string) config('services.tcc_unifast_n8n.webhook_url'));
        if ($webhook === '') {
            return 'skipped_no_webhook';
        }

        try {
            $headers = [];
            $headerName = (string) config('services.tcc_unifast_n8n.webhook_header', 'X-TCC-UniFAST-Key');
            $secret = (string) config('services.tcc_unifast_n8n.webhook_secret');
            if ($secret !== '') {
                $headers[$headerName] = $secret;
            }

            $response = Http::withHeaders($headers)
                ->timeout((int) config('services.tcc_unifast_n8n.timeout', 15))
                ->post($webhook, [
                    'event' => 'requirement_submission_confirmed',
                    'grantee_id' => $grantee->id,
                    'batch_id' => $batchId,
                    'student_id' => $grantee->student_id,
                    'risk_score' => $score,
                    'risk_badge' => $badge,
                    'signals' => $signals,
                    'eligibility' => $eligibility,
                ]);

            return $response->successful() ? 'sent' : 'failed_'.$response->status();
        } catch (\Throwable $exception) {
            Log::warning('submission_pipeline.n8n_failed', ['error' => $exception->getMessage()]);

            return 'failed';
        }
    }
}
