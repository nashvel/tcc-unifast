<?php

namespace App\Http\Controllers;

use App\Mail\GranteeActivationInviteMail;
use App\Models\ActivationToken;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\MasterlistImport;
use App\Models\MasterlistRow;
use App\Models\User;
use App\Services\MasterlistSpreadsheetParser;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MasterlistImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
        ]);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $query = MasterlistImport::query()
            ->with('batch')
            ->latest('id');

        if (! empty($validated['batch_id'])) {
            $query->where('batch_id', $validated['batch_id']);
        }

        $paginator = $query->paginate($perPage);
        $rows = collect($paginator->items())->map(fn (MasterlistImport $import) => [
            'id' => $import->id,
            'status' => $import->status,
            'original_name' => $import->original_name,
            'total_rows' => $import->total_rows,
            'valid_rows' => $import->valid_rows,
            'invalid_rows' => $import->invalid_rows,
            'imported_rows' => $import->imported_rows,
            'created_at' => $import->created_at,
            'batch' => $import->batch ? [
                'id' => $import->batch->id,
                'name' => $import->batch->name,
                'academic_year' => $import->batch->academic_year,
                'semester' => $import->batch->semester,
            ] : null,
        ]);

        return PaginatedJson::from($paginator, $rows->values());
    }

    public function preview(Request $request, MasterlistSpreadsheetParser $parser): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:20480'],
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
        ]);

        $batch = Batch::query()->findOrFail($validated['batch_id']);
        if (! $batch->submission_deadline) {
            throw ValidationException::withMessages([
                'batch_id' => 'Select a batch that already has a submission deadline before importing.',
            ]);
        }

        $file = $validated['file'];
        $storedPath = $file->store('masterlist-imports', 'local');
        $parsedRows = $parser->parse($file);

        $import = MasterlistImport::create([
            'batch_id' => $batch->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => 'previewed',
        ]);

        $seenStudentIds = [];
        $seenStudentNumbers = [];
        $validRows = 0;
        $invalidRows = 0;

        foreach ($parsedRows as $index => $row) {
            $normalized = $this->normalizeRow($row);
            $errors = $this->rowErrors($normalized, $seenStudentIds, $seenStudentNumbers);
            $status = $errors === [] ? 'valid' : 'invalid';
            $validRows += $status === 'valid' ? 1 : 0;
            $invalidRows += $status === 'invalid' ? 1 : 0;

            if (($normalized['student_id'] ?? '') !== '') {
                $seenStudentIds[] = $normalized['student_id'];
            }
            if (($normalized['student_number'] ?? '') !== '') {
                $seenStudentNumbers[] = $normalized['student_number'];
            }

            MasterlistRow::create([
                'masterlist_import_id' => $import->id,
                'row_number' => $index + 2,
                ...$normalized,
                'status' => $status,
                'errors' => $errors,
                'raw_payload' => $row,
            ]);
        }

        $import->update([
            'total_rows' => count($parsedRows),
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
        ]);

        return response()->json(['data' => $this->presentImport($import->fresh(['batch', 'rows']))]);
    }

    public function show(MasterlistImport $import): JsonResponse
    {
        return response()->json(['data' => $this->presentImport($import->load(['batch', 'rows']))]);
    }

    public function confirm(Request $request, MasterlistImport $import): JsonResponse
    {
        abort_unless(in_array($request->user()->role, ['developer', 'admin'], true), 403);

        if ($import->status === 'imported') {
            return response()->json(['data' => $this->presentImport($import->load(['batch', 'rows']))]);
        }

        $batch = $import->batch;
        if (! $batch || ! $batch->submission_deadline) {
            throw ValidationException::withMessages([
                'batch_id' => 'The import batch must exist and include a submission deadline.',
            ]);
        }

        $sent = 0;
        $failed = [];

        DB::transaction(function () use ($import, &$sent, &$failed): void {
            $import->load(['batch', 'rows']);
            foreach ($import->rows()->where('status', 'valid')->get() as $row) {
                $temporaryPassword = $this->temporaryPassword();
                $user = User::create([
                    'name' => $row->full_name,
                    'email' => $row->email,
                    'role' => 'student',
                    'student_id' => $row->student_id,
                    'account_status' => 'unverified',
                    'password' => Hash::make($temporaryPassword),
                ]);

                Grantee::create([
                    'user_id' => $user->id,
                    'batch_id' => $import->batch_id,
                    'student_id' => $row->student_id,
                    'student_number' => $row->student_number,
                    'full_name' => $row->full_name,
                    'email' => $row->email,
                    'program' => $row->program,
                    'year_level' => $row->year_level,
                    'status' => 'unverified',
                ]);

                $plainToken = Str::random(48);
                ActivationToken::create([
                    'user_id' => $user->id,
                    'token_hash' => hash('sha256', $plainToken),
                    'expires_at' => now()->addDays(7),
                ]);

                try {
                    $this->sendActivationEmail($user, $temporaryPassword, $plainToken);
                    $sent++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failed[] = ['email' => $user->email, 'message' => $exception->getMessage()];
                }
            }

            $import->update([
                'status' => 'imported',
                'imported_rows' => $import->rows()->where('status', 'valid')->count(),
            ]);
        });

        return response()->json([
            'data' => $this->presentImport($import->fresh(['batch', 'rows'])),
            'mail' => ['sent' => $sent, 'failed' => $failed],
        ], $failed === [] ? 200 : 207);
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string|null>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'student_id' => $row['student_id'] ?? null,
            'student_number' => $row['student_number'] ?? null,
            'full_name' => $row['full_name'] ?? null,
            'email' => $row['email'] ?? null,
            'program' => $row['program'] ?? null,
            'year_level' => $row['year_level'] ?? null,
        ];
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  list<string>  $seenStudentIds
     * @param  list<string>  $seenStudentNumbers
     * @return list<string>
     */
    private function rowErrors(array $row, array $seenStudentIds, array $seenStudentNumbers): array
    {
        $errors = [];
        foreach (['student_id', 'full_name', 'email', 'program', 'year_level'] as $field) {
            if (($row[$field] ?? '') === '') {
                $errors[] = "Missing {$field}.";
            }
        }
        if (($row['email'] ?? '') !== '' && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }
        if (($row['student_id'] ?? '') !== '' && in_array($row['student_id'], $seenStudentIds, true)) {
            $errors[] = 'Duplicate student ID in file.';
        }
        if (($row['student_number'] ?? '') !== '' && in_array($row['student_number'], $seenStudentNumbers, true)) {
            $errors[] = 'Duplicate student number in file.';
        }
        if (($row['student_id'] ?? '') !== '' && User::query()->where('student_id', $row['student_id'])->exists()) {
            $errors[] = 'Student ID already has an account.';
        }
        if (($row['student_id'] ?? '') !== '' && Grantee::query()->where('student_id', $row['student_id'])->exists()) {
            $errors[] = 'Student ID already exists in grantees.';
        }
        if (($row['email'] ?? '') !== '' && User::query()->where('email', $row['email'])->exists()) {
            $errors[] = 'Email already has an account.';
        }

        return $errors;
    }

    private function temporaryPassword(): string
    {
        return 'TCC-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
    }

    private function sendActivationEmail(User $user, string $temporaryPassword, string $plainToken): void
    {
        Mail::to($user->email, $user->name)->send(new GranteeActivationInviteMail(
            $user,
            $temporaryPassword,
            url('/activate/'.$plainToken),
        ));
    }

    private function presentImport(MasterlistImport $import): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status,
            'original_name' => $import->original_name,
            'total_rows' => $import->total_rows,
            'valid_rows' => $import->valid_rows,
            'invalid_rows' => $import->invalid_rows,
            'imported_rows' => $import->imported_rows,
            'batch' => $import->batch ? [
                'id' => $import->batch->id,
                'name' => $import->batch->name,
                'academic_year' => $import->batch->academic_year,
                'semester' => $import->batch->semester,
                'submission_deadline' => $import->batch->submission_deadline,
            ] : null,
            'rows' => $import->rows->map(fn (MasterlistRow $row) => [
                'id' => $row->id,
                'row_number' => $row->row_number,
                'student_id' => $row->student_id,
                'student_number' => $row->student_number,
                'full_name' => $row->full_name,
                'email' => $row->email,
                'program' => $row->program,
                'year_level' => $row->year_level,
                'status' => $row->status,
                'errors' => $row->errors ?? [],
            ])->values(),
        ];
    }
}
