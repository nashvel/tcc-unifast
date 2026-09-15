<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Announcement::query()->with('channels')->latest()->get()->map(fn (Announcement $announcement) => $this->present($announcement))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $announcement = Announcement::create([...$data, 'created_by' => $request->user()->id, 'sent_at' => $data['status'] === 'sent' ? now() : null]);
        $announcement->channels()->createMany(collect($request->input('channels', ['in_app']))->map(fn (string $channel) => ['channel' => $channel])->all());

        return response()->json(['data' => $this->present($announcement->load('channels'))], 201);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        return response()->json(['data' => $this->present($announcement->load('channels'))]);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        if ($announcement->status === 'sent') {
            return response()->json(['message' => 'Sent announcements are immutable. Publish a correction instead.'], 422);
        }
        $data = $this->validated($request, false);
        if (($data['status'] ?? null) === 'sent' && ! $announcement->sent_at) {
            $data['sent_at'] = now();
        }
        $announcement->update($data);
        if ($request->has('channels')) {
            $announcement->channels()->delete();
        }
        if ($request->has('channels')) {
            $announcement->channels()->createMany(collect($request->input('channels'))->map(fn (string $channel) => ['channel' => $channel])->all());
        }

        return response()->json(['data' => $this->present($announcement->fresh()->load('channels'))]);
    }

    public function cancel(Announcement $announcement): JsonResponse
    {
        if ($announcement->status !== 'scheduled') {
            return response()->json(['message' => 'Only scheduled announcements can be cancelled.'], 422);
        }
        $announcement->update(['status' => 'cancelled']);

        return response()->json(['data' => $this->present($announcement->fresh()->load('channels'))]);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        if ($announcement->status !== 'draft') {
            return response()->json(['message' => 'Only draft announcements can be deleted.'], 422);
        }
        $announcement->delete();

        return response()->json(['message' => 'Draft announcement deleted.']);
    }

    private function validated(Request $request, bool $required = true): array
    {
        return $request->validate([
            'title' => ($required ? 'required' : 'sometimes|required').'|string|max:255',
            'body' => ($required ? 'required' : 'sometimes|required').'|string',
            'audience_type' => ($required ? 'required' : 'sometimes|required').'|in:all,batch,program',
            'channels' => 'sometimes|array|min:1', 'channels.*' => 'in:in_app,email,sms',
            'status' => ($required ? 'required' : 'sometimes|required').'|in:draft,scheduled,sent,cancelled',
            'scheduled_at' => 'nullable|date',
        ]);
    }

    private function present(Announcement $announcement): array
    {
        return [...$announcement->only(['id', 'title', 'body', 'audience_type', 'status', 'scheduled_at', 'sent_at', 'created_at']), 'channels' => $announcement->channels->pluck('channel')->values()];
    }
}
