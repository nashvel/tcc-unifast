<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;
use App\Support\SecureUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FormSubmissionService
{
    private const BLOCKED_EXTENSIONS = [
        'exe', 'php', 'php3', 'php4', 'php5', 'phtml',
        'js', 'sh', 'bat', 'cmd', 'py', 'rb', 'pl',
        'asp', 'aspx', 'jsp', 'jar',
    ];

    private const DEFAULT_ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    private const DEFAULT_MAX_FILE_BYTES = 5 * 1024 * 1024; // 5 MB

    /**
     * Validate that submitted field names exactly match the form schema.
     * No extra fields, no missing required fields.
     *
     * @param  array<string, mixed>  $submitted
     *
     * @throws ValidationException
     */
    public function validateSchema(Form $form, array $submitted): void
    {
        /** @var \Illuminate\Support\Collection<int, FormField> $fields */
        $fields = $form->fields()->get();

        $schemaNames = $fields->pluck('field_name')->all();
        $submittedNames = array_keys($submitted);

        // Reject extra fields not in schema
        $extra = array_diff($submittedNames, $schemaNames);
        if (! empty($extra)) {
            throw ValidationException::withMessages([
                'fields' => ['Submitted fields do not match the form schema.'],
            ]);
        }

        $errors = [];

        foreach ($fields as $field) {
            $name  = $field->field_name;
            $value = $submitted[$name] ?? null;
            $blank = $value === null || $value === '' || $value === [];

            // Required check
            if ($field->is_required && $blank) {
                $errors[$name][] = "{$field->label} is required.";
                continue;
            }

            if ($blank) {
                continue;
            }

            // Type-specific validation
            $fieldErrors = $this->validateFieldValue($field, $value);
            if (! empty($fieldErrors)) {
                $errors[$name] = $fieldErrors;
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function validateFieldValue(FormField $field, mixed $value): array
    {
        $errors = [];
        $type   = $field->field_type;

        match ($type) {
            'number' => $this->validateNumber($field, $value, $errors),
            'email'  => $this->validateEmail($field, $value, $errors),
            'select', 'radio' => $this->validateSingleChoice($field, $value, $errors),
            'checkbox' => $this->validateMultiChoice($field, $value, $errors),
            'date'   => $this->validateDate($field, $value, $errors),
            'text', 'textarea' => $this->validateText($field, $value, $errors),
            default  => null,
        };

        return $errors;
    }

    /** @param list<string> $errors */
    private function validateNumber(FormField $field, mixed $value, array &$errors): void
    {
        if (! is_numeric($value)) {
            $errors[] = "{$field->label} must be a number.";
            return;
        }

        $num = (float) $value;

        if ($field->min_value !== null && $num < (float) $field->min_value) {
            $errors[] = "{$field->label} must be at least {$field->min_value}.";
        }

        if ($field->max_value !== null && $num > (float) $field->max_value) {
            $errors[] = "{$field->label} must be at most {$field->max_value}.";
        }
    }

    /** @param list<string> $errors */
    private function validateEmail(FormField $field, mixed $value, array &$errors): void
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "{$field->label} must be a valid email address.";
        }

        if (mb_strlen((string) $value) > 191) {
            $errors[] = "{$field->label} is too long.";
        }
    }

    /** @param list<string> $errors */
    private function validateSingleChoice(FormField $field, mixed $value, array &$errors): void
    {
        $allowed = is_array($field->options) ? $field->options : [];

        if (! in_array($value, $allowed, true)) {
            $errors[] = "Selected value for {$field->label} is not a valid option.";
        }
    }

    /** @param list<string> $errors */
    private function validateMultiChoice(FormField $field, mixed $value, array &$errors): void
    {
        if (! is_array($value)) {
            $errors[] = "{$field->label} must be an array of selected options.";
            return;
        }

        if ($field->is_required && empty($value)) {
            $errors[] = "At least one option must be selected for {$field->label}.";
            return;
        }

        $allowed = is_array($field->options) ? $field->options : [];

        foreach ($value as $selected) {
            if (! in_array($selected, $allowed, true)) {
                $errors[] = "Selected value for {$field->label} is not a valid option.";
                break;
            }
        }
    }

    /** @param list<string> $errors */
    private function validateDate(FormField $field, mixed $value, array &$errors): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
            $errors[] = "{$field->label} must be a valid date (YYYY-MM-DD).";
            return;
        }

        if ($field->min_value && $value < $field->min_value) {
            $errors[] = "{$field->label} must be on or after {$field->min_value}.";
        }

        if ($field->max_value && $value > $field->max_value) {
            $errors[] = "{$field->label} must be on or before {$field->max_value}.";
        }
    }

    /** @param list<string> $errors */
    private function validateText(FormField $field, mixed $value, array &$errors): void
    {
        $len = mb_strlen((string) $value);

        if ($field->min_length !== null && $len < $field->min_length) {
            $errors[] = "{$field->label} must be at least {$field->min_length} characters.";
        }

        if ($field->max_length !== null && $len > $field->max_length) {
            $errors[] = "{$field->label} must be at most {$field->max_length} characters.";
        }
    }

    /**
     * Store an uploaded file for a form file-field.
     * Returns the storage path (outside public directory).
     *
     * @throws ValidationException
     */
    public function storeFileField(
        UploadedFile $file,
        FormField $field,
        string $fieldName,
    ): string {
        // Block dangerous extensions regardless of MIME
        $ext = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                $fieldName => ["File type .{$ext} is not allowed."],
            ]);
        }

        // Parse accepted types from field config
        $allowedMimes = self::DEFAULT_ALLOWED_MIMES;

        if ($field->accepted_types) {
            $parsed = array_filter(array_map('trim', explode(',', $field->accepted_types)));

            if (! empty($parsed)) {
                $allowedMimes = $parsed;
            }
        }

        // Validate MIME via finfo (magic bytes + MIME)
        SecureUpload::assertAllowedMime($file, $allowedMimes, $fieldName);

        // Check file size
        $maxBytes = $field->max_file_size
            ? $field->max_file_size * 1024
            : self::DEFAULT_MAX_FILE_BYTES;

        if ($file->getSize() > $maxBytes) {
            $maxMb = round($maxBytes / 1024 / 1024, 1);
            throw ValidationException::withMessages([
                $fieldName => ["File must not exceed {$maxMb} MB."],
            ]);
        }

        // Store with UUID filename in private disk (outside public)
        $uuid      = (string) Str::uuid();
        $extension = match ($file->getMimeType()) {
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            default           => 'bin',
        };

        $path = "form-uploads/{$uuid}.{$extension}";
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Build a SHA-256 hash of the submission for tamper detection.
     */
    public function buildResponseHash(Form $form, ?int $granteeId, array $responses): string
    {
        $payload = json_encode([
            'form_id'    => $form->id,
            'grantee_id' => $granteeId,
            'responses'  => $responses,
        ], JSON_UNESCAPED_UNICODE | JSON_SORT_KEYS);

        return hash('sha256', (string) $payload);
    }

    /**
     * Check whether a grantee has already reached max submissions for this form.
     */
    public function hasReachedLimit(Form $form, ?int $granteeId): bool
    {
        if (! $granteeId) {
            return false;
        }

        $max = $form->max_submissions ?? 1;

        $count = $form->responses()
            ->where('grantee_id', $granteeId)
            ->count();

        return $count >= $max;
    }
}
