<?php

namespace App\Http\Controllers;

use App\Models\SecurityFinding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityFindingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SecurityFinding::query()->with('relatedUser:id,name');
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('search')) $query->where('title', 'like', '%'.$request->string('search').'%');
        return response()->json(['data' => $query->latest()->paginate(min(100, max(1, $request->integer('per_page', 25))))]);
    }

    public function update(Request $request, SecurityFinding $securityFinding): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:open,resolved,ignored']);
        $securityFinding->fill($data);
        if ($data['status'] === 'resolved') { $securityFinding->resolved_by = $request->user()->id; $securityFinding->resolved_at = now(); }
        $securityFinding->save();
        return response()->json(['data' => $securityFinding]);
    }
}
