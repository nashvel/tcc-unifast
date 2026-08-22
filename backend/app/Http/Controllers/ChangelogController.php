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

        if (request()->has('refresh')) {
            Cache::forget($cacheKey);
        }

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

        $responseData = [
            'data' => $commits,
            'repo' => $repo,
            'has_token' => ! empty($token),
        ];

        // In local environment, only generate the static mock JSON file when explicitly refreshing
        if (app()->environment('local') && request()->has('refresh')) {
            $mockPath = base_path('../frontend/src/mock/changelogs.json');
            if (file_exists(dirname($mockPath))) {
                $jsonString = json_encode($responseData, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($jsonString !== false) {
                    file_put_contents($mockPath, $jsonString);
                }
            }
        }

        return response()->json($responseData);
    }
}
