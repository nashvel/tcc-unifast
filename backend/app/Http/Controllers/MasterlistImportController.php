<?php

namespace App\Http\Controllers;

use App\Mail\GranteeActivationInviteMail;
use App\Models\AcademicProgram;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\MasterlistImport;
use App\Models\User;
use App\Services\MasterlistImportPresenter;
use App\Services\MasterlistImportRecorder;
use App\Services\MasterlistSpreadsheetParser;
use App\Support\ActivationLink;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MasterlistImportController extends Controller
{
    public function __construct(
        private readonly MasterlistImportPresenter $presenter,
        private readonly MasterlistImportRecorder $recorder,
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
            'file' => [
                'required',
                'file',
                'mimes:csv,xlsx,xls,pdf,docx',
                'max:20480',
            ],
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

        // Use parseWithDetection so DOCX/PDF uploads return table detection metadata.
        $parsed = $parser->parseWithDetection($file);
        $parsedRows = $parsed['rows'];
        $detectionInfo = $parsed['detection_info'];

        $import = MasterlistImport::create([
            'batch_id' => $batch->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => 'previewed',
        ]);

        $this->recorder->recordDetection($import, $detectionInfo);
        $this->recorder->recordRows($import, $parsedRows);

        return response()->json(['data' => $this->presentImport($import->fresh(['batch', 'rows', 'detection.headers']))]);
    }

    public function show(MasterlistImport $import): JsonResponse
    {
        return response()->json(['data' => $this->presentImport($import->load(['batch', 'rows', 'detection.headers']))]);
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
                // Unusable hash: staged accounts hold no credential until the
                // student completes identity verification. Invites (and their
                // activation tokens) are sent later from the Onboarding Center.
                $user = User::create([
                    'name' => $row->full_name,
                    'email' => $row->email,
                    'role' => 'student',
                    'student_id' => $row->student_id,
                    'account_status' => 'unverified',
                    'password' => Hash::make(Str::random(64)),
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

                // Emails and activation tokens are no longer generated here.
                // They will be generated when the staff uses the Onboarding Center to blast invites.
            }

            $import->update([
                'status' => 'imported',
                'imported_rows' => $import->rows()->where('status', 'valid')->count(),
            ]);
        });

        return response()->json([
            'data' => $this->presentImport($import->fresh(['batch', 'rows'])),
            'message' => 'Successfully staged accounts for invitation.',
        ], 200);
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

    /**
     * Presentation lives in MasterlistImportPresenter; this stays as a shim so the
     * existing call sites read unchanged.
     *
     * @return array<string, mixed>
     */
    private function presentImport(MasterlistImport $import): array
    {
        return $this->presenter->import($import);
    }
}
