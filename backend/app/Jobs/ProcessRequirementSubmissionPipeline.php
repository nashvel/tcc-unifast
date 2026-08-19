<?php

namespace App\Jobs;

use App\Services\SubmissionPipeline\ProcessRequirementSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRequirementSubmissionPipeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $granteeId,
        public int $batchId,
        public bool $identityFailed = false,
    ) {}

    public function handle(ProcessRequirementSubmissionService $pipeline): void
    {
        $pipeline->process($this->granteeId, $this->batchId, $this->identityFailed);
    }
}
