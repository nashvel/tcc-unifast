<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormSection;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FormController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // Forms CRUD
    // ──────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $perPage    = min(max((int) $request->integer('per_page', 20), 1), 100);
        $search     = trim((string) $request->query('search', ''));
        $visibility = $request->query('visibility');
        $status     = $request->query('status');
        $batchId    = $request->query('batch_id');

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

        $archived = filter_var($request->query('archived', false), FILTER_VALIDATE_BOOLEAN);
        if ($archived) {
            $query->where('status', 'archived');
        } else {
            $query->where(function($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'archived');
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status && $status !== 'all') {
            $query->where('status', $status);
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
        $validated['status']     = 'draft';
        $validated['is_active']  = false; // always start as draft

        if (($validated['visibility'] ?? 'private') === 'public') {
            $validated['public_token'] = (string) Str::uuid();
        }

        $form = Form::create($validated);

        // Create a default section
        $form->sections()->create(['title' => 'Section 1', 'sort_order' => 0]);

        AuditLog::create([
            'actor'      => $request->user()->name,
            'role'       => ucfirst($request->user()->role),
            'action'     => 'form_created',
            'module'     => 'Forms',
            'target'     => $form->title,
            'context'    => ['title' => $form->title, 'visibility' => $form->visibility],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => $this->present($form->loadCount('responses'))], 201);
    }

    public function show(int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');

        $form = Form::withCount('responses')
            ->with([
                'sections.fields.fieldOptions',
                'sections.fields.conditions',
            ])
            ->findOrFail($id);

        return response()->json(['data' => $this->presentWithSections($form)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');

        $form          = Form::findOrFail($id);
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

        $form = $form->fresh()->loadCount('responses')->load([
            'sections.fields.fieldOptions',
            'sections.fields.conditions',
        ]);

        return response()->json(['data' => $this->presentWithSections($form)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');

        $form = Form::findOrFail($id);

        $form->archive();

        AuditLog::create([
            'actor'      => $request->user()->name,
            'role'       => ucfirst($request->user()->role),
            'action'     => 'form_archived',
            'module'     => 'Forms',
            'target'     => $form->title,
            'context'    => ['archived_by' => $request->user()->name],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Form archived.']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Publish Workflow
    // ──────────────────────────────────────────────────────────────────────────

    public function publish(Request $request, int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');
        $form = Form::with('sections.fields.fieldOptions')->findOrFail($id);

        $allFields = $form->sections->flatMap(fn ($s) => $s->fields);

        if ($allFields->isEmpty()) {
            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => 'A form must have at least one field before it can be published.',
            ], 422);
        }

        // Validate: all choice fields must have ≥ 2 options
        $choiceErrors = $allFields
            ->filter(fn ($f) => in_array($f->field_type, ['select', 'radio', 'checkbox']))
            ->filter(fn ($f) => $f->fieldOptions->count() < 2)
            ->map(fn ($f) => "Field \"{$f->label}\" needs at least 2 options.");

        if ($choiceErrors->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => 'Cannot publish: some fields have issues.',
                'errors'  => $choiceErrors->values(),
            ], 422);
        }

        $form->publish();

        AuditLog::create([
            'actor'      => $request->user()->name,
            'role'       => ucfirst($request->user()->role),
            'action'     => 'form_published',
            'module'     => 'Forms',
            'target'     => $form->title,
            'context'    => ['published_by' => $request->user()->name],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => $this->present($form->fresh()->loadCount('responses'))]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');
        $form = Form::findOrFail($id);
        $form->close();

        AuditLog::create([
            'actor'      => $request->user()->name,
            'role'       => ucfirst($request->user()->role),
            'action'     => 'form_closed',
            'module'     => 'Forms',
            'target'     => $form->title,
            'context'    => [],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => $this->present($form->fresh()->loadCount('responses'))]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');
        $form = Form::findOrFail($id);

        if (!$form->is_active && $form->fields()->count() === 0) {
            return response()->json([
                'success' => false, 'code' => 422,
                'message' => 'A form must have at least one field before it can be activated.',
            ], 422);
        }

        if (!$form->is_active && !$form->allChoiceFieldsHaveOptions()) {
            return response()->json([
                'success' => false, 'code' => 422,
                'message' => 'All select, radio, and checkbox fields must have at least two options.',
            ], 422);
        }

        $form->update(['is_active' => !$form->is_active]);
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
                'success' => false, 'code' => 422,
                'message' => 'Token regeneration is only available for public forms.',
            ], 422);
        }

        $newToken = (string) Str::uuid();
        $form->update(['public_token' => $newToken]);

        AuditLog::create([
            'actor'      => $request->user()->name,
            'role'       => ucfirst($request->user()->role),
            'action'     => 'form_token_regenerated',
            'module'     => 'Forms',
            'target'     => $form->title,
            'context'    => ['new_token' => $newToken],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => ['public_token' => $newToken]]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Sections CRUD
    // ──────────────────────────────────────────────────────────────────────────

    public function storeSections(Request $request, int $formId): JsonResponse
    {
        $form      = Form::findOrFail($formId);
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $maxOrder  = $form->sections()->max('sort_order') ?? -1;
        $section   = $form->sections()->create(array_merge($validated, ['sort_order' => $maxOrder + 1]));

        return response()->json(['data' => $this->presentSection($section)], 201);
    }

    public function updateSection(Request $request, int $formId, int $sectionId): JsonResponse
    {
        $section   = FormSection::where('form_id', $formId)->findOrFail($sectionId);
        $validated = $request->validate([
            'title'       => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $section->update($validated);

        return response()->json(['data' => $this->presentSection($section->fresh())]);
    }

    public function destroySection(Request $request, int $formId, int $sectionId): JsonResponse
    {
        $section = FormSection::where('form_id', $formId)->findOrFail($sectionId);
        $section->delete(); // cascades to fields via DB FK

        return response()->json(['message' => 'Section deleted.']);
    }

    public function reorderSections(Request $request, int $formId): JsonResponse
    {
        $validated = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        foreach ($validated['order'] as $index => $sectionId) {
            FormSection::where('form_id', $formId)->where('id', $sectionId)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['message' => 'Sections reordered.']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Fields CRUD (delegated from FieldController; inline here for simplicity)
    // ──────────────────────────────────────────────────────────────────────────

    public function storeField(Request $request, int $formId): JsonResponse
    {
        $form      = Form::findOrFail($formId);
        $validated = $request->validate([
            'section_id'     => ['nullable', 'integer', 'exists:form_sections,id'],
            'label'          => ['required', 'string', 'max:191'],
            'field_name'     => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'field_type'     => ['required', 'in:text,number,email,select,radio,checkbox,textarea,date,file'],
            'placeholder'    => ['nullable', 'string', 'max:191'],
            'options'        => ['nullable', 'array'],
            'options.*'      => ['string', 'max:500'],
            'is_required'    => ['boolean'],
            'min_value'      => ['nullable', 'string', 'max:50'],
            'max_value'      => ['nullable', 'string', 'max:50'],
            'min_length'     => ['nullable', 'integer', 'min:0'],
            'max_length'     => ['nullable', 'integer', 'min:1'],
            'accepted_types' => ['nullable', 'string', 'max:191'],
            'max_file_size'  => ['nullable', 'integer', 'min:1'],
        ]);

        $validated['form_id'] = $formId;

        // Uniqueness of field_name within the form
        if (FormField::where('form_id', $formId)->where('field_name', $validated['field_name'])->exists()) {
            return response()->json([
                'success' => false, 'code' => 422,
                'message' => "Field name '{$validated['field_name']}' is already used in this form.",
            ], 422);
        }

        // If no section given, attach to the first section
        if (empty($validated['section_id'])) {
            $section = $form->sections()->orderBy('sort_order')->first()
                ?? $form->sections()->create(['title' => 'Section 1', 'sort_order' => 0]);
            $validated['section_id'] = $section->id;
        }

        $maxOrder            = $form->fields()->max('sort_order') ?? -1;
        $validated['sort_order'] = $maxOrder + 1;

        $options = $validated['options'] ?? null;
        unset($validated['options']);

        $field = FormField::create($validated);

        // Persist options to normalized table
        if ($options) {
            foreach ($options as $i => $val) {
                $field->fieldOptions()->create(['option_value' => $val, 'sort_order' => $i]);
            }
            // Keep JSON cache in sync
            $field->update(['options' => $options]);
        }

        return response()->json(['data' => $this->presentField($field->load('fieldOptions', 'conditions'))], 201);
    }

    public function updateField(Request $request, int $formId, int $fieldId): JsonResponse
    {
        $field     = FormField::where('form_id', $formId)->findOrFail($fieldId);
        $validated = $request->validate([
            'section_id'     => ['nullable', 'integer', 'exists:form_sections,id'],
            'label'          => ['sometimes', 'required', 'string', 'max:191'],
            'field_name'     => ['sometimes', 'required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'field_type'     => ['sometimes', 'required', 'in:text,number,email,select,radio,checkbox,textarea,date,file'],
            'placeholder'    => ['nullable', 'string', 'max:191'],
            'options'        => ['nullable', 'array'],
            'options.*'      => ['string', 'max:500'],
            'is_required'    => ['sometimes', 'boolean'],
            'min_value'      => ['nullable', 'string', 'max:50'],
            'max_value'      => ['nullable', 'string', 'max:50'],
            'min_length'     => ['nullable', 'integer', 'min:0'],
            'max_length'     => ['nullable', 'integer', 'min:1'],
            'accepted_types' => ['nullable', 'string', 'max:191'],
            'max_file_size'  => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['options'])) {
            $options = $validated['options'];
            unset($validated['options']);

            // Replace normalized options
            $field->fieldOptions()->delete();
            foreach ($options as $i => $val) {
                $field->fieldOptions()->create(['option_value' => $val, 'sort_order' => $i]);
            }
            $validated['options'] = $options; // sync JSON cache
        }

        $field->update($validated);

        return response()->json(['data' => $this->presentField($field->fresh()->load('fieldOptions', 'conditions'))]);
    }

    public function destroyField(int $formId, int $fieldId): JsonResponse
    {
        $field = FormField::where('form_id', $formId)->findOrFail($fieldId);
        $field->delete();

        return response()->json(['message' => 'Field deleted.']);
    }

    public function reorderFields(Request $request, int $formId): JsonResponse
    {
        $validated = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        foreach ($validated['order'] as $index => $fieldId) {
            FormField::where('form_id', $formId)->where('id', $fieldId)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['message' => 'Fields reordered.']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Analytics
    // ──────────────────────────────────────────────────────────────────────────

    public function analytics(int $id): JsonResponse
    {
        abort_if($id < 1, 400, 'Invalid form ID.');
        $form = Form::findOrFail($id);

        $total         = $form->responses()->count();
        $authenticated = $form->responses()->where('is_authenticated', true)->count();
        $anonymous     = $total - $authenticated;

        // Submissions per day — last 30 days
        $byDay = $form->responses()
            ->selectRaw('DATE(submitted_at) as date, COUNT(*) as count')
            ->where('submitted_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'count' => (int) $row->count]);

        // Fill zeros for days with no submissions
        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $date    = now()->subDays($i)->toDateString();
            $found   = $byDay->firstWhere('date', $date);
            $days[]  = ['date' => $date, 'count' => $found ? $found['count'] : 0];
        }

        $totalFields    = $form->fields()->count();
        $requiredFields = $form->fields()->where('is_required', true)->count();

        return response()->json([
            'data' => [
                'total'          => $total,
                'authenticated'  => $authenticated,
                'anonymous'      => $anonymous,
                'by_day'         => $days,
                'total_fields'   => $totalFields,
                'required_fields' => $requiredFields,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Presenters
    // ──────────────────────────────────────────────────────────────────────────

    private function present(Form $form): array
    {
        return [
            'id'              => $form->id,
            'title'           => $form->title,
            'description'     => $form->description,
            'status'          => $form->status ?? 'draft',
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

    private function presentWithSections(Form $form): array
    {
        return array_merge($this->present($form), [
            'sections' => $form->sections->map(fn (FormSection $s) => $this->presentSection($s))->values(),
        ]);
    }

    private function presentSection(FormSection $section): array
    {
        return [
            'id'          => $section->id,
            'form_id'     => $section->form_id,
            'title'       => $section->title,
            'description' => $section->description,
            'sort_order'  => $section->sort_order,
            'fields'      => ($section->relationLoaded('fields')
                ? $section->fields->map(fn (FormField $f) => $this->presentField($f))->values()
                : []),
        ];
    }

    private function presentField(FormField $field): array
    {
        return [
            'id'             => $field->id,
            'form_id'        => $field->form_id,
            'section_id'     => $field->section_id,
            'label'          => $field->label,
            'field_name'     => $field->field_name,
            'field_type'     => $field->field_type,
            'placeholder'    => $field->placeholder,
            'options'        => $field->relationLoaded('fieldOptions')
                                 ? $field->fieldOptions->pluck('option_value')->toArray()
                                 : ($field->options ?? []),
            'is_required'    => $field->is_required,
            'min_value'      => $field->min_value,
            'max_value'      => $field->max_value,
            'min_length'     => $field->min_length,
            'max_length'     => $field->max_length,
            'accepted_types' => $field->accepted_types,
            'max_file_size'  => $field->max_file_size,
            'sort_order'     => $field->sort_order,
            'is_locked'      => $field->is_locked,
            'conditions'     => $field->relationLoaded('conditions')
                                 ? $field->conditions->map(fn ($c) => [
                                     'id'              => $c->id,
                                     'source_field_id' => $c->source_field_id,
                                     'operator'        => $c->operator,
                                     'condition_value' => $c->condition_value,
                                 ])->values()
                                 : [],
        ];
    }
}
