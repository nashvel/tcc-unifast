<?php

namespace App\Http\Controllers;

use App\Models\Grantee;
use App\Support\PaginatedJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GranteeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'full_name');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['full_name', 'student_number', 'student_id', 'program', 'status', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'full_name';
        }

        $query = Grantee::query()->with(['batch', 'user']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_number', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('program', 'like', "%{$search}%");
            });
        }

        if ($account = $request->query('account')) {
            if ($account !== 'all') {
                $query->whereHas('user', fn ($builder) => $builder->where('account_status', $account));
            }
        }

        if ($submission = $request->query('submission')) {
            if ($submission !== 'all') {
                $query->where('submission_status', $submission);
            }
        }

        $paginator = $query->orderBy($sort, $direction)->paginate($perPage);
        $rows = collect($paginator->items())->map(fn (Grantee $grantee) => $this->present($grantee));

        return PaginatedJson::from($paginator, $rows->values());
    }

    public function show(Grantee $grantee): JsonResponse
    {
        $grantee->load(['batch', 'user', 'academicRecord', 'kycProfile']);

        return response()->json(['data' => $this->present($grantee, true)]);
    }

    private function present(Grantee $grantee, bool $detailed = false): array
    {
        $payload = [
            'id' => $grantee->id,
            'student_number' => $grantee->student_number,
            'student_id' => $grantee->student_id,
            'name' => $grantee->full_name,
            'email' => $grantee->email,
            'program' => $grantee->program,
            'batch' => $grantee->batch?->name,
            'batch_id' => $grantee->batch_id,
            'account' => $grantee->user?->account_status ?? 'inactive',
            'submission' => $grantee->submission_status ?? 'not_submitted',
            'eligibility' => $grantee->status === 'eligible' ? 'eligible' : 'pending',
            'risk' => 'low',
        ];

        if ($detailed) {
            $payload['contact'] = $grantee->kycProfile?->contact;
            $payload['year_level'] = $grantee->year_level;
            $payload['university'] = 'Tagoloan Community College';
            $payload['gwa'] = $grantee->academicRecord?->latest_gwa;
            $payload['birthdate'] = $grantee->kycProfile?->birthdate;
        }

        return $payload;
    }
}
