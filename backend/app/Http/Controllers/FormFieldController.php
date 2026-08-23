<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormFieldController extends Controller
{
    public function store(Request $request, int $formId): JsonResponse
    {
        abort_if($formId < 1, 400, 'Invalid form ID.');

        $form = Form::findOrFail($formId);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:191'],
            'field_name' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'field_type' => ['required', 'in:text,number,email,select,radio,checkbox,textarea,date,file'],
            'placeholder' => ['nullable', 'string', 'max:191'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:191'],
            'is_required' => ['boolean'],
            'min_value' => ['nullable', 'max:50'],
            'max_value' => ['nullable', 'max:50'],
            'min_length' => ['nullable', 'integer', 'min:0'],
            'max_length' => ['nullable', 'integer', 'min:1'],
            'accepted_types' => ['nullable', 'string', 'max:191'],
            'max_file_size' => ['nullable', 'integer', 'min:1'],
        ]);

        if (array_key_exists('min_value', $validated)) {
            $validated['min_value'] = ($validated['min_value'] !== null && $validated['min_value'] !== '') ? (string) $validated['min_value'] : null;
        }
        if (array_key_exists('max_value', $validated)) {
            $validated['max_value'] = ($validated['max_value'] !== null && $validated['max_value'] !== '') ? (string) $validated['max_value'] : null;
        }

        // Enforce unique field_name within this form
        $exists = FormField::where('form_id', $formId)
            ->where('field_name', $validated['field_name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'code' => 422,
                'message' => 'Validation failed',
                'errors' => ['field_name' => ['This field name is already used in this form.']],
            ], 422);
        }

        // Auto-assign sort_order
        $maxOrder = FormField::where('form_id', $formId)->max('sort_order') ?? -1;
        $validated['form_id'] = $formId;
        $validated['sort_order'] = $maxOrder + 1;
        $validated['is_required'] = $validated['is_required'] ?? true;

        $field = FormField::create($validated);

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => ucfirst($request->user()->role),
            'action' => 'form_field_added',
            'module' => 'Forms',
            'target' => "Form #{$formId}",
            'context' => ['label' => $field->label, 'field_type' => $field->field_type, 'form_id' => $formId],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => $this->present($field)], 201);
    }

    public function update(Request $request, int $formId, int $fieldId): JsonResponse
    {
        abort_if($formId < 1 || $fieldId < 1, 400, 'Invalid ID.');

        $field = FormField::where('form_id', $formId)->findOrFail($fieldId);

        // Locked fields: only label and placeholder can be edited
        if ($field->is_locked) {
            $validated = $request->validate([
                'label' => ['sometimes', 'required', 'string', 'max:191'],
                'placeholder' => ['nullable', 'string', 'max:191'],
            ]);
        } else {
            $validated = $request->validate([
                'label' => ['sometimes', 'required', 'string', 'max:191'],
                'field_name' => ['sometimes', 'required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
                'field_type' => ['sometimes', 'required', 'in:text,number,email,select,radio,checkbox,textarea,date,file'],
                'placeholder' => ['nullable', 'string', 'max:191'],
                'options' => ['nullable', 'array'],
                'options.*' => ['string', 'max:191'],
                'is_required' => ['boolean'],
                'min_value' => ['nullable', 'max:50'],
                'max_value' => ['nullable', 'max:50'],
                'min_length' => ['nullable', 'integer', 'min:0'],
                'max_length' => ['nullable', 'integer', 'min:1'],
                'accepted_types' => ['nullable', 'string', 'max:191'],
                'max_file_size' => ['nullable', 'integer', 'min:1'],
            ]);

            if (array_key_exists('min_value', $validated)) {
                $validated['min_value'] = ($validated['min_value'] !== null && $validated['min_value'] !== '') ? (string) $validated['min_value'] : null;
            }
            if (array_key_exists('max_value', $validated)) {
                $validated['max_value'] = ($validated['max_value'] !== null && $validated['max_value'] !== '') ? (string) $validated['max_value'] : null;
            }

            // Check unique field_name if being changed
            if (
                isset($validated['field_name']) &&
                $validated['field_name'] !== $field->field_name &&
                FormField::where('form_id', $formId)->where('field_name', $validated['field_name'])->exists()
            ) {
                return response()->json([
                    'success' => false,
                    'code' => 422,
                    'message' => 'Validation failed',
                    'errors' => ['field_name' => ['This field name is already used in this form.']],
                ], 422);
            }
        }

        $before = $field->only(array_keys($validated));
        $field->update($validated);

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => ucfirst($request->user()->role),
            'action' => 'form_field_updated',
            'module' => 'Forms',
            'target' => "Form #{$formId} / Field #{$fieldId}",
            'context' => ['before' => $before, 'after' => $field->fresh()->only(array_keys($validated))],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => $this->present($field->fresh())]);
    }

    public function destroy(Request $request, int $formId, int $fieldId): JsonResponse
    {
        abort_if($formId < 1 || $fieldId < 1, 400, 'Invalid ID.');

        $field = FormField::where('form_id', $formId)->findOrFail($fieldId);

        if ($field->is_locked) {
            return response()->json([
                'success' => false,
                'code' => 422,
                'message' => 'This field cannot be deleted because the form has existing responses.',
            ], 422);
        }

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => ucfirst($request->user()->role),
            'action' => 'form_field_deleted',
            'module' => 'Forms',
            'target' => "Form #{$formId} / Field #{$fieldId}",
            'context' => ['label' => $field->label, 'deleted_by' => $request->user()->name],
            'ip_address' => $request->ip(),
        ]);

        $field->delete();

        return response()->json(['message' => 'Field removed.']);
    }

    /**
     * Update sort_order for all fields in bulk.
     */
    public function reorder(Request $request, int $formId): JsonResponse
    {
        abort_if($formId < 1, 400, 'Invalid form ID.');

        Form::findOrFail($formId);

        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer', 'min:1'],
        ]);

        $order = $validated['order']; // [ field_id => sort_order, ... ]

        foreach ($order as $fieldId => $sortOrder) {
            if (! is_numeric($fieldId) || $fieldId < 1) {
                continue;
            }

            FormField::where('form_id', $formId)
                ->where('id', (int) $fieldId)
                ->update(['sort_order' => (int) $sortOrder]);
        }

        AuditLog::create([
            'actor' => $request->user()->name,
            'role' => ucfirst($request->user()->role),
            'action' => 'form_fields_reordered',
            'module' => 'Forms',
            'target' => "Form #{$formId}",
            'context' => ['new_order' => $order],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Field order updated.']);
    }

    private function present(FormField $field): array
    {
        return [
            'id' => $field->id,
            'form_id' => $field->form_id,
            'label' => $field->label,
            'field_name' => $field->field_name,
            'field_type' => $field->field_type,
            'placeholder' => $field->placeholder,
            'options' => $field->options,
            'is_required' => $field->is_required,
            'min_value' => $field->min_value,
            'max_value' => $field->max_value,
            'min_length' => $field->min_length,
            'max_length' => $field->max_length,
            'accepted_types' => $field->accepted_types,
            'max_file_size' => $field->max_file_size,
            'sort_order' => $field->sort_order,
            'is_locked' => $field->is_locked,
        ];
    }
}
