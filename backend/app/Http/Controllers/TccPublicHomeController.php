<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TccPublicHomeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember(
            'tcc_public_home',
            max(30, (int) config('services.tcc_public.cache_seconds', 300)),
            fn () => $this->fetchHomePayload()
        );

        return response()->json($payload);
    }

    /**
     * @return array{slides: array<int, array<string, mixed>>, news: array<int, array<string, mixed>>}
     */
    private function fetchHomePayload(): array
    {
        try {
            return $this->fetchHomeApiPayload();
        } catch (RuntimeException) {
            try {
                return $this->scrapeNewsPayload();
            } catch (RuntimeException) {
                abort(502, 'Unable to load Tagoloan Community College public content.');
            }
        }
    }

    /**
     * @return array{slides: array<int, array<string, mixed>>, news: array<int, array<string, mixed>>, source: string}
     */
    private function fetchHomeApiPayload(): array
    {
        $response = Http::acceptJson()
            ->timeout((int) config('services.tcc_public.timeout', 10))
            ->get((string) config('services.tcc_public.home_url'));

        if (! $response->successful()) {
            throw new RuntimeException('Unable to load Tagoloan Community College public home API.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Tagoloan Community College public home API returned an invalid response.');
        }

        return [
            'source' => 'home_api',
            'slides' => collect(Arr::get($payload, 'slides', []))
                ->filter(fn (mixed $slide): bool => is_array($slide))
                ->take(5)
                ->map(fn (array $slide): array => [
                    'id' => Arr::get($slide, 'id'),
                    'title' => Arr::get($slide, 'title'),
                    'subtitle' => Arr::get($slide, 'subtitle'),
                    'image_url' => Arr::get($slide, 'image_url'),
                    'link_url' => Arr::get($slide, 'link_url'),
                ])
                ->values()
                ->all(),
            'news' => collect(Arr::get($payload, 'news', []))
                ->filter(fn (mixed $article): bool => is_array($article))
                ->take(8)
                ->map(fn (array $article): array => [
                    'id' => Arr::get($article, 'id'),
                    'slug' => Arr::get($article, 'slug'),
                    'title' => Arr::get($article, 'title'),
                    'excerpt' => Arr::get($article, 'excerpt'),
                    'image_url' => Arr::get($article, 'thumbnail_url') ?: Arr::get($article, 'image_url'),
                    'published_at' => Arr::get($article, 'published_at'),
                    'author_name' => Arr::get($article, 'author.name'),
                    'sdg_goals' => array_values(array_filter(
                        Arr::get($article, 'sdg_goals', []),
                        fn (mixed $goal): bool => is_numeric($goal)
                    )),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{slides: array<int, array<string, mixed>>, news: array<int, array<string, mixed>>, source: string}
     */
    private function scrapeNewsPayload(): array
    {
        $siteUrl = rtrim((string) config('services.tcc_public.site_url', 'https://tcc.edu.ph'), '/');
        $newsPage = $this->getText($siteUrl.'/news');
        $entryScriptUrl = $this->absoluteUrl($siteUrl, $this->matchFirst(
            '/<script[^>]+type=["\']module["\'][^>]+src=["\']([^"\']+)["\']/i',
            $newsPage
        ));
        $entryScript = $this->getText($entryScriptUrl);
        $newsChunkPath = $this->matchFirst('/assets\/NewsList-[^"`\')\s]+\.js/', $entryScript);
        $newsChunk = $this->getText($this->absoluteUrl($siteUrl, '/'.$newsChunkPath));

        $perPage = (int) ($this->matchFirst('/\/api\/v1\/news\?per_page=(\d+)&page=/', $newsChunk) ?: 6);
        $perPage = max(1, min($perPage, 12));
        $newsEndpointPath = "/api/v1/news?per_page={$perPage}&page=1";
        $newsEndpoint = rtrim((string) config('services.tcc_public.api_url', 'https://api.tcc.edu.ph'), '/').$newsEndpointPath;
        $response = Http::acceptJson()
            ->timeout((int) config('services.tcc_public.timeout', 10))
            ->get($newsEndpoint);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to load Tagoloan Community College public news listing.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Tagoloan Community College public news listing returned an invalid response.');
        }

        return [
            'source' => 'news_scrape',
            'slides' => [],
            'news' => collect(Arr::get($payload, 'data', []))
                ->filter(fn (mixed $article): bool => is_array($article))
                ->take(8)
                ->map(fn (array $article): array => [
                    'id' => Arr::get($article, 'id'),
                    'slug' => Arr::get($article, 'slug'),
                    'title' => Arr::get($article, 'title'),
                    'excerpt' => Arr::get($article, 'excerpt'),
                    'image_url' => Arr::get($article, 'thumbnail_url') ?: Arr::get($article, 'image_url'),
                    'published_at' => Arr::get($article, 'published_at'),
                    'author_name' => Arr::get($article, 'author.name'),
                    'sdg_goals' => array_values(array_filter(
                        Arr::get($article, 'sdg_goals', []),
                        fn (mixed $goal): bool => is_numeric($goal)
                    )),
                ])
                ->values()
                ->all(),
        ];
    }

    private function getText(string $url): string
    {
        $response = Http::accept('text/html,application/javascript,text/javascript,*/*')
            ->timeout((int) config('services.tcc_public.timeout', 10))
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Unable to scrape {$url}.");
        }

        return $response->body();
    }

    private function matchFirst(string $pattern, string $subject): string
    {
        if (preg_match($pattern, $subject, $matches) !== 1) {
            throw new RuntimeException('Unable to identify the public TCC news bundle.');
        }

        return $matches[1] ?? $matches[0];
    }

    private function absoluteUrl(string $baseUrl, string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
