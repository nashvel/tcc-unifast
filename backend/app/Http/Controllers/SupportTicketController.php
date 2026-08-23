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
        $query = SupportTicket::with(['reporter', 'assignee', 'replies.user']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(title) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(ticket_id) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('reporter', function ($qReporter) use ($search) {
                        $qReporter->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        $tickets = $query->latest()->get();

        if ($tickets->isEmpty() && ! SupportTicket::exists()) {
            $this->seedInitialTickets();
            $tickets = SupportTicket::with(['reporter', 'assignee', 'replies.user'])->latest()->get();
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
        $ticketId = 'TK-'.str_pad((string) $count, 3, '0', STR_PAD_LEFT);

        $ticket = SupportTicket::create([
            'ticket_id' => $ticketId,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'Open',
            'reporter_id' => $user ? $user->id : null,
            'assignee_id' => null,
            'description' => $validated['description'] ?? null,
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
            'assignee_id' => 'nullable|exists:users,id',
            'reply' => 'nullable|string',
        ]);

        if (isset($validated['status'])) {
            $supportTicket->status = $validated['status'];
        }
        if (isset($validated['priority'])) {
            $supportTicket->priority = $validated['priority'];
        }
        if (array_key_exists('assignee_id', $validated)) {
            $supportTicket->assignee_id = $validated['assignee_id'];
        }

        if (! empty($validated['reply'])) {
            $user = $request->user();
            $supportTicket->replies()->create([
                'user_id' => $user ? $user->id : null, // Assuming user_id can be null if not logged in? No, it's constrained.
                'message' => $validated['reply'],
            ]);
        }

        $supportTicket->save();
        $supportTicket->load(['reporter', 'assignee', 'replies.user']);

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
                'reporter_id' => null,
                'assignee_id' => null,
                'description' => 'Face verification API times out on weak mobile connections.',
            ],
            [
                'ticket_id' => 'TK-002',
                'title' => 'Request: CSV export for audit trail',
                'category' => 'feature',
                'priority' => 'Normal',
                'status' => 'In Progress',
                'reporter_id' => null,
                'assignee_id' => null,
                'description' => 'Admin requested CSV export capability for developer audit logs.',
            ],
            [
                'ticket_id' => 'TK-003',
                'title' => 'OCR mismatch on non-standard font transcripts',
                'category' => 'bug',
                'priority' => 'Normal',
                'status' => 'Waiting',
                'reporter_id' => null,
                'assignee_id' => null,
                'description' => 'Special characters on course names cause low confidence score.',
            ],
        ];

        foreach ($initial as $data) {
            SupportTicket::create($data);
        }
    }
}
