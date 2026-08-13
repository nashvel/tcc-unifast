<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Form;
use App\Models\FormField;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage   = min(max((int) $request->integer('per_page', 20), 1), 100);
        $search    = trim((string) $request->query('search', ''));
        $visibility = $request->query('visibility');
        $status    = $request->query('status');
        $batchId   = $request->query('batch_id');

        $query = Form::withTrashed(false)
            ->withCount('responses')
            ->with(['batch:id,name', 'creator:id,name'])
            ->latest();

        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($visibility && $visibility !== 'all') {
            $query->where('visibility', $visibility);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($batchId && is_numeric($batchId)) {
            $query->where('batch_id', (int) $batchId);
        }

        $paginator = $query->paginate($perPage);
        $rows      = collect($paginator->items())->map(fn (Form $f) => $this->present($f));

        return PaginatedJson::from($paginator, $rows->values());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:191'],
            'description'     => ['nullable', 'string', 'max:5000'],
            'target_role'     => ['required', 'in:grantee,staff,all'],
            'visibility'      => ['required', 'in:public,private'],
            'batch_id'        => ['nullable', 'integer', 'exists:batches,id'],
            'is_active'       => ['boolean'],
            'max_submissions' => ['nullable', 'integer', 'min:1'],
            'closes_at'       => ['nullable', 'date', 'after:now'],
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['is_active']  = $validated['is_active'] ?? false;

        if (($validated['visibility'] ?? 'private') === 'public') {
            $validated['public_token'] = (string) Str::uuid();
        }

        $form = Form::create($validated);

        AuditLog::create([
            'actor'      => $request->user()->name,
            'role'       => ucfirst($request->user()->role),
            'action'     => 'form_created',
            'module'     => 'Forms',
            'target'     => $form->title,
            'context'    => ['title' => $form->title, 'visibility' => $form->visibility, 'target_role' => $form->target_role],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => $this->present($form->loadCount('responses'))], 201);
    }

    public function show(int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');

        $form = Form::withCount('responses')->with('fields')->findOrFail($id);

        return response()->json(['data' => $this->presentWithFields($form)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');

        $form = Form::findOrFail($id);

        $oldVisibility = $form->visibility;

        $validated = $request->validate([
            'title'           => ['sometimes', 'required', 'string', 'max:191'],
            'description'     => ['nullable', 'string', 'max:5000'],
            'target_role'     => ['sometimes', 'required', 'in:grantee,staff,all'],
            'visibility'      => ['sometimes', 'required', 'in:public,private'],
            'batch_id'        => ['nullable', 'integer', 'exists:batches,id'],
            'is_active'       => ['sometimes', 'boolean'],
            'max_submissions' => ['nullable', 'integer', 'min:1'],
            'closes_at'       => ['nullable', 'date'],
        ]);

        $before = $form->only(array_keys($validated));

        // Handle visibility change
        if (isset($validated['visibility'])) {
            if ($validated['visibility'] === 'public' && $oldVisibility === 'private') {
                $validated['public_token'] = (string) Str::uuid();
            } elseif ($validated['visibility'] === 'private' && $oldVisibility === 'public') {
                $validated['public_token'] = null;
            }
        }

        $form->update($validated);

        AuditLog::create([
            'actor'      => $request->user()->name,
            'role'       => ucfirst($request->user()->role),
            'action'     => 'form_updated',
            'module'     => 'Forms',
            'target'     => $form->title,
            'context'    => ['before' => $before, 'after' => $form->fresh()->only(array_keys($validated))],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => $this->presentWithFields($form->fresh()->loadCount('responses')->load('fields'))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');

        $form = Form::findOrFail($id);

        if ($form->responses()->exists()) {
            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => 'Cannot delete a form that has responses. The form has been preserved.',
            ], 422);
        }

        $form->delete();

        AuditLog::create([
            'actor'      => $request->user()->name,
            'role'       => ucfirst($request->user()->role),
            'action'     => 'form_deleted',
            'module'     => 'Forms',
            'target'     => $form->title,
            'context'    => ['deleted_by' => $request->user()->name],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Form deleted.']);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');

        $form = Form::findOrFail($id);

        // Cannot activate if no fields
        if (! $form->is_active && $form->fields()->count() === 0) {
            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => 'A form must have at least one field before it can be activated.',
            ], 422);
        }

        // Cannot activate if choice fields missing options
        if (! $form->is_active && ! $form->allChoiceFieldsHaveOptions()) {
            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => 'All select, radio, and checkbox fields must have at least two options.',
            ], 422);
        }

        $form->update(['is_active' => ! $form->is_active]);

        $action = $form->fresh()->is_active ? 'form_activated' : 'form_deactivated';

        AuditLog::create([
            'actor'      => $request->user()->name,
            'role'       => ucfirst($request->user()->role),
            'action'     => $action,
            'module'     => 'Forms',
            'target'     => $form->title,
            'context'    => ['is_active' => $form->fresh()->is_active],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => $this->present($form->fresh()->loadCount('responses'))]);
    }

    public function regenerateToken(Request $request, int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');

        $form = Form::findOrFail($id);

        if ($form->visibility !== 'public') {
            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => 'Token regeneration is only available for public forms.',
            ], 422);
        }

        $oldToken = $form->public_token;
        $newToken = (string) Str::uuid();

        $form->update(['public_token' => $newToken]);

        AuditLog::create([
            'actor'      => $request->user()->name,
            'role'       => ucfirst($request->user()->role),
            'action'     => 'form_token_regenerated',
            'module'     => 'Forms',
            'target'     => $form->title,
            'context'    => ['old_token' => $oldToken, 'new_token' => $newToken],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => ['public_token' => $newToken]]);
    }

    // ──────────────────────────────────────────
    // Presenters
    // ──────────────────────────────────────────

    private function present(Form $form): array
    {
        return [
            'id'              => $form->id,
            'title'           => $form->title,
            'description'     => $form->description,
            'visibility'      => $form->visibility,
            'target_role'     => $form->target_role,
            'is_active'       => $form->is_active,
            'max_submissions' => $form->max_submissions,
            'closes_at'       => $form->closes_at?->toISOString(),
            'batch_id'        => $form->batch_id,
            'batch_name'      => $form->batch?->name,
            'public_token'    => $form->public_token,
            'responses_count' => $form->responses_count ?? 0,
            'created_by'      => $form->creator?->name,
            'created_at'      => $form->created_at?->toISOString(),
            'updated_at'      => $form->updated_at?->toISOString(),
        ];
    }

    private function presentWithFields(Form $form): array
    {
        return array_merge($this->present($form), [
            'fields' => $form->fields->map(fn (FormField $f) => [
                'id'            => $f->id,
                'label'         => $f->label,
                'field_name'    => $f->field_name,
                'field_type'    => $f->field_type,
                'placeholder'   => $f->placeholder,
                'options'       => $f->options,
                'is_required'   => $f->is_required,
                'min_value'     => $f->min_value,
                'max_value'     => $f->max_value,
                'min_length'    => $f->min_length,
                'max_length'    => $f->max_length,
                'accepted_types' => $f->accepted_types,
                'max_file_size' => $f->max_file_size,
                'sort_order'    => $f->sort_order,
                'is_locked'     => $f->is_locked,
            ])->values(),
        ]);
    }
}
