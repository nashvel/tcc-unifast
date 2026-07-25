<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TccUnifastSyncController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $validated = $request->validate([
            'student_id' => ['nullable', 'string', 'max:100'],
            'batch' => ['nullable', 'string', 'max:255'],
            'previous_batch' => ['nullable', 'string', 'max:255'],
            'force_full_sync' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $url = config('services.tcc_unifast_n8n.webhook_url');
        $header = config('services.tcc_unifast_n8n.webhook_header');
        $secret = config('services.tcc_unifast_n8n.webhook_secret');

        abort_unless($url && $header && $secret, 503, 'The n8n synchronization webhook is not configured.');

        $requestId = (string) Str::uuid();
        $payload = array_merge($validated, [
            'request_id' => $requestId,
            'source' => 'laravel',
            'requested_at' => now()->toIso8601String(),
        ]);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders([$header => $secret])
                ->timeout(config('services.tcc_unifast_n8n.timeout'))
                ->retry(3, 1000, throw: false)
                ->post($url, $payload);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'The synchronization service is currently unavailable.',
                'request_id' => $requestId,
            ], 503);
        }

        if ($response->failed()) {
            report(new \RuntimeException("n8n sync webhook returned HTTP {$response->status()} for request {$requestId}."));

            return response()->json([
                'message' => 'The synchronization service rejected the request.',
                'request_id' => $requestId,
            ], 502);
        }

        return response()->json([
            'message' => 'Synchronization request accepted.',
            'request_id' => $requestId,
        ], 202);
    }

    private function authorizeRequest(Request $request): void
    {
        $configuredSecret = (string) config('services.tcc_unifast_n8n.endpoint_secret');
        $providedSecret = (string) $request->header('X-TCC-UniFAST-Endpoint-Key');

        abort_unless(
            $configuredSecret !== '' && hash_equals($configuredSecret, $providedSecret),
            401,
            'Invalid synchronization endpoint key.',
        );
    }
}
