<?php

namespace App\Services;

use App\Support\OcrServiceRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Calls the Python ocr-service /face/match endpoint to compare two vault identity
 * images server-side using OpenCV DNN (YuNet + SFace).
 *
 * The client never computes or submits face descriptors — only raw JPEG bytes are
 * sent from the Laravel backend, which already owns the files in private storage.
 */
class FaceVerificationService
{
    /**
     * Stream two vault identity image files to ocr-service and compare them.
     *
     * @param  string  $referencePath  Absolute filesystem path to the reference face image (ID card crop).
     * @param  string  $livePath  Absolute filesystem path to the live selfie image.
     * @return array{
     *   matched: bool,
     *   score: float,
     *   cosine_distance: float,
     *   l2_distance: float,
     *   reference_face_found: bool,
     *   live_face_found: bool,
     *   provider: string,
     *   error: ?string,
     * }
     *
     * @throws \RuntimeException When FACE_API_PROVIDER=ocr_service and the service is unavailable (HTTP 503).
     */
    public function compare(string $referencePath, string $livePath): array
    {
        $baseUrl = rtrim((string) config('services.ocr.url', ''), '/');

        try {
            $response = Http::acceptJson()
                ->withHeaders(OcrServiceRequest::headers())
                ->timeout((int) config('services.ocr.timeout', 120))
                ->attach('reference_file', file_get_contents($referencePath), 'reference.jpg')
                ->attach('live_file', file_get_contents($livePath), 'live.jpg')
                ->post($baseUrl.'/face/match');
        } catch (ConnectionException $e) {
            throw new \RuntimeException(
                'Face verification service is temporarily unavailable. Please try again in a few minutes.',
                503,
                $e,
            );
        }

        if ($response->status() === 503) {
            throw new \RuntimeException(
                'Face verification service is temporarily unavailable. Please try again in a few minutes.',
                503,
            );
        }

        if ($response->failed()) {
            $errorMessage = (string) data_get($response->json(), 'error.message', '');
            throw new \RuntimeException(
                $errorMessage !== ''
                    ? 'Face verification failed: '.$errorMessage
                    : 'Face verification service returned an unexpected error.',
            );
        }

        $data = $response->json() ?? [];

        return [
            'matched' => (bool) data_get($data, 'matched', false),
            'score' => (float) data_get($data, 'score', 0.0),
            'cosine_distance' => (float) data_get($data, 'cosine_distance', 2.0),
            'l2_distance' => (float) data_get($data, 'l2_distance', INF),
            'reference_face_found' => (bool) data_get($data, 'reference_face_found', false),
            'live_face_found' => (bool) data_get($data, 'live_face_found', false),
            'provider' => 'ocr_service',
            'error' => data_get($data, 'error'),
        ];
    }
}
