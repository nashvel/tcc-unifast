<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StudentFaceVerificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
            'face_capture' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $provider = (string) config('services.face_api.provider', 'mock');
        $threshold = (float) config('services.face_api.threshold', 85);
        $studentId = (string) $request->user()?->student_id;
        $adminReference = $this->adminReferencePath($studentId);

        if (! isset($validated['student_id_document']) && $adminReference === null) {
            throw ValidationException::withMessages([
                'student_id_document' => 'Upload a student ID or ask staff to attach an admin reference ID sample first.',
            ]);
        }

        if ($provider === 'mock') {
            return response()->json([
                'provider' => 'mock',
                'matched' => true,
                'score' => 96.4,
                'threshold' => $threshold,
                'reference_source' => $adminReference ? 'admin_id_sample' : 'student_upload',
                'message' => 'Mock face verification passed. Set FACE_API_PROVIDER=http for a real provider.',
            ]);
        }

        if ($provider !== 'http') {
            throw ValidationException::withMessages([
                'face_api' => 'Unsupported FACE_API_PROVIDER. Use mock or http.',
            ]);
        }

        if (! isset($validated['face_capture'])) {
            throw ValidationException::withMessages([
                'face_capture' => 'A live face capture image is required for the configured Face API provider.',
            ]);
        }

        $url = (string) config('services.face_api.url');
        $key = (string) config('services.face_api.key');
        abort_unless($url !== '' && $key !== '', 503, 'The Face API provider is not configured.');

        $idDocument = $validated['student_id_document'] ?? null;
        $faceCapture = $validated['face_capture'];
        $referencePath = $idDocument?->getRealPath() ?: Storage::disk('public')->path($adminReference);
        $referenceName = $idDocument?->getClientOriginalName() ?: basename((string) $adminReference);
        $response = Http::acceptJson()
            ->timeout((int) config('services.face_api.timeout', 30))
            ->withToken($key)
            ->attach('student_id_document', fopen($referencePath, 'r'), $referenceName)
            ->attach('face_capture', fopen($faceCapture->getRealPath(), 'r'), $faceCapture->getClientOriginalName())
            ->post($url, [
                'student_id' => $request->user()?->student_id,
                'threshold' => $threshold,
            ]);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Face verification provider rejected the request.',
                'provider_response' => $response->json(),
            ], $response->status() >= 500 ? 502 : 422);
        }

        $payload = $response->json();
        $score = (float) data_get($payload, 'score', data_get($payload, 'confidence', 0));

        return response()->json([
            'provider' => 'http',
            'matched' => (bool) data_get($payload, 'matched', $score >= $threshold),
            'score' => $score,
            'threshold' => $threshold,
            'reference_source' => $adminReference && ! $idDocument ? 'admin_id_sample' : 'student_upload',
            'provider_response' => $payload,
        ]);
    }

    private function adminReferencePath(string $studentId): ?string
    {
        if ($studentId === '') {
            return null;
        }

        $safeStudent = preg_replace('/[^A-Za-z0-9_-]/', '-', $studentId) ?: 'unknown';
        $files = Storage::disk('public')->files("id-samples/{$safeStudent}");

        return $files[0] ?? null;
    }
}
