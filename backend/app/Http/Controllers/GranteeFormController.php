<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormField;
use App\Http\Controllers\FormResponseController;
use App\Services\FormSecurityService;
use App\Services\FormSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GranteeFormController extends Controller
{
    public function __construct(
        private readonly FormSecurityService $security,
        private readonly FormSubmissionService $submission,
        private readonly FormResponseController $responseController,
    ) {}

    /**
     * GET /api/forms/assigned
     * List forms available to the authenticated grantee's batch.
     */
    public function assigned(Request $request): JsonResponse
    {
        $user    = $request->user();
        $grantee = $user->grantee;

        $query = Form::withCount('responses')
            ->where('visibility', 'private')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($grantee): void {
                // Forms assigned to the grantee's specific batch
                if ($grantee?->batch_id) {
                    $q->where('batch_id', $grantee->batch_id)
                      ->orWhereNull('batch_id'); // or forms without batch restriction
                } else {
                    $q->whereNull('batch_id');
                }
            })
            ->where(function ($q): void {
                $q->where('target_role', 'grantee')->orWhere('target_role', 'all');
            })
            ->orderByDesc('created_at');

        $forms = $query->get()->map(fn (Form $f) => $this->presentAssigned($f, $grantee?->id));

        return response()->json(['data' => $forms]);
    }

    /**
     * GET /api/forms/{id}/schema
     * Load the full schema for rendering — grantee access only.
     */
    public function schema(Request $request, int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');

        $user    = $request->user();
        $grantee = $user->grantee;

        // Always return 403 for private forms that fail access — never 404
        $form = Form::with('fields')->find($id);

        if (! $form || $form->deleted_at) {
            return response()->json(['success' => false, 'code' => 403, 'message' => 'Access denied.'], 403);
        }

        if ($form->visibility !== 'private') {
            return response()->json(['success' => false, 'code' => 403, 'message' => 'Access denied.'], 403);
        }

        if (! $form->is_active) {
            return response()->json(['success' => false, 'code' => 403, 'message' => 'Access denied.'], 403);
        }

        // Check batch assignment
        if ($form->batch_id && $grantee?->batch_id !== $form->batch_id) {
            $this->security->log('unauthorized_access', $request, $form->id);

            return response()->json(['success' => false, 'code' => 403, 'message' => 'Access denied.'], 403);
        }

        // Check closes_at before returning schema
        if ($form->closes_at && $form->closes_at->isPast()) {
            return response()->json(['success' => false, 'code' => 410, 'message' => 'This form is no longer accepting responses.'], 410);
        }

        $alreadySubmitted = $this->submission->hasReachedLimit($form, $grantee?->id);

        return response()->json([
            'data' => [
                'id'               => $form->id,
                'title'            => $form->title,
                'description'      => $form->description,
                'closes_at'        => $form->closes_at?->toISOString(),
                'already_submitted' => $alreadySubmitted,
                'fields'           => $form->fields->map(fn (FormField $f) => [
                    'id'             => $f->id,
                    'label'          => $f->label,
                    'field_name'     => $f->field_name,
                    'field_type'     => $f->field_type,
                    'placeholder'    => $f->placeholder,
                    'options'        => $f->options,
                    'is_required'    => $f->is_required,
                    'min_value'      => $f->min_value,
                    'max_value'      => $f->max_value,
                    'min_length'     => $f->min_length,
                    'max_length'     => $f->max_length,
                    'accepted_types' => $f->accepted_types,
                    'max_file_size'  => $f->max_file_size,
                ])->values(),
            ],
        ]);
    }

    /**
     * POST /api/forms/{id}/responses
     * Submit a response to a private form as authenticated grantee.
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');

        $user    = $request->user();
        $grantee = $user->grantee;

        $form = Form::with('fields')->find($id);

        if (! $form || $form->deleted_at || $form->visibility !== 'private') {
            return response()->json(['success' => false, 'code' => 403, 'message' => 'Access denied.'], 403);
        }

        if (! $form->is_active) {
            return response()->json(['success' => false, 'code' => 403, 'message' => 'Access denied.'], 403);
        }

        if ($form->batch_id && $grantee?->batch_id !== $form->batch_id) {
            $this->security->log('unauthorized_access', $request, $form->id);

            return response()->json(['success' => false, 'code' => 403, 'message' => 'Access denied.'], 403);
        }

        if ($form->closes_at && $form->closes_at->isPast()) {
            return response()->json(['success' => false, 'code' => 410, 'message' => 'This form is no longer accepting responses.'], 410);
        }

        if ($this->submission->hasReachedLimit($form, $grantee?->id)) {
            $this->security->log('duplicate_submission_attempt', $request, $form->id);

            return response()->json(['success' => false, 'code' => 409, 'message' => 'You have already submitted this form.'], 409);
        }

        /** @var FormResponseController $responseController */
        $responseController = app(FormResponseController::class);

        return $responseController->processSubmission(
            request: $request,
            form: $form,
            granteeId: $grantee?->id,
            batchId: $grantee?->batch_id,
            isAuthenticated: true,
        );
    }

    private function presentAssigned(Form $form, ?int $granteeId): array
    {
        $alreadySubmitted = $granteeId
            ? $this->submission->hasReachedLimit($form, $granteeId)
            : false;

        return [
            'id'               => $form->id,
            'title'            => $form->title,
            'description'      => $form->description,
            'closes_at'        => $form->closes_at?->toISOString(),
            'is_closed'        => $form->closes_at?->isPast() ?? false,
            'already_submitted' => $alreadySubmitted,
            'responses_count'  => $form->responses_count ?? 0,
        ];
    }
}
