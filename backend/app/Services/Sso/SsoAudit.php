<?php

namespace App\Services\Sso;

use App\Models\AuditLog;

class SsoAudit
{
    public static function record(string $action, array $context = []): void
    {
        $user = request()->user();
        AuditLog::create([
            'actor' => $user?->name ?? 'SIS SSO', 'role' => $user?->role ?? 'system',
            'action' => 'sso_'.$action, 'module' => 'Authentication', 'target' => 'SIS connection',
            'context' => array_intersect_key($context, array_flip(['connection_id', 'user_id', 'review_id', 'reason', 'mode', 'session_policy', 'secret_replaced'])),
            'ip_address' => request()->ip(),
        ]);
    }
}
