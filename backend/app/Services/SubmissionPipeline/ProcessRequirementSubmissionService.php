<?php

namespace App\Services\SubmissionPipeline;

use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\SubmissionPipelineResult;
use App\Services\AcademicGradeParser;
use App\Services\StaffSubmissionNotifier;
use App\Services\SubmissionRiskScoringService;

class ProcessRequirementSubmissionService
{
    public function __construct(
        private readonly SubmissionRiskScoringService $scoring,
        private readonly AcademicGradeParser $gradeParser,
        private readonly StaffSubmissionNotifier $staffNotifier,
        private readonly PipelineExternalChecksService $externalChecks,
        private readonly PipelineAcademicOcrService $academicOcr,
    ) {}

    public function process(int $granteeId, int $batchId, bool $identityFailed = false): void
    {
        $grantee = Grantee::query()->with('kycProfile')->find($granteeId);
        if (! $grantee) {
            return;
        }

        $slots = DocumentSubmission::query()
            ->where('grantee_id', $granteeId)
            ->where('batch_id', $batchId)
            ->get()
            ->keyBy('slot_key');

        // Course History + Grade Slip: PyMuPDF text/metadata (Tesseract only if no text layer).
        $ocrSummary = [];
        foreach (['course_history', 'grade_slip'] as $slot) {
            $doc = $slots->get($slot);
            if (! $doc) {
                continue;
            }
            $ocrSummary[$slot] = $this->academicOcr->processPdfSlot($doc);
        }

        // Re-classify Course History blanks using Grade Slip academic term as pending anchor.
        $ocrSummary = $this->academicOcr->applyGradeSlipAnchorToCourseHistory($ocrSummary, $slots);

        $gradeSlipDoc = $slots->get('grade_slip');
        $gradeslipQrResult = $gradeSlipDoc
            ? $this->externalChecks->runGradeslipQr($gradeSlipDoc)
            : ['status' => 'skipped', 'success' => false, 'found' => false, 'domain_valid' => false];

        if ($gradeSlipDoc && isset($ocrSummary['grade_slip']) && is_array($ocrSummary['grade_slip'])) {
            $ocrSummary['grade_slip']['gradeslip_qr'] = $gradeslipQrResult;
            if ($this->gradeParser->gradeSlipLooksLikeEmptyEnrollment($ocrSummary['grade_slip'])) {
                $ocrSummary['grade_slip']['enrollment_slip_warning'] = true;
                $ocrSummary['grade_slip']['grade_summary'] = array_merge(
                    is_array($ocrSummary['grade_slip']['grade_summary'] ?? null)
                        ? $ocrSummary['grade_slip']['grade_summary']
                        : [],
                    [
                        'enrollment_slip_warning' => true,
                        'message' => 'This Grade Slip looks like a current-enrollment slip with no grades. Upload the last Grade Slip that already has grades (usually the 2nd-to-last term on Course History).',
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
            $gradeSlipTerm = $this->gradeParser->resolveGradeSlipTerm($ocrSummary['grade_slip'], $chTermsForResolve);
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
        $academics = $this->gradeParser->parse(
            $combinedText,
            $grantee->program,
            $retentionCourses !== [] ? $retentionCourses : null,
            $retentionSlot,
            $retentionTerms !== [] ? $retentionTerms : null,
            $retentionSlot === 'course_history' ? $gradeSlipTerm : null,
        );
        $ocrSummary['_academics'] = $academics;

        // Pillow moiré authenticity is disabled until the microservice is ready.
        $authenticity = $this->externalChecks->runAuthenticityCheck($slots->get('school_id'));

        $signals = $this->scoring->collectSignals(
            identityFailed: $identityFailed,
            ocrSummary: $ocrSummary,
            authenticityStatus: $authenticity['status'],
            grantee: $grantee,
            gradeslipQr: $gradeslipQrResult,
        );
        $score = $this->scoring->score($signals);
        $badge = $this->scoring->badge($score);
        $eligibility = $this->scoring->evaluateEligibility($grantee, $ocrSummary);

        if ($eligibility['status'] === 'pass') {
            $grantee->forceFill(['status' => 'eligible'])->save();
        } elseif ($eligibility['status'] === 'fail') {
            $grantee->forceFill(['status' => 'not_eligible'])->save();
        }

        $n8nStatus = $this->externalChecks->triggerN8n($grantee, $batchId, $score, $badge, $signals, $eligibility);
        $this->staffNotifier->notifyPipelineComplete($grantee, $batchId, $eligibility, $badge);

        $metaNotes = collect($ocrSummary)
            ->filter(fn ($row) => is_array($row) && ! empty($row['pdf_metadata_analysis']['reasons'] ?? []))
            ->flatMap(fn ($row) => $row['pdf_metadata_analysis']['reasons'])
            ->unique()
            ->implode('; ');

        SubmissionPipelineResult::updateOrCreate(
            ['grantee_id' => $granteeId, 'batch_id' => $batchId],
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
}
