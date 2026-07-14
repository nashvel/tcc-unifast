<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentSubmission;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class StudentDocumentOcrController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'in:Course History,COR'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ]);

        $file = $validated['file'];
        $isPdf = $file->getMimeType() === 'application/pdf';
        if (! $isPdf && $file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Image uploads are limited to 10 MB.']);
        }

        $baseUrl = rtrim((string) config('services.ocr.url'), '/');
        abort_unless($baseUrl !== '', 503, 'The OCR service is not configured.');

        $storedPath = $file->store('submissions', 'public');
        $submission = DocumentSubmission::create([
            'student_id' => $request->user()->student_id, 'student_name' => $request->user()->name,
            'document_type' => $validated['document_type'], 'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath, 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(),
            'status' => 'processing',
        ]);

        try {
            $response = Http::acceptJson()->timeout((int) config('services.ocr.timeout', 120))
                ->retry(2, 500, throw: false)
                ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
                ->post($baseUrl.($isPdf ? '/ocr/pdf' : '/ocr/image'));
        } catch (ConnectionException) {
            $submission->update(['status' => 'ocr_failed']);
            return response()->json(['message' => 'OCR service is unavailable. Start the Python OCR server and try again.'], 503);
        }

        if ($response->failed()) {
            $submission->update(['status' => 'ocr_failed']);
            return response()->json([
                'message' => $response->json('error.message', 'The document could not be processed.'),
                'error' => $response->json('error'),
            ], $response->status() >= 500 ? 502 : 422);
        }

        $ocr = $response->json();
        $text = $isPdf ? data_get($ocr, 'result.combined_text') : data_get($ocr, 'result.cleaned_text');
        $metadata = $isPdf ? null : ['metadata' => $ocr['metadata'] ?? null, 'qr_code' => $ocr['qr_code'] ?? null];
        $risk = data_get($ocr, 'metadata.software') || data_get($ocr, 'metadata.gps_present') ? 'medium' : 'low';
        $submission->update([
            'status' => 'pending_review', 'risk_level' => $risk, 'extracted_text' => $text,
            'ocr_confidence' => $isPdf ? null : data_get($ocr, 'result.average_confidence'),
            'ocr_payload' => $ocr, 'metadata_payload' => $metadata,
        ]);
        AuditLog::create([
            'actor' => $request->user()->name, 'role' => 'Student', 'action' => 'submission_uploaded',
            'module' => 'Document Validation', 'target' => "Submission #{$submission->id}",
            'context' => ['document_type' => $validated['document_type'], 'risk' => $risk], 'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'submission_id' => $submission->id,
            'document_type' => $validated['document_type'],
            'file_name' => $file->getClientOriginalName(),
            'ocr' => $response->json(),
        ]);
    }
}
