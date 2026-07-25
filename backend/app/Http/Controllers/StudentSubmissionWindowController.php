<?php

namespace App\Http\Controllers;

use App\Services\BatchWindowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentSubmissionWindowController extends Controller
{
    public function __invoke(Request $request, BatchWindowService $windows): JsonResponse
    {
        return response()->json(['data' => $windows->windowForStudent($request->user())]);
    }
}
