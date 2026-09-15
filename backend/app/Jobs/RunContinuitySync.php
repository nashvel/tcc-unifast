<?php

namespace App\Jobs;

use App\Models\ContinuitySyncRun;
use App\Services\Continuity\ContinuitySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunContinuitySync implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1700;

    public function __construct(public string $runId) {}

    public function handle(ContinuitySyncService $service): void
    {
        $run = ContinuitySyncRun::findOrFail($this->runId);
        if ($run->status === 'completed') {
            return;
        }
        try {
            $service->run($run);
        } catch (\Throwable) {
            $run->update(['status' => 'failed', 'error_code' => 'sync_failed', 'finished_at' => now()]);
        }
    }
}
