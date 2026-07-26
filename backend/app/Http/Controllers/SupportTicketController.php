<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::query();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(title) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(ticket_id) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(reporter) LIKE ?', ["%{$search}%"]);
            });
        }

        $tickets = $query->latest()->get();

        if ($tickets->isEmpty() && !SupportTicket::exists()) {
            $this->seedInitialTickets();
            $tickets = SupportTicket::query()->latest()->get();
        }

        return response()->json(['data' => $tickets]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|in:bug,feature,question,general',
            'priority' => 'required|string|in:Low,Normal,High,Critical',
            'description' => 'nullable|string',
        ]);

        $user = $request->user();
        $count = SupportTicket::count() + 1;
        $ticketId = 'TK-' . str_pad((string)$count, 3, '0', STR_PAD_LEFT);

        $ticket = SupportTicket::create([
            'ticket_id' => $ticketId,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'Open',
            'reporter' => $user ? $user->name : 'System Developer',
            'assignee' => 'System Developer',
            'description' => $validated['description'] ?? null,
            'replies' => [],
        ]);

        if ($user) {
            AuditLog::create([
                'actor' => $user->name,
                'role' => ucfirst($user->role),
                'action' => 'ticket_create',
                'module' => 'Support',
                'target' => "Created ticket {$ticketId}",
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json(['data' => $ticket], 201);
    }

    public function update(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|required|string|in:Open,In Progress,Waiting,Resolved',
            'priority' => 'sometimes|required|string|in:Low,Normal,High,Critical',
            'assignee' => 'nullable|string|max:100',
            'reply' => 'nullable|string',
        ]);

        if (isset($validated['status'])) {
            $supportTicket->status = $validated['status'];
        }
        if (isset($validated['priority'])) {
            $supportTicket->priority = $validated['priority'];
        }
        if (array_key_exists('assignee', $validated)) {
            $supportTicket->assignee = $validated['assignee'];
        }

        if (!empty($validated['reply'])) {
            $replies = $supportTicket->replies ?? [];
            $user = $request->user();
            $replies[] = [
                'author' => $user ? $user->name : 'System Developer',
                'message' => $validated['reply'],
                'created_at' => now()->toDateTimeString(),
            ];
            $supportTicket->replies = $replies;
        }

        $supportTicket->save();

        return response()->json(['data' => $supportTicket]);
    }

    private function seedInitialTickets(): void
    {
        $initial = [
            [
                'ticket_id' => 'TK-001',
                'title' => 'Face verification timeout after 30s',
                'category' => 'bug',
                'priority' => 'High',
                'status' => 'Open',
                'reporter' => 'Maria Santos',
                'assignee' => 'System Developer',
                'description' => 'Face verification API times out on weak mobile connections.',
                'replies' => [],
            ],
            [
                'ticket_id' => 'TK-002',
                'title' => 'Request: CSV export for audit trail',
                'category' => 'feature',
                'priority' => 'Normal',
                'status' => 'In Progress',
                'reporter' => 'Office Administrator',
                'assignee' => 'System Developer',
                'description' => 'Admin requested CSV export capability for developer audit logs.',
                'replies' => [],
            ],
            [
                'ticket_id' => 'TK-003',
                'title' => 'OCR mismatch on non-standard font transcripts',
                'category' => 'bug',
                'priority' => 'Normal',
                'status' => 'Waiting',
                'reporter' => 'UniFAST Staff',
                'assignee' => 'System Developer',
                'description' => 'Special characters on course names cause low confidence score.',
                'replies' => [],
            ],
        ];

        foreach ($initial as $data) {
            SupportTicket::create($data);
        }
    }
}
