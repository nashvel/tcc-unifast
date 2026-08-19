<?php

namespace App\Services\SubmissionPipeline;

use App\Models\DocumentSubmission;
use App\Models\PolicySetting;
use App\Services\AcademicGradeParser;
use App\Services\PdfDocumentService;
use App\Support\VaultFileStorage;
use Illuminate\Support\Collection;

class PipelineAcademicOcrService
{
    public function __construct(
        private readonly AcademicGradeParser $gradeParser,
        private readonly PdfDocumentService $pdfDocuments,
    ) {}

    /**
     * PyMuPDF-first for SIS PDFs. Tesseract only if no text layer.
     *
     * @return array<string, mixed>
     */
    public function processPdfSlot(DocumentSubmission $doc): array
    {
        if (! $doc->stored_path || ! VaultFileStorage::exists($doc->stored_path)) {
            return [
                'status' => 'skipped',
                'error' => 'PDF file missing',
                'pdf_metadata_analysis' => ['suspicious' => false, 'reasons' => [], 'fields' => [], 'source' => 'unavailable'],
            ];
        }

        $absolute = VaultFileStorage::absolutePath($doc->stored_path);
        $result = $this->pdfDocuments->process($absolute, $doc->original_name ?: 'document.pdf');

        $meta = is_array($doc->metadata_payload) ? $doc->metadata_payload : [];
        $analysis = is_array($result['pdf_metadata_analysis'] ?? null) ? $result['pdf_metadata_analysis'] : null;
        $rawMeta = is_array($result['pdf_metadata'] ?? null) ? $result['pdf_metadata'] : null;
        if ($rawMeta === null && is_array($analysis['fields'] ?? null)) {
            $rawMeta = $analysis['fields'];
        }
        $courses = is_array($result['courses'] ?? null) ? $result['courses'] : [];
        $terms = is_array($result['terms'] ?? null) ? $result['terms'] : [];
        $slotKey = is_string($doc->slot_key) ? $doc->slot_key : null;
        $gradeSummary = $this->gradeParser->parse(
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
        $meta['pdf_metadata'] = $rawMeta;
        $meta['pdf_metadata_analysis'] = $analysis;
        $maxFailed = PolicySetting::maxFailedSubjects();
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
     * @param  Collection<string, DocumentSubmission>  $slots
     * @return array<string, mixed>
     */
    public function applyGradeSlipAnchorToCourseHistory(array $ocrSummary, Collection $slots): array
    {
        $ch = $ocrSummary['course_history'] ?? null;
        $gs = $ocrSummary['grade_slip'] ?? null;
        if (! is_array($ch) || ! is_array($gs)) {
            return $ocrSummary;
        }

        $chText = (string) ($ch['raw_text'] ?? $ch['text'] ?? $ch['combined_text'] ?? '');
        $chCourses = is_array($ch['courses'] ?? null) ? $ch['courses'] : null;
        $chTerms = is_array($ch['terms'] ?? null) ? $ch['terms'] : null;
        $gsTerm = $this->gradeParser->resolveGradeSlipTerm($gs, $chTerms);
        if ($gsTerm === null || $gsTerm === '') {
            return $ocrSummary;
        }
        $reparsed = $this->gradeParser->parse(
            $chText,
            null,
            $chCourses,
            'course_history',
            $chTerms,
            $gsTerm,
        );

        $maxFailed = PolicySetting::maxFailedSubjects();
        $retention = (int) $reparsed['retention_count'];
        $pendingCount = (int) ($reparsed['pending_count'] ?? 0);
        $gsCourses = is_array($gs['courses'] ?? null) ? $gs['courses'] : [];
        $mismatches = $this->gradeParser->crossCheckChBlanksAgainstGradeSlip(
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
}
