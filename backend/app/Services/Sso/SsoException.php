<?php

namespace App\Services\Sso;

use RuntimeException;

class SsoException extends RuntimeException
{
    public function __construct(public readonly string $safeCode = 'sso_unavailable')
    {
        parent::__construct($safeCode);
    }
}
