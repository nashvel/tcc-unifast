<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSecurityLog;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormSecurityLogController extends Controller
{
    public function index(Request $request, int $formId): JsonResponse
    {
        abort_if($formId < 1, 400, 'Invalid form ID.');

        Form::findOrFail($formId);

        $perPage   = min(max((int) $request->integer('per_page', 20), 1), 100);
        $eventType = $request->query('event_type');
        $dateFrom  = $request->query('date_from');
        $dateTo    = $request->query('date_to');

        $query = FormSecurityLog::where('form_id', $formId)
            ->orderByDesc('created_at');

        if ($eventType && $eventType !== 'all') {
            $query->where('event_type', $eventType);
        }

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $paginator = $query->paginate($perPage);
        $rows      = collect($paginator->items())->map(fn (FormSecurityLog $log) => [
            'id'         => $log->id,
            'event_type' => $log->event_type,
            'ip_address' => $log->ip_address,
            'user_id'    => $log->user_id,
            'created_at' => $log->created_at?->toISOString(),
            'payload'    => $log->payload,
        ]);

        return PaginatedJson::from($paginator, $rows->values());
    }
}
