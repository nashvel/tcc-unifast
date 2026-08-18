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

        // Re-classify Course History blanks using Grade Slip academic term as pending anchor.
        $ocrSummary = $this->applyGradeSlipAnchorToCourseHistory($ocrSummary, $slots, $gradeParser);

        $gradeSlipDoc = $slots->get('grade_slip');
        $gradeslipQrResult = $gradeSlipDoc
            ? $this->runGradeslipQr($gradeSlipDoc, $gradeslipQr)
            : ['status' => 'skipped', 'success' => false, 'found' => false, 'domain_valid' => false];

        if ($gradeSlipDoc && isset($ocrSummary['grade_slip']) && is_array($ocrSummary['grade_slip'])) {
            $ocrSummary['grade_slip']['gradeslip_qr'] = $gradeslipQrResult;
            if ($gradeParser->gradeSlipLooksLikeEmptyEnrollment($ocrSummary['grade_slip'])) {
                $ocrSummary['grade_slip']['enrollment_slip_warning'] = true;
                $ocrSummary['grade_slip']['grade_summary'] = array_merge(
                    is_array($ocrSummary['grade_slip']['grade_summary'] ?? null)
                        ? $ocrSummary['grade_slip']['grade_summary']
                        : [],
                    [
                        'enrollment_slip_warning' => true,
                        'message' => 'This Grade Slip has pending or late instructor grades. It is accepted for review as long as it is the official current Grade Slip.',
                    ],
                );
            }
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
        $gradeSlipTerm = null;
        if (isset($ocrSummary['grade_slip']) && is_array($ocrSummary['grade_slip'])) {
            $chTermsForResolve = is_array($ocrSummary['course_history']['terms'] ?? null)
                ? $ocrSummary['course_history']['terms']
                : null;
            $gradeSlipTerm = $gradeParser->resolveGradeSlipTerm($ocrSummary['grade_slip'], $chTermsForResolve);
        }
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
            $retentionSlot === 'course_history' ? $gradeSlipTerm : null,
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
        // Top-level aliases for staff Document Detail / API consumers.
        $meta['pdf_metadata'] = $rawMeta;
        $meta['pdf_metadata_analysis'] = $analysis;
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
            'grade_slip_term' => $gradeSummary['grade_slip_term'] ?? null,
            'terms_detected' => (bool) ($gradeSummary['terms_detected'] ?? false),
            'over_limit' => $retention >= $maxFailed,
            'term_count' => is_array($gradeSummary['terms'] ?? null) ? count($gradeSummary['terms']) : 0,
            'message' => $retention >= $maxFailed
                ? sprintf(
                    'Not eligible under retention: %d failed + %d dropped = %d (max %d).%s',
                    $gradeSummary['failed_count'],
                    $gradeSummary['dropped_count'],
                    $retention,
                    $maxFailed,
                    $blanksAsDropped
                        ? ($pendingCount > 0
                            ? sprintf(' %d pending blank(s) ignored (Grade Slip term + current enrollment).', $pendingCount)
                            : ' Older Course History blanks count as dropped; GS term and newer enrollment blanks are pending.')
                        : ' Grade-slip blanks ignored for eligibility.',
                )
                : ($pendingCount > 0
                    ? sprintf('%d pending grade(s) noted (not counted toward retention).', $pendingCount)
                    : null),
        ];
        if ($slotKey === 'course_history' && empty($gradeSummary['terms_detected'])) {
            $meta['grade_summary']['terms_missing_warning'] = true;
            $meta['grade_summary']['message'] = trim(
                ($meta['grade_summary']['message'] ? $meta['grade_summary']['message'].' ' : '')
                .'Term headers not detected — blanks treated as pending; re-check the Course History PDF.'
            );
        }

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
                'pdf_metadata' => $rawMeta,
                'pdf_metadata_analysis' => $analysis,
                'result' => [
                    'combined_text' => $result['raw_text'] ?? $result['text'] ?? '',
                    'formatted_table_text' => $result['formatted_table_text'] ?? null,
                    'courses' => $coursesForUi,
                    'terms' => $termsForUi,
                    'grade_summary' => $meta['grade_summary'],
                    'pdf_metadata' => $rawMeta,
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
     * Re-parse Course History with Grade Slip academic_term so pending blanks follow
     * GS term + newer CH terms (not newest+2-prior).
     *
     * @param  array<string, mixed>  $ocrSummary
     * @param  \Illuminate\Support\Collection<string, DocumentSubmission>  $slots
     * @return array<string, mixed>
     */
    private function applyGradeSlipAnchorToCourseHistory(
        array $ocrSummary,
        $slots,
        AcademicGradeParser $gradeParser,
    ): array {
        $ch = $ocrSummary['course_history'] ?? null;
        $gs = $ocrSummary['grade_slip'] ?? null;
        if (! is_array($ch) || ! is_array($gs)) {
            return $ocrSummary;
        }

        $chText = (string) ($ch['raw_text'] ?? $ch['text'] ?? $ch['combined_text'] ?? '');
        $chCourses = is_array($ch['courses'] ?? null) ? $ch['courses'] : null;
        $chTerms = is_array($ch['terms'] ?? null) ? $ch['terms'] : null;
        // Pass CH terms so GS without year/semester can still infer Summer (etc.) via course overlap.
        $gsTerm = $gradeParser->resolveGradeSlipTerm($gs, $chTerms);
        if ($gsTerm === null || $gsTerm === '') {
            return $ocrSummary;
        }
        $reparsed = $gradeParser->parse(
            $chText,
            null,
            $chCourses,
            'course_history',
            $chTerms,
            $gsTerm,
        );

        $maxFailed = \App\Models\PolicySetting::maxFailedSubjects();
        $retention = (int) $reparsed['retention_count'];
        $pendingCount = (int) ($reparsed['pending_count'] ?? 0);
        $gsCourses = is_array($gs['courses'] ?? null) ? $gs['courses'] : [];
        $mismatches = $gradeParser->crossCheckChBlanksAgainstGradeSlip(
            $chTerms,
            $chCourses,
            $gsCourses,
            $gsTerm,
        );
        $gradeSummary = [
            'blank_count' => $reparsed['blank_count'],
            'pending_count' => $pendingCount,
            'failed_count' => $reparsed['failed_count'],
            'dropped_count' => $reparsed['dropped_count'],
            'numeric_failed_count' => $reparsed['numeric_failed_count'] ?? 0,
            'retention_count' => $retention,
            'pass_grade' => $reparsed['pass_grade'],
            'program_code' => $reparsed['program_code'],
            'max_failed' => $maxFailed,
            'document_type' => 'course_history',
            'blanks_count_as_fails' => false,
            'blanks_count_as_dropped' => true,
            'pending_term_window' => AcademicGradeParser::PENDING_TERM_WINDOW,
            'grade_slip_term' => $gsTerm,
            'terms_detected' => (bool) ($reparsed['terms_detected'] ?? false),
            'over_limit' => $retention >= $maxFailed,
            'term_count' => is_array($reparsed['terms'] ?? null) ? count($reparsed['terms']) : 0,
            'cross_check' => 'grade_slip_term',
            'grade_mismatches' => $mismatches,
            'grade_mismatch_count' => count($mismatches),
            'message' => $retention >= $maxFailed
                ? sprintf(
                    'Not eligible under retention: %d failed + %d dropped = %d (max %d). Pending blanks use Grade Slip term "%s" plus any newer Course History enrollment.',
                    $reparsed['failed_count'],
                    $reparsed['dropped_count'],
                    $retention,
                    $maxFailed,
                    $gsTerm,
                )
                : sprintf(
                    'Pending blanks anchored to Grade Slip term "%s"%s.',
                    $gsTerm,
                    $pendingCount > 0 ? sprintf(' (%d pending)', $pendingCount) : '',
                ),
        ];
        if ($mismatches !== []) {
            $codes = implode(', ', array_column($mismatches, 'code'));
            $gradeSummary['message'] = trim(
                ($gradeSummary['message'] ?? '').
                ' Staff flag: Course History blank but Grade Slip has a grade for: '.$codes.'.'
            );
        }

        $coursesForUi = is_array($reparsed['courses'] ?? null) && $reparsed['courses'] !== []
            ? $reparsed['courses']
            : ($chCourses ?? []);
        $termsForUi = is_array($reparsed['terms'] ?? null) && $reparsed['terms'] !== []
            ? $reparsed['terms']
            : ($chTerms ?? []);

        $ocrSummary['course_history']['courses'] = $coursesForUi;
        $ocrSummary['course_history']['terms'] = $termsForUi;
        $ocrSummary['course_history']['grade_summary'] = $gradeSummary;
        $ocrSummary['course_history']['grade_slip_term'] = $gsTerm;

        $chDoc = $slots->get('course_history');
        if ($chDoc instanceof DocumentSubmission) {
            $meta = is_array($chDoc->metadata_payload) ? $chDoc->metadata_payload : [];
            $meta['grade_summary'] = $gradeSummary;
            $payload = is_array($chDoc->ocr_payload) ? $chDoc->ocr_payload : [];
            $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
            $result['courses'] = $coursesForUi;
            $result['terms'] = $termsForUi;
            $result['grade_summary'] = $gradeSummary;
            $payload['result'] = $result;
            $chDoc->update([
                'ocr_payload' => $payload,
                'metadata_payload' => $meta,
            ]);
        }

        return $ocrSummary;
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

        // Soft-fail: refresh so QR merge never wipes pdf_document / pdf_metadata
        // written by processPdfSlot (even when pyzbar DLL / QR decode fails).
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
