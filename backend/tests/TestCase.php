<?php

namespace Tests;

use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Clear throttle counters between tests.
     *
     * Several routes are rate-limited per authenticated user (e.g. KYC at 60/min).
     * The limiter is keyed on the user id, and RefreshDatabase restarts ids from 1 —
     * so hits recorded by one test are still counted against the "same" user in the
     * next, surfacing as a spurious 429/401 that only appears when the suite runs
     * as a whole. Resetting here keeps tests independent of execution order.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(RateLimiter::class)->clear('');
        $this->app->make('cache')->store()->flush();
    }
}
