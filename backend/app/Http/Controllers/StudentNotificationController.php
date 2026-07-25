<?php

namespace App\Http\Controllers;

use App\Models\BatchNotification;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $paginator = BatchNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        $items = collect($paginator->items())->map(fn (BatchNotification $notification) => [
            'id' => $notification->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'type' => $notification->type,
            'read' => $notification->read_at !== null,
            'time' => $notification->created_at?->toDayDateTimeString(),
        ]);

        return PaginatedJson::from($paginator, $items->values());
    }

    public function markRead(Request $request, BatchNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->forceFill(['read_at' => now()])->save();

        return response()->json([
            'data' => [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'type' => $notification->type,
                'read' => true,
                'time' => $notification->created_at?->toDayDateTimeString(),
            ],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        BatchNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
