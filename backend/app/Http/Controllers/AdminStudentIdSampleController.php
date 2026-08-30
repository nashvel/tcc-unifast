<?php

namespace App\Http\Controllers;

use App\Support\SecureUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Staff-supplied reference School ID sample, used to tune OCR expectations.
 *
 * Stored on the private disk: this is a real student's ID document, and the public
 * disk is web-served. The MIME type is verified by magic bytes and the stored
 * filename is server-generated, so a client-supplied extension can never decide
 * what lands on disk.
 */
class AdminStudentIdSampleController extends Controller
{
    /** @var list<string> */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** @var array<string, string> */
    private const EXTENSION_FOR_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __invoke(Request $request, string $student): JsonResponse
    {
        $validated = $request->validate([
            'id_sample' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ]);

        $file = $validated['id_sample'];

        // Magic-byte check, not just the declared MIME / client extension.
        $detected = SecureUpload::assertAllowedMime($file, self::ALLOWED_MIMES, 'id_sample');

        $safeStudent = preg_replace('/[^A-Za-z0-9_-]/', '-', $student) ?: 'unknown';
        $directory = "id-samples/{$safeStudent}";

        foreach (Storage::disk('local')->files($directory) as $existing) {
            Storage::disk('local')->delete($existing);
        }

        $extension = self::EXTENSION_FOR_MIME[$detected] ?? 'bin';
        $path = $file->storeAs($directory, Str::random(32).'.'.$extension, 'local');

        return response()->json([
            'student_id' => $student,
            'path' => $path,
            'mime_type' => $detected,
            'original_name' => SecureUpload::sanitizeOriginalName($file->getClientOriginalName(), 'id-sample'),
            'message' => 'Reference ID sample saved to private storage.',
        ]);
    }
}
