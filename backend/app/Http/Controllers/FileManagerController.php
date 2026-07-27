<?php

namespace App\Http\Controllers;

use App\Models\DocumentSubmission;
use App\Models\MasterlistImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileManagerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = DocumentSubmission::query()
            ->select('id', 'original_name', 'mime_type', 'file_size', 'student_name', 'created_at')
            ->get()
            ->map(function ($doc) {
                $size = $doc->file_size;
                $formattedSize = $size > 1048576 ? number_format($size / 1048576, 1) . ' MB' : number_format($size / 1024, 0) . ' KB';
                
                $category = 'document';
                if (str_starts_with($doc->mime_type, 'image/')) {
                    $category = 'image';
                }

                return [
                    'id' => 'doc_' . $doc->id,
                    'name' => $doc->original_name,
                    'category' => $category,
                    'owner' => $doc->student_name,
                    'size' => $formattedSize,
                    'size_bytes' => $size,
                    'created_at' => $doc->created_at,
                ];
            });

        $imports = MasterlistImport::query()
            ->with('uploader')
            ->select('id', 'uploaded_by', 'original_name', 'stored_path', 'created_at')
            ->get()
            ->map(function ($import) {
                $size = 0;
                $formattedSize = 'Spreadsheet';

                return [
                    'id' => 'import_' . $import->id,
                    'name' => $import->original_name ?: 'masterlist_import_' . $import->id . '.csv',
                    'category' => 'spreadsheet',
                    'owner' => $import->uploader ? $import->uploader->name : 'System Admin',
                    'size' => $formattedSize,
                    'size_bytes' => $size,
                    'created_at' => $import->created_at,
                ];
            });

        $allFiles = $documents->concat($imports)->sortByDesc('created_at')->values();

        $totalFiles = $allFiles->count();
        $totalDocs = $allFiles->where('category', 'document')->count();
        $totalImages = $allFiles->where('category', 'image')->count();
        $totalBytes = $allFiles->sum('size_bytes');
        
        $storageUsed = $totalBytes > 1073741824 
            ? number_format($totalBytes / 1073741824, 1) . ' GB' 
            : number_format($totalBytes / 1048576, 1) . ' MB';

        return response()->json([
            'data' => $allFiles,
            'summary' => [
                'total_files' => number_format($totalFiles),
                'documents' => number_format($totalDocs),
                'images' => number_format($totalImages),
                'storage_used' => $storageUsed,
            ]
        ]);
    }
}
