<?php

namespace App\Http\Controllers;

use App\Mail\GranteeActivationInviteMail;
use App\Models\ActivationToken;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\MasterlistImport;
use App\Models\MasterlistRow;
use App\Models\User;
use App\Services\MasterlistImportPresenter;
use App\Services\MasterlistImportRowValidator;
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
    public function __construct(
        private readonly MasterlistImportPresenter $presenter,
        private readonly MasterlistImportRowValidator $rowValidator,
    ) {}

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
        $rows = collect($paginator->items())->map(
            fn (MasterlistImport $import) => $this->presenter->listRow($import)
        );

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

        $validPrograms = $this->rowValidator->activePrograms();

        foreach ($parsedRows as $index => $row) {
            $normalized = $this->rowValidator->normalize($row);
            $errors = $this->rowValidator->errors($normalized, $seenStudentIds, $seenStudentNumbers, $validPrograms);
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

        return response()->json(['data' => $this->presenter->import($import->fresh(['batch', 'rows']))]);
    }

    public function show(MasterlistImport $import): JsonResponse
    {
        return response()->json(['data' => $this->presenter->import($import->load(['batch', 'rows']))]);
    }

    public function confirm(Request $request, MasterlistImport $import): JsonResponse
    {
        abort_unless(in_array($request->user()->role, ['developer', 'admin', 'head', 'staff'], true), 403);

        if ($import->status === 'imported') {
            return response()->json(['data' => $this->presenter->import($import->load(['batch', 'rows']))]);
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
                    'student_number' => trim($row->student_number) === '' ? null : $row->student_number,
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
            'data' => $this->presenter->import($import->fresh(['batch', 'rows'])),
            'mail' => ['sent' => $sent, 'failed' => $failed],
        ], $failed === [] ? 200 : 207);
    }

    public function destroy(Request $request, MasterlistImport $import): JsonResponse
    {
        // Only allow deleting imports that haven't been completed yet (e.g. pending/failed)
        if ($import->status === 'completed') {
            return response()->json(['message' => 'Completed imports cannot be deleted.'], 403);
        }

        $import->delete();

        return response()->json(['message' => 'Import deleted successfully.']);
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
            $this->activationUrl($plainToken),
        ));
    }

    private function activationUrl(string $plainToken): string
    {
        $frontend = rtrim((string) (config('app.frontend_url') ?: env('FRONTEND_URL', 'http://localhost:5173')), '/');

        return $frontend.'/activate/'.$plainToken.'?lang=en';
    }
}
