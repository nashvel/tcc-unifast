<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->integer('page', 1));
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $search = trim($request->input('search', ''));

        $query = AuditLog::query()->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('actor', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('target', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $logs = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'data' => $logs,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Client-reported UI events.
     *
     * The audit trail is evidence, so a client must not be able to write arbitrary
     * actions into it: `action` and `module` are restricted to a known vocabulary,
     * and every row is marked `source: client` so operator-recorded and
     * client-reported entries stay distinguishable. This list covers both the
     * staff/admin action surface and the student-facing product tour.
     *
     * @var list<string>
     */
    private const ALLOWED_ACTIONS = [
        // Staff / admin actions
        'route_view',
        'ui_click',
        'page_viewed',
        'export_downloaded',
        'report_generated',
        'filter_applied',
        'session_timeout',
        'permission_denied_view',
        // Student product tour / UI events
        'page_view',
        'document_preview_opened',
        'document_download_clicked',
        'form_opened',
        'form_abandoned',
        'session_idle_warning_shown',
        'ui_error_encountered',
        'tour_started',
        'tour_completed',
        'tour_dismissed',
    ];

    /**
     * Every module the frontend's `moduleFromPath()` (services/audit.ts) can derive
     * from a real `/app/*` or `/student/*` route's second path segment, plus the
     * fixed set the click/tour instrumentation sends directly.
     *
     * @var list<string>
     */
    private const ALLOWED_MODULES = [
        // Derived from /app/* second path segment
        'Dashboard',
        'Announcements',
        'Social Posts',
        'Reports',
        'Billing',
        'Distribution',
        'Support',
        'Audit',
        'Security',
        'Users',
        'Settings',
        'Activation Seeder',
        'Appearance',
        'Style Guide',
        'Masterlist',
        'Onboarding',
        'Developer',
        'Grantees',
        'Batches',
        'Programs',
        'Academic',
        'Documents',
        'Face Reviews',
        'Eligibility',
        'Files',
        'Forms',
        // Derived from /student/* second path segment
        'Kyc',
        'Verify',
        'Submissions',
        'Profile',
        'Upload',
        'Notifications',
        // Fixed values sent directly by tour/UI instrumentation
        'Navigation',
        'Session',
        'UI',
        'Tour',
        'Requirements Submission',
    ];

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:100', Rule::in(self::ALLOWED_ACTIONS)],
            'module' => ['required', 'string', 'max:100', Rule::in(self::ALLOWED_MODULES)],
            'target' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        AuditLog::create([
            'actor' => $user ? $user->name : 'System Developer',
            'role' => $user ? ucfirst($user->role) : 'Developer',
            'action' => $validated['action'],
            'module' => $validated['module'],
            'target' => $validated['target'] ?? null,
            // Tag provenance so a client-reported event is never mistaken for one
            // written by a server-side action.
            'context' => array_merge($validated['context'] ?? [], ['source' => 'client']),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Audit event logged.'], 201);
    }
}
