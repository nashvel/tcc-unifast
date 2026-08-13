<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormField;
use App\Services\FormSecurityService;
use App\Services\FormSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicFormController extends Controller
{
    public function __construct(
        private readonly FormSecurityService $security,
        private readonly FormSubmissionService $submission,
        private readonly FormResponseController $responseController,
    ) {}

    /**
     * GET /api/forms/public/{token}
     * Returns the form schema for rendering — no authentication required.
     */
    public function show(Request $request, string $token): JsonResponse
    {
        // Validate token format before any DB query
        if (! $this->security->validateToken($token)) {
            return response()->json(['success' => false, 'code' => 400, 'message' => 'Invalid request.'], 400);
        }

        $form = Form::with('fields')
            ->where('public_token', $token)
            ->where('visibility', 'public')
            ->first();

        if (! $form) {
            return response()->json(['success' => false, 'code' => 404, 'message' => 'Not found.'], 404);
        }

        if (! $form->is_active) {
            return response()->json(['success' => false, 'code' => 404, 'message' => 'Not found.'], 404);
        }

        // Check expiry before returning schema
        if ($form->closes_at && $form->closes_at->isPast()) {
            return response()->json(['success' => false, 'code' => 410, 'message' => 'This form is no longer accepting responses.'], 410);
        }

        return response()->json(['data' => $this->presentPublicSchema($form)]);
    }

    /**
     * POST /api/forms/public/{token}/responses
     * Submit a response to a public form — no authentication required.
     * Rate limiting applied at route level.
     */
    public function store(Request $request, string $token): JsonResponse
    {
        // Validate token format before DB query
        if (! $this->security->validateToken($token)) {
            $this->security->log('token_enumeration_attempt', $request, null, ['token_length' => strlen($token)]);

            return response()->json(['success' => false, 'code' => 400, 'message' => 'Invalid request.'], 400);
        }

        $form = Form::with('fields')
            ->where('public_token', $token)
            ->where('visibility', 'public')
            ->first();

        if (! $form) {
            return response()->json(['success' => false, 'code' => 404, 'message' => 'Not found.'], 404);
        }

        if (! $form->is_active) {
            return response()->json(['success' => false, 'code' => 404, 'message' => 'Not found.'], 404);
        }

        if ($form->closes_at && $form->closes_at->isPast()) {
            return response()->json(['success' => false, 'code' => 410, 'message' => 'This form is no longer accepting responses.'], 410);
        }

        return $this->responseController->processSubmission(
            request: $request,
            form: $form,
            granteeId: null,
            batchId: null,
            isAuthenticated: false,
        );
    }

    private function presentPublicSchema(Form $form): array
    {
        return [
            'id'          => $form->id,
            'title'       => $form->title,
            'description' => $form->description,
            'closes_at'   => $form->closes_at?->toISOString(),
            'fields'      => $form->fields->map(fn (FormField $f) => [
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
        ];
    }
}
