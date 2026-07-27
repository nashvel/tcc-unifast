<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ChangelogController extends Controller
{
    public function index(): JsonResponse
    {
        $repo = env('GITHUB_REPO', 'nashvel/tcc-unifast');
        $token = env('GITHUB_TOKEN');

        $cacheKey = "github_commits_{$repo}";

        // Cache for 5 minutes
        $commits = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($repo, $token) {
            $request = Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'TCC-UniFAST-App',
            ]);

            if ($token) {
                $request->withToken($token);
            }

            $response = $request->get("https://api.github.com/repos/{$repo}/commits?per_page=100");

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        });

        return response()->json([
            'data' => $commits,
            'repo' => $repo,
            'has_token' => !empty($token),
        ]);
    }
}
