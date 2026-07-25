<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminStudentIdSampleController extends Controller
{
    public function __invoke(Request $request, string $student): JsonResponse
    {
        $validated = $request->validate([
            'id_sample' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ]);

        $file = $validated['id_sample'];
        $safeStudent = preg_replace('/[^A-Za-z0-9_-]/', '-', $student) ?: 'unknown';
        $directory = "id-samples/{$safeStudent}";

        foreach (Storage::disk('public')->files($directory) as $existing) {
            Storage::disk('public')->delete($existing);
        }

        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $path = $file->storeAs($directory, "reference.{$extension}", 'public');

        return response()->json([
            'student_id' => $student,
            'path' => $path,
            'file_url' => Storage::disk('public')->url($path),
            'message' => 'Reference ID sample saved.',
        ]);
    }
}
