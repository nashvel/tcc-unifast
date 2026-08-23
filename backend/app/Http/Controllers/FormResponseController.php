<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormResponse;
use App\Services\FormSecurityService;
use App\Services\FormSubmissionService;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormResponseController extends Controller
{
    public function __construct(
        private readonly FormSecurityService $security,
        private readonly FormSubmissionService $submission,
    ) {}

    /** Admin + staff: list responses for a form. */
    public function index(Request $request, int $formId): JsonResponse
    {
        abort_if($formId < 1, 400, 'Invalid form ID.');

        $form = Form::findOrFail($formId);
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = FormResponse::with(['grantee:id,full_name,student_id', 'batch:id,name'])
            ->where('form_id', $formId)
            ->latest('submitted_at');

        if ($auth = $request->query('authenticated')) {
            $query->where('is_authenticated', $auth === 'true' ? 1 : 0);
        }

        if ($from = $request->query('date_from')) {
            $query->where('submitted_at', '>=', $from);
        }

        if ($to = $request->query('date_to')) {
            $query->where('submitted_at', '<=', $to.' 23:59:59');
        }

        if ($batchId = $request->query('batch_id')) {
            if (is_numeric($batchId)) {
                $query->where('batch_id', (int) $batchId);
            }
        }

        $paginator = $query->paginate($perPage);
        $rows = collect($paginator->items())
            ->map(fn (FormResponse $r) => $this->presentRow($r));

        return PaginatedJson::from($paginator, $rows->values());
    }

    /** Admin + staff: view a single response. */
    public function show(int $formId, int $responseId): JsonResponse
    {
        abort_if($formId < 1 || $responseId < 1, 400, 'Invalid ID.');

        $response = FormResponse::with(['grantee:id,full_name,student_id', 'batch:id,name'])
            ->where('form_id', $formId)
            ->findOrFail($responseId);

        return response()->json(['data' => $this->presentDetail($response)]);
    }

    /**
     * Grantee: submit a response to a private form.
     */
    public function store(Request $request, int $formId): JsonResponse
    {
        abort_if($formId < 1, 400, 'Invalid form ID.');

        $form = Form::with('fields')->findOrFail($formId);

        // Check form is active and not expired
        if (! $form->is_active) {
            return response()->json(['success' => false, 'code' => 403, 'message' => 'Access denied.'], 403);
        }

        if ($form->closes_at && $form->closes_at->isPast()) {
            return response()->json(['success' => false, 'code' => 410, 'message' => 'This form is no longer accepting responses.'], 410);
        }

        $grantee = $request->user()->grantee;
        $granteeId = $grantee?->id;

        // Check batch assignment for private grantee forms
        if ($form->visibility === 'private' && $form->batch_id && $grantee?->batch_id !== $form->batch_id) {
            return response()->json(['success' => false, 'code' => 403, 'message' => 'Access denied.'], 403);
        }

        // Duplicate submission check
        if ($this->submission->hasReachedLimit($form, $granteeId)) {
            $this->security->log('duplicate_submission_attempt', $request, $form->id);

            return response()->json(['success' => false, 'code' => 409, 'message' => 'You have already submitted this form.'], 409);
        }

        return $this->processSubmission($request, $form, $granteeId, $grantee?->batch_id, true);
    }

    /**
     * Admin only: export all responses as CSV.
     */
    public function export(Request $request, int $formId): StreamedResponse
    {
        abort_if($formId < 1, 400, 'Invalid form ID.');

        $form = Form::with('fields')->findOrFail($formId);
        $responses = FormResponse::with(['grantee:id,full_name,student_id'])
            ->where('form_id', $formId)
            ->orderBy('submitted_at')
            ->get();

        $fields = $form->fields->pluck('label', 'field_name')->all();
        $fieldNames = array_keys($fields);

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => ucfirst($request->user()->role),
            'action' => 'form_responses_exported',
            'module' => 'Forms',
            'target' => $form->title,
            'context' => ['exported_by' => $request->user()->name, 'response_count' => $responses->count()],
            'ip_address' => $request->ip(),
        ]);

        return response()->streamDownload(function () use ($responses, $fieldNames, $fields): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // Header row
            fputcsv($out, array_merge(
                ['Grantee Name', 'Student ID', 'Batch', 'Submitted At', 'Authenticated'],
                array_values($fields),
            ));

            foreach ($responses as $resp) {
                $responseData = is_array($resp->responses) ? $resp->responses : [];
                $row = [
                    $resp->grantee?->full_name ?? 'Anonymous',
                    $resp->grantee?->student_id ?? '',
                    $resp->batch?->name ?? '',
                    $resp->submitted_at?->toDateTimeString() ?? '',
                    $resp->is_authenticated ? 'Yes' : 'No',
                ];

                foreach ($fieldNames as $name) {
                    $val = $responseData[$name] ?? '';
                    $row[] = is_array($val) ? implode(', ', $val) : (string) $val;
                }

                fputcsv($out, $row);
            }

            fclose($out);
        }, "form-{$formId}-responses.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Shared submission pipeline used by both private and public handlers.
     */
    public function processSubmission(
        Request $request,
        Form $form,
        ?int $granteeId,
        ?int $batchId,
        bool $isAuthenticated,
    ): JsonResponse {
        $rawData = $request->except(['_token', 'honeypot', 'website', 'url', 'phone_number']);

        // ── Honeypot check ──────────────────────────────────────────
        $honeypot = $request->input('website', '')
            ?: $request->input('url', '')
            ?: $request->input('phone_number', '');

        if ($honeypot !== '' && $honeypot !== null) {
            $this->security->log('honeypot_triggered', $request, $form->id, ['honeypot_value' => '(redacted)']);

            // Silent 200 to fool bots — do not store response
            return response()->json(['success' => true, 'message' => 'Response submitted.']);
        }

        // ── Threat detection (pre-sanitization, on raw) ─────────────
        $threat = $this->security->detectThreat($rawData);
        if ($threat) {
            $this->security->log($threat, $request, $form->id, ['keys' => array_keys($rawData)]);

            return response()->json([
                'success' => false,
                'code' => 422,
                'message' => 'Validation failed',
                'errors' => ['submission' => ['Submission contains invalid content.']],
            ], 422);
        }

        // ── Sanitize ────────────────────────────────────────────────
        $cleanData = $this->security->sanitizeSubmission($rawData);

        // ── Schema validation ───────────────────────────────────────
        $this->submission->validateSchema($form, $cleanData);

        // ── Handle file fields ──────────────────────────────────────
        foreach ($form->fields->where('field_type', 'file') as $field) {
            $name = $field->field_name;

            if ($request->hasFile($name)) {
                $file = $request->file($name);
                if ($file && $file->isValid()) {
                    $path = $this->submission->storeFileField($file, $field, $name);
                    $cleanData[$name] = $path;
                }
            }
        }

        // ── Lock fields once first response is stored ───────────────
        $isFirstResponse = $form->responses()->doesntExist();

        $hash = $this->submission->buildResponseHash($form, $granteeId, $cleanData);

        FormResponse::create([
            'form_id' => $form->id,
            'grantee_id' => $granteeId,
            'batch_id' => $batchId,
            'responses' => $cleanData,
            'response_hash' => $hash,
            'is_authenticated' => $isAuthenticated,
            'submitter_ip' => mb_substr((string) $request->ip(), 0, 45),
            'submitter_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'honeypot_triggered' => false,
            'submitted_at' => now(),
            'created_at' => now(),
        ]);

        if ($isFirstResponse) {
            $form->fields()->update(['is_locked' => true]);
        }

        AuditLog::create([
            'actor' => $request->user()?->name ?? 'Anonymous',
            'role' => ucfirst($request->user()?->role ?? 'guest'),
            'action' => 'form_response_submitted',
            'module' => 'Forms',
            'target' => $form->title,
            'context' => ['form_id' => $form->id, 'grantee_id' => $granteeId, 'is_authenticated' => $isAuthenticated],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['success' => true, 'message' => 'Response submitted successfully.'], 201);
    }

    // ── Presenters ────────────────────────────────────────────────────

    private function presentRow(FormResponse $r): array
    {
        return [
            'id' => $r->id,
            'grantee_name' => $r->grantee?->full_name ?? 'Anonymous',
            'student_id' => $r->grantee?->student_id,
            'batch_name' => $r->batch?->name,
            'submitted_at' => $r->submitted_at?->toISOString(),
            'is_authenticated' => $r->is_authenticated,
            'submitter_ip' => $r->submitter_ip,
        ];
    }

    private function presentDetail(FormResponse $r): array
    {
        return array_merge($this->presentRow($r), [
            'responses' => $r->responses,
            'honeypot_triggered' => $r->honeypot_triggered,
            'submitter_agent' => $r->submitter_agent,
        ]);
    }
}
