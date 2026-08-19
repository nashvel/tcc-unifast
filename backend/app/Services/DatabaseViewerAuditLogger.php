<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class DatabaseViewerAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function read(Request $request, string $action, string $target, array $context = []): void
    {
        $user = $request->user();

        AuditLog::create([
            'actor' => $user?->email ?? 'unknown',
            'role' => $user?->role ?? 'unknown',
            'action' => $action,
            'module' => 'database_viewer',
            'target' => $target,
            'context' => $context,
            'ip_address' => $request->ip(),
        ]);
    }
}
