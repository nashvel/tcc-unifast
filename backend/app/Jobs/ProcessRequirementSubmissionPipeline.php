<?php

namespace App\Jobs;

use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\SubmissionPipelineResult;
use App\Services\AcademicGradeParser;
use App\Services\GradeslipQrService;
use App\Services\PdfDocumentService;
use App\Services\StaffSubmissionNotifier;
use App\Services\SubmissionRiskScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessRequirementSubmissionPipeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $granteeId,
        public int $batchId,
        public bool $identityFailed = false,
    ) {}

    public function handle(
        SubmissionRiskScoringService $scoring,
        GradeslipQrService $gradeslipQr,
        AcademicGradeParser $gradeParser,
        StaffSubmissionNotifier $staffNotifier,
        PdfDocumentService $pdfDocuments,
    ): void {
        $grantee = Grantee::query()->with('kycProfile')->find($this->granteeId);
        if (! $grantee) {
            return;
        }

        $slots = DocumentSubmission::query()
            ->where('grantee_id', $this->granteeId)
            ->where('batch_id', $this->batchId)
            ->get()
            ->keyBy('slot_key');

        // Course History + Grade Slip: PyMuPDF text/metadata (Tesseract only if no text layer).
        $ocrSummary = [];
        foreach (['course_history', 'grade_slip'] as $slot) {
            $doc = $slots->get($slot);
            if (! $doc) {
                continue;
            }
            $ocrSummary[$slot] = $this->processPdfSlot($doc, $pdfDocuments);
        }

        $gradeSlipDoc = $slots->get('grade_slip');
        $gradeslipQrResult = $gradeSlipDoc
            ? $this->runGradeslipQr($gradeSlipDoc, $gradeslipQr)
            : ['status' => 'skipped', 'success' => false, 'found' => false, 'domain_valid' => false];

        if ($gradeSlipDoc && isset($ocrSummary['grade_slip']) && is_array($ocrSummary['grade_slip'])) {
            $ocrSummary['grade_slip']['gradeslip_qr'] = $gradeslipQrResult;
        }

        $combinedText = collect($ocrSummary)->pluck('text')->filter()->implode("\n");
        $academics = $gradeParser->parse($combinedText, $grantee->program);
        $ocrSummary['_academics'] = $academics;

        // School ID authenticity (Pillow) remains separate from PDF PyMuPDF path.
        $authenticity = $this->runAuthenticityStub($slots->get('school_id'));

        $signals = $scoring->collectSignals(
            identityFailed: $this->identityFailed,
            ocrSummary: $ocrSummary,
            authenticityStatus: $authenticity['status'],
            grantee: $grantee,
            gradeslipQr: $gradeslipQrResult,
        );
        $score = $scoring->score($signals);
        $badge = $scoring->badge($score);
        $eligibility = $scoring->evaluateEligibility($grantee, $ocrSummary);

        if ($eligibility['status'] === 'pass') {
            $grantee->forceFill(['status' => 'eligible'])->save();
        } elseif ($eligibility['status'] === 'fail') {
            $grantee->forceFill(['status' => 'not_eligible'])->save();
        }

        $n8nStatus = $this->triggerN8n($grantee, $score, $badge, $signals, $eligibility);
        $staffNotifier->notifyPipelineComplete($grantee, $this->batchId, $eligibility, $badge);

        $metaNotes = collect($ocrSummary)
            ->filter(fn ($row) => is_array($row) && ! empty($row['pdf_metadata_analysis']['reasons'] ?? []))
            ->flatMap(fn ($row) => $row['pdf_metadata_analysis']['reasons'])
            ->unique()
            ->implode('; ');

        SubmissionPipelineResult::updateOrCreate(
            ['grantee_id' => $this->granteeId, 'batch_id' => $this->batchId],
            [
                'risk_score' => $score,
                'risk_badge' => $badge,
                'signals' => $signals,
                'eligibility' => $eligibility,
                'ocr_summary' => $ocrSummary,
                'n8n_status' => $n8nStatus,
                'authenticity_status' => $authenticity['status'],
                'status' => 'completed',
                'notes' => trim(($authenticity['notes'] ?? '').($metaNotes !== '' ? ' PDF meta: '.$metaNotes : '')) ?: null,
            ],
        );
    }

    /**
     * PyMuPDF-first for SIS PDFs. Tesseract only if no text layer.
     *
     * @return array<string, mixed>
     */
    private function processPdfSlot(DocumentSubmission $doc, PdfDocumentService $pdfDocuments): array
    {
        if (! $doc->stored_path || ! Storage::disk('public')->exists($doc->stored_path)) {
            return [
                'status' => 'skipped',
                'error' => 'PDF file missing',
                'pdf_metadata_analysis' => ['suspicious' => false, 'reasons' => [], 'fields' => [], 'source' => 'unavailable'],
            ];
        }

        $absolute = Storage::disk('public')->path($doc->stored_path);
        $result = $pdfDocuments->process($absolute, $doc->original_name ?: 'document.pdf');

        $meta = is_array($doc->metadata_payload) ? $doc->metadata_payload : [];
        $meta['pdf_document'] = [
            'provider' => $result['provider'] ?? null,
            'method' => $result['method'] ?? null,
            'pdf_metadata_analysis' => $result['pdf_metadata_analysis'] ?? null,
        ];

        $doc->update([
            'extracted_text' => $result['text'] ?? null,
            'ocr_payload' => [
                'engine' => $result['provider'] ?? 'pymupdf',
                'method' => $result['method'] ?? 'pymupdf_text_layer',
                'result' => ['combined_text' => $result['text'] ?? ''],
            ],
            'metadata_payload' => $meta,
            'status' => 'pending_review',
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function runGradeslipQr(DocumentSubmission $doc, GradeslipQrService $gradeslipQr): array
    {
        if (! $doc->stored_path || ! Storage::disk('public')->exists($doc->stored_path)) {
            return [
                'status' => 'skipped',
                'success' => false,
                'found' => false,
                'domain_valid' => false,
                'error' => 'Grade slip file missing',
                'error_code' => 'file_missing',
            ];
        }

        $absolute = Storage::disk('public')->path($doc->stored_path);
        $result = $gradeslipQr->decode($absolute);

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
    private function runAuthenticityStub(?DocumentSubmission $schoolId): array
    {
        $url = trim((string) config('services.identity.authenticity_service_url'));
        if ($url === '') {
            // TODO: Wire Pillow moiré / print-texture analysis when authenticity microservice is deployed.
            return [
                'status' => 'stubbed',
                'notes' => 'Pillow/moire authenticity check stubbed — set AUTHENTICITY_SERVICE_URL to enable.',
            ];
        }

        if (! $schoolId?->stored_path) {
            return ['status' => 'skipped', 'notes' => 'No school ID image for authenticity analysis.'];
        }

        try {
            $absolute = Storage::disk('public')->path($schoolId->stored_path);
            $response = Http::timeout(30)
                ->attach('file', fopen($absolute, 'r'), 'id_scan.jpg')
                ->post($url);
            if ($response->failed()) {
                return ['status' => 'failed', 'notes' => 'Authenticity service returned an error.'];
            }

            return ['status' => (string) data_get($response->json(), 'status', 'ok'), 'notes' => (string) data_get($response->json(), 'message')];
        } catch (\Throwable $exception) {
            return ['status' => 'failed', 'notes' => $exception->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $signals
     * @param  array<string, mixed>  $eligibility
     */
    private function triggerN8n(Grantee $grantee, int $score, string $badge, array $signals, array $eligibility): string
    {
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
                    'batch_id' => $this->batchId,
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
