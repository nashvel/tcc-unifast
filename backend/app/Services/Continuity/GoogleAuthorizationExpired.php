<?php

namespace App\Services\Continuity;

use Illuminate\Validation\ValidationException;

/** A provider-confirmed invalid grant, never a transport or client configuration error. */
class GoogleAuthorizationExpired extends ValidationException {}
