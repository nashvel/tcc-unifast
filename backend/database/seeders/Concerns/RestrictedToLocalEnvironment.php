<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * Guard for seeders that plant known, shared credentials for local QA.
 *
 * These are convenient on a developer machine and a credential hole anywhere else,
 * so they must fail loudly rather than silently create guessable accounts if
 * `db:seed` is ever run against a shared or deployed database.
 */
trait RestrictedToLocalEnvironment
{
    protected function assertLocalEnvironment(): void
    {
        if (app()->environment(['local', 'testing'])) {
            return;
        }

        throw new RuntimeException(sprintf(
            '%s seeds known test credentials and is restricted to the local/testing environment (current: %s).',
            class_basename(static::class),
            app()->environment(),
        ));
    }
}
