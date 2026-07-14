<?php

namespace App\Http\Controllers;

use App\Models\BatchNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = BatchNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (BatchNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'type' => $notification->type,
                'read' => $notification->read_at !== null,
                'time' => $notification->created_at?->toDayDateTimeString(),
            ]);

        return response()->json(['data' => $items]);
    }
}
