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
use App\Support\VaultFileStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $combinedText = collect($ocrSummary)
            ->filter(fn ($row) => is_array($row))
            ->map(fn ($row) => (string) ($row['raw_text'] ?? $row['text'] ?? ''))
            ->filter()
            ->implode("\n");
        // Prefer Course History (full history) for retention; fall back to grade_slip.
        $retentionCourses = [];
        $retentionTerms = [];
        $retentionSlot = null;
        foreach (['course_history', 'grade_slip'] as $slot) {
            $slotCourses = $ocrSummary[$slot]['courses'] ?? null;
            $slotTerms = $ocrSummary[$slot]['terms'] ?? null;
            $normalizedCourses = is_array($slotCourses)
                ? array_values(array_filter($slotCourses, 'is_array'))
                : [];
            $normalizedTerms = is_array($slotTerms)
                ? array_values(array_filter($slotTerms, 'is_array'))
                : [];
            if ($normalizedCourses !== [] || $normalizedTerms !== []) {
                $retentionCourses = $normalizedCourses;
                $retentionTerms = $normalizedTerms;
                $retentionSlot = $slot;
                break;
            }
        }
        $academics = $gradeParser->parse(
            $combinedText,
            $grantee->program,
            $retentionCourses !== [] ? $retentionCourses : null,
            $retentionSlot,
            $retentionTerms !== [] ? $retentionTerms : null,
        );
        $ocrSummary['_academics'] = $academics;

        // Pillow moiré authenticity is disabled until the microservice is ready.
        $authenticity = $this->runAuthenticityCheck($slots->get('school_id'));

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
        if (! $doc->stored_path || ! VaultFileStorage::exists($doc->stored_path)) {
            return [
                'status' => 'skipped',
                'error' => 'PDF file missing',
                'pdf_metadata_analysis' => ['suspicious' => false, 'reasons' => [], 'fields' => [], 'source' => 'unavailable'],
            ];
        }

        $absolute = VaultFileStorage::absolutePath($doc->stored_path);
        $result = $pdfDocuments->process($absolute, $doc->original_name ?: 'document.pdf');

        $meta = is_array($doc->metadata_payload) ? $doc->metadata_payload : [];
        $analysis = is_array($result['pdf_metadata_analysis'] ?? null) ? $result['pdf_metadata_analysis'] : null;
        $rawMeta = is_array($result['pdf_metadata'] ?? null) ? $result['pdf_metadata'] : null;
        if ($rawMeta === null && is_array($analysis['fields'] ?? null)) {
            $rawMeta = $analysis['fields'];
        }
        $courses = is_array($result['courses'] ?? null) ? $result['courses'] : [];
        $terms = is_array($result['terms'] ?? null) ? $result['terms'] : [];
        $slotKey = is_string($doc->slot_key) ? $doc->slot_key : null;
        $gradeSummary = (new AcademicGradeParser)->parse(
            (string) ($result['raw_text'] ?? $result['text'] ?? ''),
            null,
            $courses !== [] ? $courses : null,
            $slotKey,
            $terms !== [] ? $terms : null,
        );

        $meta['pdf_document'] = [
            'provider' => $result['provider'] ?? null,
            'method' => $result['method'] ?? null,
            'status' => $result['status'] ?? null,
            'pdf_metadata' => $rawMeta,
            'pdf_metadata_analysis' => $analysis,
        ];
        $maxFailed = \App\Models\PolicySetting::maxFailedSubjects();
        $retention = (int) $gradeSummary['retention_count'];
        $pendingCount = (int) ($gradeSummary['pending_count'] ?? 0);
        $blanksAsDropped = $slotKey === 'course_history';
        $meta['grade_summary'] = [
            'blank_count' => $gradeSummary['blank_count'],
            'pending_count' => $pendingCount,
            'failed_count' => $gradeSummary['failed_count'],
            'dropped_count' => $gradeSummary['dropped_count'],
            'numeric_failed_count' => $gradeSummary['numeric_failed_count'] ?? 0,
            'retention_count' => $retention,
            'pass_grade' => $gradeSummary['pass_grade'],
            'program_code' => $gradeSummary['program_code'],
            'max_failed' => $maxFailed,
            'document_type' => $slotKey,
            'blanks_count_as_fails' => false,
            'blanks_count_as_dropped' => $blanksAsDropped,
            'pending_term_window' => AcademicGradeParser::PENDING_TERM_WINDOW,
            'over_limit' => $retention >= $maxFailed,
            'term_count' => is_array($gradeSummary['terms'] ?? null) ? count($gradeSummary['terms']) : 0,
            'message' => $retention >= $maxFailed
                ? sprintf(
                    'Not eligible: %d failed + %d dropped = %d (max %d).%s',
                    $gradeSummary['failed_count'],
                    $gradeSummary['dropped_count'],
                    $retention,
                    $maxFailed,
                    $blanksAsDropped
                        ? ($pendingCount > 0
                            ? sprintf(' %d pending blank(s) ignored; older-term blanks count as dropped.', $pendingCount)
                            : ' Older-term Course History blanks counted as dropped; recent blanks are pending.')
                        : ' Grade-slip blanks ignored for eligibility.',
                )
                : ($pendingCount > 0
                    ? sprintf('%d pending grade(s) noted (not counted toward retention).', $pendingCount)
                    : null),
        ];

        // Prefer normalized course rows (blank grade cells preserved) for staff OCR table.
        $coursesForUi = is_array($gradeSummary['courses'] ?? null) && $gradeSummary['courses'] !== []
            ? $gradeSummary['courses']
            : $courses;
        $termsForUi = is_array($gradeSummary['terms'] ?? null) && $gradeSummary['terms'] !== []
            ? $gradeSummary['terms']
            : $terms;

        $displayText = $result['formatted_table_text']
            ?? $result['text']
            ?? $result['raw_text']
            ?? null;

        $doc->update([
            'extracted_text' => $displayText,
            'ocr_payload' => [
                'engine' => $result['provider'] ?? 'pymupdf',
                'method' => $result['method'] ?? 'pymupdf_text_layer',
                'result' => [
                    'combined_text' => $result['raw_text'] ?? $result['text'] ?? '',
                    'formatted_table_text' => $result['formatted_table_text'] ?? null,
                    'courses' => $coursesForUi,
                    'terms' => $termsForUi,
                    'grade_summary' => $meta['grade_summary'],
                ],
            ],
            'metadata_payload' => $meta,
            'status' => 'pending_review',
        ]);

        $result['courses'] = $coursesForUi;
        $result['terms'] = $termsForUi;
        $result['grade_summary'] = $meta['grade_summary'];

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function runGradeslipQr(DocumentSubmission $doc, GradeslipQrService $gradeslipQr): array
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
     * Pillow moiré / print-texture authenticity — disabled until AUTHENTICITY_SERVICE_URL is set.
     *
     * @return array{status: string, notes?: string}
     */
    private function runAuthenticityCheck(?DocumentSubmission $schoolId): array
    {
        $url = trim((string) config('services.identity.authenticity_service_url'));
        if ($url === '') {
            // Disabled: no stub error text for staff/student UI.
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
