<?php

namespace App\Http\Controllers;

use App\Jobs\RunContinuitySync;
use App\Models\ContinuityReview;
use App\Models\ContinuitySyncRun;
use App\Models\GoogleWorkspaceConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ContinuitySyncController extends Controller
{
    public function internal(Request $request): JsonResponse
    {
        $secret = (string) config('continuity.sync_secret');
        $timestamp = (string) $request->header('X-Continuity-Timestamp', '');
        abort_unless(strlen($secret) >= 32 && ctype_digit($timestamp) && abs(now()->timestamp - (int) $timestamp) <= 300, 401);
        abort_if(strlen($request->getContent()) > 1024, 413);
        $expected = hash_hmac('sha256', $timestamp."\nPOST\n/api/internal/n8n/continuity-sync\n".$request->getContent(), $secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Continuity-Signature', '')), 401);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'source' => ['required', 'in:n8n']]);
        abort_unless($request->header('Idempotency-Key') === $data['request_id'], 422);

        return $this->queue($data['request_id'], 'n8n', hash('sha256', $request->getContent()));
    }

    public function manual(): JsonResponse
    {
        $id = (string) Str::uuid();

        return $this->queue($id, 'admin', hash('sha256', $id));
    }

    public function runs(): JsonResponse
    {
        return response()->json(ContinuitySyncRun::latest()->paginate(25));
    }

    public function reviews(): JsonResponse
    {
        return response()->json(ContinuityReview::where('status', 'pending')->latest()->paginate(25));
    }

    public function decide(Request $request, ContinuityReview $review, \App\Services\Continuity\ContinuityReviewService $service): JsonResponse
    {
        $data = $request->validate(['choices' => ['required', 'array', 'max:30'], 'choices.*' => ['required', 'in:system,mirror'], 'note' => ['required', 'string', 'min:3', 'max:2000']]);

        return response()->json(['data' => $service->decide($review, $request->user(), $data['choices'], $data['note'])]);
    }

    private function queue(string $id, string $source, string $hash): JsonResponse
    {
        abort_unless(config('continuity.enabled') && GoogleWorkspaceConnection::where('enabled', true)->where('status', 'connected')->exists(), 409, 'Continuity synchronization is disabled.');
        $run = Cache::lock('continuity:queue', 30)->block(5, function () use ($id, $source, $hash) {
            if ($existing = ContinuitySyncRun::find($id)) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'Request ID already used.');

                return $existing;
            }
            $run = ContinuitySyncRun::create(['id' => $id, 'request_hash' => $hash, 'source' => $source]);
            RunContinuitySync::dispatch($run->id);

            return $run;
        });

        return response()->json(['data' => ['run_id' => $run->id, 'status' => $run->status]], 202);
    }
}
