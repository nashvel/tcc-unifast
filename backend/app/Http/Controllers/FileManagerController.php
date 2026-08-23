<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\DocumentSubmission;
use App\Models\MasterlistImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileManagerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tab = $request->query('tab', 'requirements');
        if (! in_array($tab, ['requirements', 'imports'], true)) {
            $tab = 'requirements';
        }

        $search = trim((string) $request->query('search', ''));
        $batchId = $request->query('batch_id');
        $batchId = is_numeric($batchId) && (int) $batchId > 0 ? (int) $batchId : null;
        $perPage = min(100, max(10, (int) $request->query('per_page', 50)));
        $page = max(1, (int) $request->query('page', 1));

        if ($tab === 'imports') {
            return $this->importsIndex($search, $batchId, $perPage, $page);
        }

        return $this->requirementsIndex($search, $batchId, $perPage, $page);
    }

    public function downloadImport(Request $request, MasterlistImport $import): StreamedResponse|Response
    {
        abort_unless(in_array($request->user()->role, ['developer', 'admin', 'head', 'staff'], true), 403);

        $relative = (string) $import->stored_path;
        abort_unless($relative !== '' && Storage::disk('local')->exists($relative), 404);

        $name = $import->original_name ?: ('masterlist_import_'.$import->id.'.csv');

        return Storage::disk('local')->download($relative, $name, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function requirementsIndex(?string $search, ?int $batchId, int $perPage, int $page): JsonResponse
    {
        $query = DocumentSubmission::query()
            ->with(['batch:id,name', 'grantee:id,student_id,full_name'])
            ->orderByDesc('created_at');

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder
                    ->where('original_name', 'like', $like)
                    ->orWhere('student_name', 'like', $like)
                    ->orWhere('student_id', 'like', $like)
                    ->orWhere('document_type', 'like', $like)
                    ->orWhere('slot_key', 'like', $like);
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($paginator->items())->map(function (DocumentSubmission $doc) {
            $size = (int) ($doc->file_size ?? 0);
            $mime = (string) ($doc->mime_type ?? '');
            $category = str_starts_with($mime, 'image/') ? 'image' : 'document';
            $fileUrl = '/api/document-submissions/'.$doc->id.'/file/primary';

            return [
                'kind' => 'requirement',
                'id' => 'doc_'.$doc->id,
                'submission_id' => $doc->id,
                'batch_id' => $doc->batch_id,
                'batch_name' => $doc->batch?->name,
                'grantee_id' => $doc->grantee_id,
                'student_id' => $doc->student_id,
                'student_name' => $doc->student_name ?: ($doc->grantee?->full_name),
                'slot_key' => $doc->slot_key,
                'document_type' => $doc->document_type,
                'status' => $doc->status,
                'name' => $doc->original_name,
                'mime_type' => $mime,
                'category' => $category,
                'size' => $this->formatBytes($size),
                'size_bytes' => $size,
                'created_at' => $doc->created_at,
                'preview_url' => $fileUrl,
                'download_url' => $fileUrl,
                'package_path' => ($doc->grantee_id && $doc->batch_id)
                    ? '/app/documents/package/'.$doc->grantee_id.'/'.$doc->batch_id
                    : ($doc->id ? '/app/documents/'.$doc->id : null),
            ];
        })->values();

        $summaryQuery = DocumentSubmission::query();
        if ($batchId) {
            $summaryQuery->where('batch_id', $batchId);
        }
        $totalFiles = (clone $summaryQuery)->count();
        $totalImages = (clone $summaryQuery)->where('mime_type', 'like', 'image/%')->count();
        $totalDocs = max(0, $totalFiles - $totalImages);
        $totalBytes = (int) (clone $summaryQuery)->sum('file_size');

        return response()->json([
            'data' => $rows,
            'tab' => 'requirements',
            'summary' => [
                'total_files' => number_format($totalFiles),
                'documents' => number_format($totalDocs),
                'images' => number_format($totalImages),
                'storage_used' => $this->formatBytes($totalBytes),
            ],
            'batches' => $this->batchOptions(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function importsIndex(string $search, ?int $batchId, int $perPage, int $page): JsonResponse
    {
        $query = MasterlistImport::query()
            ->with(['uploader:id,name', 'batch:id,name'])
            ->orderByDesc('created_at');

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder
                    ->where('original_name', 'like', $like)
                    ->orWhereHas('uploader', fn ($u) => $u->where('name', 'like', $like));
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($paginator->items())->map(function (MasterlistImport $import) {
            $size = 0;
            if (is_string($import->stored_path) && $import->stored_path !== '' && Storage::disk('local')->exists($import->stored_path)) {
                $size = (int) Storage::disk('local')->size($import->stored_path);
            }
            $downloadUrl = '/api/files/imports/'.$import->id.'/download';

            return [
                'kind' => 'import',
                'id' => 'import_'.$import->id,
                'import_id' => $import->id,
                'batch_id' => $import->batch_id,
                'batch_name' => $import->batch?->name,
                'name' => $import->original_name ?: 'masterlist_import_'.$import->id.'.csv',
                'category' => 'spreadsheet',
                'owner' => $import->uploader?->name ?: 'System Admin',
                'status' => $import->status,
                'size' => $size > 0 ? $this->formatBytes($size) : 'Spreadsheet',
                'size_bytes' => $size,
                'created_at' => $import->created_at,
                'preview_url' => null,
                'download_url' => $downloadUrl,
            ];
        })->values();

        $summaryQuery = MasterlistImport::query();
        if ($batchId) {
            $summaryQuery->where('batch_id', $batchId);
        }
        $totalFiles = (clone $summaryQuery)->count();
        $totalBytes = 0;
        foreach ((clone $summaryQuery)->get(['stored_path']) as $import) {
            if (is_string($import->stored_path) && $import->stored_path !== '' && Storage::disk('local')->exists($import->stored_path)) {
                $totalBytes += (int) Storage::disk('local')->size($import->stored_path);
            }
        }

        return response()->json([
            'data' => $rows,
            'tab' => 'imports',
            'summary' => [
                'total_files' => number_format($totalFiles),
                'documents' => '0',
                'images' => '0',
                'storage_used' => $this->formatBytes($totalBytes),
            ],
            'batches' => $this->batchOptions(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    private function batchOptions(): array
    {
        return Batch::query()
            ->orderByDesc('id')
            ->get(['id', 'name'])
            ->map(fn (Batch $batch) => [
                'id' => $batch->id,
                'name' => $batch->name,
            ])
            ->values()
            ->all();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 KB';
        }
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return number_format(max(1, $bytes / 1024), 0).' KB';
    }
}
