<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TccPublicHomeEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('tcc_public_home');
        config()->set('services.tcc_public.home_url', 'https://api.tcc.example.test/api/v1/home');
        config()->set('services.tcc_public.site_url', 'https://tcc.example.test');
        config()->set('services.tcc_public.api_url', 'https://api.tcc.example.test');
    }

    public function test_it_proxies_and_normalizes_tcc_public_home_content(): void
    {
        Http::fake([
            'api.tcc.example.test/*' => Http::response([
                'slides' => [
                    [
                        'id' => 1,
                        'title' => 'Campus story',
                        'subtitle' => 'Public education',
                        'image_url' => 'https://tcc.edu.ph/images/hero-campus.jpg',
                        'link_url' => '/admissions',
                        'extra' => 'ignored',
                    ],
                ],
                'news' => [
                    [
                        'id' => 159,
                        'slug' => 'latest-story',
                        'title' => 'Latest story',
                        'excerpt' => 'Short public excerpt.',
                        'body' => '<p>Do not proxy full article bodies.</p>',
                        'thumbnail_url' => 'https://api.tcc.edu.ph/storage/story.jpg',
                        'published_at' => '2026-08-12T16:27:00+00:00',
                        'sdg_goals' => [4, 9, 17],
                        'author' => ['name' => 'Chrisjean Limpiado'],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/public/tcc-home')
            ->assertOk()
            ->assertJsonPath('source', 'home_api')
            ->assertJsonPath('slides.0.title', 'Campus story')
            ->assertJsonPath('news.0.slug', 'latest-story')
            ->assertJsonPath('news.0.author_name', 'Chrisjean Limpiado')
            ->assertJsonPath('news.0.sdg_goals.0', 4)
            ->assertJsonMissingPath('news.0.body')
            ->assertJsonMissingPath('slides.0.extra');
    }

    public function test_it_scrapes_the_public_news_bundle_when_home_api_fails(): void
    {
        Http::fake([
            'api.tcc.example.test/api/v1/news?per_page=9&page=1' => Http::response([
                'data' => [
                    [
                        'id' => 42,
                        'slug' => 'scraped-news',
                        'title' => 'Scraped news',
                        'excerpt' => 'Loaded through the scraped news route.',
                        'thumbnail_url' => 'https://api.tcc.example.test/storage/news.jpg',
                        'published_at' => '2026-08-15T12:00:00+00:00',
                        'sdg_goals' => [3, 4],
                        'author' => ['name' => 'Campus Desk'],
                    ],
                ],
            ]),
            'api.tcc.example.test/*' => Http::response([], 503),
            'tcc.example.test/news' => Http::response('<script type="module" src="/assets/index-live.js"></script>'),
            'tcc.example.test/assets/index-live.js' => Http::response('import("./assets/NewsList-live.js")'),
            'tcc.example.test/assets/NewsList-live.js' => Http::response('fetch(`/api/v1/news?per_page=9&page=${page}`)'),
        ]);

        $this->getJson('/api/public/tcc-home')
            ->assertOk()
            ->assertJsonPath('source', 'news_scrape')
            ->assertJsonPath('slides', [])
            ->assertJsonPath('news.0.slug', 'scraped-news')
            ->assertJsonPath('news.0.author_name', 'Campus Desk')
            ->assertJsonPath('news.0.sdg_goals.1', 4);
    }

    public function test_it_returns_bad_gateway_when_api_and_scrape_fallback_fail(): void
    {
        Http::fake([
            'api.tcc.example.test/*' => Http::response([], 503),
            'tcc.example.test/*' => Http::response('', 503),
        ]);

        $this->getJson('/api/public/tcc-home')
            ->assertStatus(502);
    }
}
