<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\SocialMediaPost;
use App\Support\PaginatedJson;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SocialMediaPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 12), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $query = SocialMediaPost::query()
            ->with(['batch:id,name,academic_year,semester,submission_deadline', 'creator:id,name,email'])
            ->latest();

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('campaign', 'like', "%{$search}%");
            });
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage);
        $rows = collect($paginator->items())->map(fn (SocialMediaPost $post) => $this->present($post));

        return PaginatedJson::from($paginator, $rows->values());
    }

    public function template(Request $request): JsonResponse
    {
        abort_unless($request->user(), 401);

        $validated = $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'channel' => ['nullable', 'string', Rule::in(['facebook'])],
        ]);

        $batch = isset($validated['batch_id'])
            ? Batch::query()->withCount('grantees')->findOrFail($validated['batch_id'])
            : Batch::query()
                ->withCount('grantees')
                ->orderByDesc('is_active')
                ->latest('updated_at')
                ->first();

        return response()->json([
            'data' => $this->buildTemplate($batch),
        ]);
    }

    public function integrationStatus(Request $request): JsonResponse
    {
        abort_unless($request->user(), 401);

        $hasPublishedAt = Schema::hasColumn('social_media_posts', 'published_at');
        $latestPost = SocialMediaPost::query()->latest('updated_at')->first();
        $latestPublished = SocialMediaPost::query()
            ->where('status', 'published')
            ->latest($hasPublishedAt ? 'published_at' : 'updated_at')
            ->first();
        $cachedPage = Cache::get('social_media.facebook_page');
        $page = is_array($cachedPage) ? $cachedPage : null;
        $n8nConfigured = trim((string) config('services.tcc_unifast_n8n.webhook_url')) !== ''
            && trim((string) config('services.tcc_unifast_n8n.webhook_secret')) !== '';
        $facebookConfirmed = is_array($page);
        $lastPageRefreshAt = Cache::get('social_media.facebook_page_refreshed_at');
        $n8nReachable = $latestPost !== null
            && ($latestPost->n8n_status === 'accepted'
                || data_get($latestPost->metadata, 'last_n8n_callback_at') !== null)
            || $lastPageRefreshAt !== null;
        $state = match (true) {
            ! $n8nConfigured => 'not_configured',
            $facebookConfirmed => 'connected',
            $latestPost === null => 'ready_for_first_post',
            $latestPost->status === 'draft' => 'draft_saved',
            $latestPost->status === 'failed' => 'failed',
            $latestPost->approval_mode === 'approval_required'
                && in_array($latestPost->status, ['queued', 'sent_to_n8n'], true) => 'awaiting_approval',
            default => 'awaiting_facebook_callback',
        };

        return response()->json([
            'data' => [
                'n8n_configured' => $n8nConfigured,
                'n8n_reachable' => $n8nReachable,
                'facebook_confirmed' => $facebookConfirmed,
                'state' => $state,
                'page' => $page,
                'latest_post' => $latestPost ? [
                    'id' => $latestPost->id,
                    'status' => $latestPost->status,
                    'approval_mode' => $latestPost->approval_mode,
                    'n8n_status' => $latestPost->n8n_status,
                    'error_message' => $latestPost->error_message,
                    'updated_at' => $latestPost->updated_at,
                ] : null,
                'counts' => [
                    'total' => SocialMediaPost::query()->count(),
                    'drafts' => SocialMediaPost::query()->where('status', 'draft')->count(),
                    'processing' => SocialMediaPost::query()->whereIn('status', ['queued', 'sent_to_n8n', 'scheduled'])->count(),
                    'published' => SocialMediaPost::query()->where('status', 'published')->count(),
                    'failed' => SocialMediaPost::query()->where('status', 'failed')->count(),
                ],
                'last_activity_at' => $latestPost?->updated_at,
                'last_published_at' => $latestPublished
                    ? ($hasPublishedAt ? $latestPublished->published_at : $latestPublished->updated_at)
                    : null,
                'page_refreshed_at' => $lastPageRefreshAt,
            ],
        ]);
    }

    public function refreshPageProfile(Request $request): JsonResponse
    {
        $this->authorizeMutation($request);

        $url = trim((string) config('services.tcc_unifast_n8n.webhook_url'));
        $headerName = (string) config('services.tcc_unifast_n8n.webhook_header', 'X-TCC-UniFAST-Key');
        $secret = (string) config('services.tcc_unifast_n8n.webhook_secret');

        abort_unless($url !== '' && $secret !== '', 503, 'The n8n webhook URL or shared secret is not configured.');

        $requestId = (string) Str::uuid();
        Cache::put('social_media.facebook_page_refresh_request_id', $requestId, now()->addMinutes(10));
        Cache::put('social_media.facebook_page_refresh_requested_at', now()->toIso8601String(), now()->addMinutes(10));

        $payload = [
            'event' => 'facebook_page_profile_requested',
            'request_id' => $requestId,
            'source' => 'laravel',
            'requested_at' => now()->toIso8601String(),
            'requested_by' => [
                'id' => $request->user()?->id,
                'name' => $request->user()?->name,
                'email' => $request->user()?->email,
                'role' => $request->user()?->role,
            ],
            'callback' => [
                'url' => rtrim((string) config('app.url'), '/').'/api/integrations/n8n/social-media-page/status',
                'method' => 'POST',
                'header' => 'X-TCC-UniFAST-Endpoint-Key',
            ],
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders([$headerName => $secret])
                ->timeout((int) config('services.tcc_unifast_n8n.timeout', 15))
                ->retry(2, 1000, throw: false)
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'The n8n Facebook Page profile workflow is currently unavailable.',
                'request_id' => $requestId,
                'error' => $exception->getMessage(),
            ], 503);
        }

        if ($response->failed()) {
            return response()->json([
                'message' => 'n8n rejected the Facebook Page profile request.',
                'request_id' => $requestId,
                'response' => $response->json() ?: ['body' => $response->body()],
            ], 502);
        }

        $this->audit($request, 'facebook_page_profile_requested', null, ['request_id' => $requestId]);

        return response()->json([
            'message' => 'Facebook Page profile refresh requested.',
            'request_id' => $requestId,
            'response' => $response->json() ?: ['body' => $response->body()],
        ], 202);
    }

    public function syncFacebookPosts(Request $request): JsonResponse
    {
        $this->authorizeMutation($request);

        $pageId = trim((string) config('services.facebook_page.page_id'));
        $accessToken = trim((string) config('services.facebook_page.access_token'));
        $apiVersion = trim((string) config('services.facebook_page.api_version', 'v26.0')) ?: 'v26.0';

        abort_unless($pageId !== '' && $accessToken !== '', 503, 'The Facebook Page ID or Page access token is not configured on the backend.');

        $limit = min(max((int) $request->integer('limit', 10), 1), 25);
        $baseFields = [
            'id',
            'message',
            'created_time',
            'updated_time',
            'permalink_url',
            'full_picture',
            'status_type',
        ];
        $engagementFields = [
            'reactions.summary(true).limit(0)',
            'comments.summary(true).limit(0)',
            'shares',
        ];
        $fields = implode(',', [...$baseFields, ...$engagementFields]);

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.facebook_page.timeout', 15))
                ->retry(2, 1000, throw: false)
                ->get("https://graph.facebook.com/{$apiVersion}/{$pageId}/posts", [
                    'fields' => $fields,
                    'limit' => $limit,
                    'access_token' => $accessToken,
                ]);

            if ($response->status() === 400 && (int) data_get($response->json(), 'error.code') === 10) {
                $response = Http::acceptJson()
                    ->timeout((int) config('services.facebook_page.timeout', 15))
                    ->retry(2, 1000, throw: false)
                    ->get("https://graph.facebook.com/{$apiVersion}/{$pageId}/posts", [
                        'fields' => implode(',', $baseFields),
                        'limit' => $limit,
                        'access_token' => $accessToken,
                    ]);
            }
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'Facebook Graph API is currently unavailable.',
                'error' => $exception->getMessage(),
            ], 503);
        }

        if ($response->failed()) {
            $body = $response->json();

            return response()->json([
                'message' => 'Facebook rejected the Page posts sync request.',
                'response' => is_array($body) ? [
                    'error' => data_get($body, 'error'),
                ] : ['body' => $response->body()],
            ], 502);
        }

        $rows = collect($response->json('data') ?? []);
        $imported = $rows
            ->filter(fn ($row): bool => is_array($row) && filled(data_get($row, 'id')))
            ->map(function (array $row) use ($request): SocialMediaPost {
                $message = trim((string) data_get($row, 'message', ''));
                $externalPostId = (string) data_get($row, 'id');
                $createdTime = data_get($row, 'created_time');
                $permalink = data_get($row, 'permalink_url');
                $engagement = [
                    'reactions' => (int) data_get($row, 'reactions.summary.total_count', 0),
                    'comments' => (int) data_get($row, 'comments.summary.total_count', 0),
                    'shares' => (int) data_get($row, 'shares.count', 0),
                ];
                $titleSource = Str::of($message !== '' ? $message : 'Facebook Page Post')
                    ->squish()
                    ->limit(80, '')
                    ->value();

                $metadata = [
                    'source' => 'facebook-page-sync',
                    'synced_by' => [
                        'id' => $request->user()?->id,
                        'name' => $request->user()?->name,
                    ],
                    'synced_at' => now()->toIso8601String(),
                    'facebook' => [
                        'status_type' => data_get($row, 'status_type'),
                        'full_picture' => data_get($row, 'full_picture'),
                        'engagement' => $engagement,
                        'raw' => $row,
                    ],
                ];

                $post = SocialMediaPost::query()->firstOrNew([
                    'channel' => 'facebook',
                    'external_post_id' => $externalPostId,
                ]);

                $post->fill([
                    'title' => $titleSource !== '' ? $titleSource : 'Facebook Page Post',
                    'message' => $message !== '' ? $message : '(No text content)',
                    'campaign' => 'facebook_page_sync',
                    'status' => 'published',
                    'approval_mode' => 'pre_approved',
                    'external_permalink' => is_string($permalink) ? $permalink : $post->external_permalink,
                    'n8n_status' => $post->n8n_status ?? 'facebook_sync',
                    'n8n_response' => $row,
                    'metadata' => array_replace_recursive($post->metadata ?? [], $metadata),
                ]);

                if (! $post->exists) {
                    $post->created_by = $request->user()?->id;
                    $post->created_at = $createdTime ? Carbon::parse($createdTime) : now();
                }

                if (Schema::hasColumn('social_media_posts', 'published_at')) {
                    $post->published_at = $createdTime ? Carbon::parse($createdTime) : ($post->published_at ?? now());
                }

                $post->save();

                return $post->fresh(['batch', 'creator']);
            });

        $this->audit($request, 'facebook_posts_synced', null, [
            'count' => $imported->count(),
            'page_id' => $pageId,
        ]);

        return response()->json([
            'message' => 'Facebook Page posts synced.',
            'count' => $imported->count(),
            'data' => $imported->map(fn (SocialMediaPost $post) => $this->present($post))->values(),
        ]);
    }

    public function reactAsPage(Request $request, SocialMediaPost $socialMediaPost): JsonResponse
    {
        $this->authorizeMutation($request);

        abort_unless($socialMediaPost->channel === 'facebook', 422, 'Only Facebook posts can receive Page reactions.');
        abort_unless(filled($socialMediaPost->external_post_id), 409, 'Sync or publish this post to Facebook before reacting as the Page.');

        $accessToken = trim((string) config('services.facebook_page.access_token'));
        $apiVersion = trim((string) config('services.facebook_page.api_version', 'v26.0')) ?: 'v26.0';

        abort_unless($accessToken !== '', 503, 'The Facebook Page access token is not configured on the backend.');

        $metadata = $socialMediaPost->metadata ?? [];
        $alreadyReacted = (bool) data_get($metadata, 'facebook.page_reacted');

        try {
            $facebookRequest = Http::acceptJson()
                ->asForm()
                ->timeout((int) config('services.facebook_page.timeout', 15))
                ->retry(2, 1000, throw: false);

            $response = $alreadyReacted
                ? $facebookRequest->delete("https://graph.facebook.com/{$apiVersion}/{$socialMediaPost->external_post_id}/likes", [
                    'access_token' => $accessToken,
                ])
                : $facebookRequest->post("https://graph.facebook.com/{$apiVersion}/{$socialMediaPost->external_post_id}/likes", [
                    'access_token' => $accessToken,
                ]);
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'Facebook Graph API is currently unavailable.',
                'error' => $exception->getMessage(),
            ], 503);
        }

        if ($response->failed()) {
            $body = $response->json();
            $graphError = is_array($body) ? data_get($body, 'error') : null;

            return response()->json([
                'message' => 'Facebook rejected the Page reaction request.',
                'response' => [
                    'error' => is_array($graphError) ? [
                        'message' => data_get($graphError, 'message'),
                        'type' => data_get($graphError, 'type'),
                        'code' => data_get($graphError, 'code'),
                        'subcode' => data_get($graphError, 'error_subcode'),
                    ] : null,
                ],
            ], 502);
        }

        $engagement = [
            'reactions' => (int) data_get($metadata, 'facebook.engagement.reactions', 0),
            'comments' => (int) data_get($metadata, 'facebook.engagement.comments', 0),
            'shares' => (int) data_get($metadata, 'facebook.engagement.shares', 0),
        ];

        if ($alreadyReacted) {
            $engagement['reactions'] = max(0, $engagement['reactions'] - 1);
        } else {
            $engagement['reactions'] = $engagement['reactions'] + 1;
        }

        data_set($metadata, 'facebook.engagement', $engagement);
        data_set($metadata, 'facebook.page_reacted', ! $alreadyReacted);

        if ($alreadyReacted) {
            data_forget($metadata, 'facebook.page_reaction_type');
            data_forget($metadata, 'facebook.page_reacted_at');
            data_forget($metadata, 'facebook.page_reacted_by');
        } else {
            data_set($metadata, 'facebook.page_reaction_type', 'LIKE');
            data_set($metadata, 'facebook.page_reacted_at', now()->toIso8601String());
            data_set($metadata, 'facebook.page_reacted_by', [
                'id' => $request->user()?->id,
                'name' => $request->user()?->name,
            ]);
        }

        $socialMediaPost->metadata = $metadata;
        $socialMediaPost->save();

        $this->audit($request, $alreadyReacted ? 'facebook_page_unreacted' : 'facebook_page_reacted', $socialMediaPost, [
            'external_post_id' => $socialMediaPost->external_post_id,
            'type' => 'LIKE',
        ]);

        return response()->json([
            'message' => $alreadyReacted
                ? 'Removed the Facebook Page reaction from the post.'
                : 'Reacted to the Facebook post as the Page.',
            'data' => $this->present($socialMediaPost->fresh(['batch', 'creator'])),
        ]);
    }

    public function comments(Request $request, SocialMediaPost $socialMediaPost): JsonResponse
    {
        $this->authorizeMutation($request);

        abort_unless($socialMediaPost->channel === 'facebook', 422, 'Only Facebook posts can load comments.');
        abort_unless(filled($socialMediaPost->external_post_id), 409, 'Sync or publish this post to Facebook before loading comments.');

        $accessToken = trim((string) config('services.facebook_page.access_token'));
        $apiVersion = trim((string) config('services.facebook_page.api_version', 'v26.0')) ?: 'v26.0';

        abort_unless($accessToken !== '', 503, 'The Facebook Page access token is not configured on the backend.');

        $limit = min(max((int) $request->integer('limit', 25), 1), 50);

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.facebook_page.timeout', 15))
                ->retry(2, 1000, throw: false)
                ->get("https://graph.facebook.com/{$apiVersion}/{$socialMediaPost->external_post_id}/comments", [
                    'fields' => 'id,message,created_time,from,like_count,comment_count',
                    'filter' => 'stream',
                    'summary' => true,
                    'limit' => $limit,
                    'access_token' => $accessToken,
                ]);
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'Facebook Graph API is currently unavailable.',
                'error' => $exception->getMessage(),
            ], 503);
        }

        if ($response->failed()) {
            return $this->facebookGraphErrorResponse($response, 'Facebook rejected the Page comments request.');
        }

        $comments = collect($response->json('data') ?? [])
            ->filter(fn ($row): bool => is_array($row) && filled(data_get($row, 'id')))
            ->map(fn (array $row): array => $this->presentFacebookComment($row))
            ->values();

        $metadata = $socialMediaPost->metadata ?? [];
        $engagement = [
            'reactions' => (int) data_get($metadata, 'facebook.engagement.reactions', 0),
            'comments' => (int) data_get($response->json(), 'summary.total_count', $comments->count()),
            'shares' => (int) data_get($metadata, 'facebook.engagement.shares', 0),
        ];

        data_set($metadata, 'facebook.engagement', $engagement);
        data_set($metadata, 'facebook.comments', $comments->all());
        data_set($metadata, 'facebook.comments_synced_at', now()->toIso8601String());

        $socialMediaPost->metadata = $metadata;
        $socialMediaPost->save();

        return response()->json([
            'message' => 'Facebook comments loaded.',
            'count' => $comments->count(),
            'data' => $comments,
            'post' => $this->present($socialMediaPost->fresh(['batch', 'creator'])),
        ]);
    }

    public function commentAsPage(Request $request, SocialMediaPost $socialMediaPost): JsonResponse
    {
        $this->authorizeMutation($request);

        abort_unless($socialMediaPost->channel === 'facebook', 422, 'Only Facebook posts can receive Page comments.');
        abort_unless(filled($socialMediaPost->external_post_id), 409, 'Sync or publish this post to Facebook before commenting as the Page.');

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
        ]);

        $message = trim((string) $validated['message']);
        abort_unless($message !== '', 422, 'Enter a comment before posting.');

        $accessToken = trim((string) config('services.facebook_page.access_token'));
        $apiVersion = trim((string) config('services.facebook_page.api_version', 'v26.0')) ?: 'v26.0';

        abort_unless($accessToken !== '', 503, 'The Facebook Page access token is not configured on the backend.');

        try {
            $response = Http::acceptJson()
                ->asForm()
                ->timeout((int) config('services.facebook_page.timeout', 15))
                ->retry(2, 1000, throw: false)
                ->post("https://graph.facebook.com/{$apiVersion}/{$socialMediaPost->external_post_id}/comments", [
                    'message' => $message,
                    'access_token' => $accessToken,
                ]);
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'Facebook Graph API is currently unavailable.',
                'error' => $exception->getMessage(),
            ], 503);
        }

        if ($response->failed()) {
            return $this->facebookGraphErrorResponse($response, 'Facebook rejected the Page comment request.');
        }

        $metadata = $socialMediaPost->metadata ?? [];
        $engagement = [
            'reactions' => (int) data_get($metadata, 'facebook.engagement.reactions', 0),
            'comments' => (int) data_get($metadata, 'facebook.engagement.comments', 0) + 1,
            'shares' => (int) data_get($metadata, 'facebook.engagement.shares', 0),
        ];
        $comment = [
            'id' => (string) data_get($response->json(), 'id', ''),
            'message' => $message,
            'author_name' => (string) config('services.facebook_page.name', 'Facebook Page'),
            'author_id' => (string) config('services.facebook_page.page_id', ''),
            'created_at' => now()->toIso8601String(),
            'like_count' => 0,
            'comment_count' => 0,
        ];

        data_set($metadata, 'facebook.engagement', $engagement);
        data_set($metadata, 'facebook.last_comment', [
            ...$comment,
            'created_by' => [
                'id' => $request->user()?->id,
                'name' => $request->user()?->name,
            ],
        ]);

        $socialMediaPost->metadata = $metadata;
        $socialMediaPost->save();

        $this->audit($request, 'facebook_page_commented', $socialMediaPost, [
            'external_post_id' => $socialMediaPost->external_post_id,
            'comment_id' => $comment['id'],
        ]);

        return response()->json([
            'message' => 'Commented on the Facebook post as the Page.',
            'data' => $comment,
            'post' => $this->present($socialMediaPost->fresh(['batch', 'creator'])),
        ], 201);
    }

    public function receivePageStatus(Request $request): JsonResponse
    {
        $configuredSecret = (string) config('services.tcc_unifast_n8n.endpoint_secret');
        $providedSecret = (string) $request->header('X-TCC-UniFAST-Endpoint-Key');
        abort_unless(
            $configuredSecret !== '' && hash_equals($configuredSecret, $providedSecret),
            401,
            'Invalid n8n callback key.'
        );

        $validated = $request->validate([
            'request_id' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', Rule::in(['profile_loaded', 'failed'])],
            'error_message' => ['nullable', 'string', 'max:3000'],
            'facebook_page' => ['required_if:status,profile_loaded', 'array'],
            'facebook_page.id' => ['nullable', 'string', 'max:255'],
            'facebook_page.name' => ['nullable', 'string', 'max:255'],
            'facebook_page.url' => ['nullable', 'url', 'max:2000'],
            'facebook_page.picture_url' => ['nullable', 'url', 'max:2000'],
            'facebook_page.cover_url' => ['nullable', 'url', 'max:2000'],
            'facebook_page.followers_count' => ['nullable', 'integer', 'min:0'],
            'facebook_page.fan_count' => ['nullable', 'integer', 'min:0'],
            'response' => ['nullable', 'array'],
        ]);

        $expectedRequestId = Cache::get('social_media.facebook_page_refresh_request_id');
        // Require a stored request_id AND verify it matches — if the cache key has expired,
        // the callback is stale/unexpected and must be rejected to prevent data injection.
        abort_unless(
            $expectedRequestId !== null && hash_equals((string) $expectedRequestId, $validated['request_id']),
            409,
            'The callback request ID does not match the latest Facebook Page profile request.'
        );

        if ($validated['status'] === 'failed') {
            Cache::put('social_media.facebook_page_refresh_error', $validated['error_message'] ?? 'The Facebook workflow reported a failure.', now()->addMinutes(30));

            return response()->json(['message' => 'Facebook Page profile refresh failure recorded.']);
        }

        Cache::put('social_media.facebook_page', $validated['facebook_page'], now()->addDays(30));
        Cache::put('social_media.facebook_page_refreshed_at', now()->toIso8601String(), now()->addDays(30));
        Cache::forget('social_media.facebook_page_refresh_error');

        AuditLog::create([
            'actor' => 'n8n Facebook Workflow',
            'role' => 'Integration',
            'action' => 'facebook_page_profile_loaded',
            'module' => 'Social Media Posts',
            'target' => data_get($validated, 'facebook_page.name', 'Facebook Page'),
            'context' => [
                'request_id' => $validated['request_id'],
                'facebook_page_id' => data_get($validated, 'facebook_page.id'),
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Facebook Page profile updated.',
            'data' => [
                'page' => $validated['facebook_page'],
            ],
        ]);
    }

    public function receiveStatus(Request $request, SocialMediaPost $socialMediaPost): JsonResponse
    {
        $configuredSecret = (string) config('services.tcc_unifast_n8n.endpoint_secret');
        $providedSecret = (string) $request->header('X-TCC-UniFAST-Endpoint-Key');
        abort_unless(
            $configuredSecret !== '' && hash_equals($configuredSecret, $providedSecret),
            401,
            'Invalid n8n callback key.'
        );

        $validated = $request->validate([
            'request_id' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', Rule::in(['accepted', 'scheduled', 'published', 'failed', 'rejected'])],
            'external_post_id' => ['nullable', 'string', 'max:255'],
            'external_permalink' => ['nullable', 'url', 'max:2000'],
            'published_at' => ['nullable', 'date'],
            'error_message' => ['nullable', 'string', 'max:3000'],
            'facebook_page' => ['nullable', 'array'],
            'facebook_page.id' => ['nullable', 'string', 'max:255'],
            'facebook_page.name' => ['nullable', 'string', 'max:255'],
            'facebook_page.url' => ['nullable', 'url', 'max:2000'],
            'facebook_page.picture_url' => ['nullable', 'url', 'max:2000'],
            'facebook_page.cover_url' => ['nullable', 'url', 'max:2000'],
            'facebook_page.followers_count' => ['nullable', 'integer', 'min:0'],
            'facebook_page.fan_count' => ['nullable', 'integer', 'min:0'],
            'response' => ['nullable', 'array'],
        ]);

        abort_if(
            $socialMediaPost->n8n_request_id === null
                || ! hash_equals($socialMediaPost->n8n_request_id, $validated['request_id']),
            409,
            'The callback request ID does not match this social post.'
        );

        $status = match ($validated['status']) {
            'published' => 'published',
            'scheduled' => 'scheduled',
            'failed', 'rejected' => 'failed',
            default => 'sent_to_n8n',
        };
        $metadata = $socialMediaPost->metadata ?? [];
        if (isset($validated['facebook_page'])) {
            $metadata['facebook_page'] = $validated['facebook_page'];
        }
        $metadata['last_n8n_callback_at'] = now()->toIso8601String();

        $updates = [
            'status' => $status,
            'n8n_status' => $validated['status'],
            'n8n_response' => $validated['response'] ?? $socialMediaPost->n8n_response,
            'external_post_id' => $validated['external_post_id'] ?? $socialMediaPost->external_post_id,
            'external_permalink' => $validated['external_permalink'] ?? $socialMediaPost->external_permalink,
            'error_message' => in_array($status, ['failed'], true)
                ? ($validated['error_message'] ?? 'The Facebook workflow reported a failure.')
                : null,
            'metadata' => $metadata,
        ];
        if (Schema::hasColumn('social_media_posts', 'published_at')) {
            $updates['published_at'] = $status === 'published'
                ? ($validated['published_at'] ?? now())
                : $socialMediaPost->published_at;
        }
        $socialMediaPost->update($updates);

        AuditLog::create([
            'actor' => 'n8n Facebook Workflow',
            'role' => 'Integration',
            'action' => 'social_post_status_'.$status,
            'module' => 'Social Media Posts',
            'target' => $socialMediaPost->title,
            'context' => [
                'request_id' => $validated['request_id'],
                'external_post_id' => $socialMediaPost->external_post_id,
                'external_permalink' => $socialMediaPost->external_permalink,
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Social post status updated.',
            'data' => $this->present($socialMediaPost->fresh(['batch', 'creator'])),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeMutation($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:1', 'max:5000'],
            'channel' => ['required', 'string', Rule::in(['facebook'])],
            'campaign' => ['nullable', 'string', 'max:120'],
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'approval_mode' => ['required', 'string', Rule::in(['approval_required', 'pre_approved'])],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'metadata' => ['nullable', 'array'],
        ]);

        $post = SocialMediaPost::create([
            ...$validated,
            'status' => 'draft',
            'created_by' => $request->user()?->id,
        ]);

        $this->audit($request, 'created', $post, ['channel' => $post->channel, 'campaign' => $post->campaign]);

        return response()->json(['data' => $this->present($post->load(['batch', 'creator']))], 201);
    }

    public function show(Request $request, SocialMediaPost $socialMediaPost): JsonResponse
    {
        abort_unless($request->user(), 401);

        return response()->json([
            'data' => $this->present($socialMediaPost->load(['batch', 'creator'])),
        ]);
    }

    public function dispatch(Request $request, SocialMediaPost $socialMediaPost): JsonResponse
    {
        $this->authorizeMutation($request);

        $validated = $request->validate([
            'approval_mode' => ['sometimes', 'string', Rule::in(['pre_approved'])],
        ]);
        $isApprovalRedispatch = $socialMediaPost->status === 'sent_to_n8n'
            && $socialMediaPost->approval_mode === 'approval_required'
            && ($validated['approval_mode'] ?? null) === 'pre_approved';

        abort_if(
            in_array($socialMediaPost->status, ['queued', 'sent_to_n8n', 'published'], true)
                && ! $isApprovalRedispatch,
            409,
            'This social post has already been sent for publishing.'
        );

        if ($isApprovalRedispatch) {
            $socialMediaPost->approval_mode = 'pre_approved';
        }

        $url = trim((string) config('services.tcc_unifast_n8n.webhook_url'));
        $headerName = (string) config('services.tcc_unifast_n8n.webhook_header', 'X-TCC-UniFAST-Key');
        $secret = (string) config('services.tcc_unifast_n8n.webhook_secret');

        abort_unless($url !== '', 503, 'The n8n webhook URL is not configured.');

        $requestId = (string) Str::uuid();
        $batch = $socialMediaPost->batch ?: ($socialMediaPost->batch_id ? Batch::find($socialMediaPost->batch_id) : null);
        $payload = [
            'event' => 'social_post_publish_requested',
            'request_id' => $requestId,
            'source' => 'laravel',
            'requested_at' => now()->toIso8601String(),
            'requested_by' => [
                'id' => $request->user()?->id,
                'name' => $request->user()?->name,
                'email' => $request->user()?->email,
                'role' => $request->user()?->role,
            ],
            'social_post' => [
                'id' => $socialMediaPost->id,
                'title' => $socialMediaPost->title,
                'channel' => $socialMediaPost->channel,
                'campaign' => $socialMediaPost->campaign,
                'approval_mode' => $socialMediaPost->approval_mode,
                'message' => $socialMediaPost->message,
                'scheduled_for' => $socialMediaPost->scheduled_for?->toIso8601String(),
            ],
            'batch' => $batch ? [
                'id' => $batch->id,
                'name' => $batch->name,
                'academic_year' => $batch->academic_year,
                'semester' => $batch->semester,
                'submission_deadline' => $batch->submission_deadline?->toIso8601String(),
                'grantees_count' => $batch->grantees()->count(),
            ] : null,
            'callback' => [
                'url' => rtrim((string) config('app.url'), '/')
                    .'/api/integrations/n8n/social-media-posts/'.$socialMediaPost->id.'/status',
                'method' => 'POST',
                'header' => 'X-TCC-UniFAST-Endpoint-Key',
            ],
        ];

        $socialMediaPost->update([
            'status' => 'queued',
            'submitted_at' => now(),
            'n8n_request_id' => $requestId,
            'n8n_status' => 'sending',
            'error_message' => null,
        ]);

        try {
            $headers = $secret !== '' ? [$headerName => $secret] : [];
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders($headers)
                ->timeout((int) config('services.tcc_unifast_n8n.timeout', 15))
                ->retry(2, 1000, throw: false)
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            $socialMediaPost->update([
                'status' => 'failed',
                'n8n_status' => 'connection_failed',
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'The n8n publishing workflow is currently unavailable.',
                'request_id' => $requestId,
                'data' => $this->present($socialMediaPost->fresh(['batch', 'creator'])),
            ], 503);
        }

        if ($response->failed()) {
            $responseBody = $response->json() ?: ['body' => $response->body()];
            $errorMessage = (string) data_get($responseBody, 'error', 'n8n rejected the publish request.');

            $socialMediaPost->update([
                'status' => 'failed',
                'n8n_status' => 'http_'.$response->status(),
                'n8n_response' => $responseBody,
                'error_message' => $errorMessage,
            ]);

            return response()->json([
                'message' => $errorMessage,
                'request_id' => $requestId,
                'data' => $this->present($socialMediaPost->fresh(['batch', 'creator'])),
            ], 502);
        }

        $socialMediaPost->update([
            'status' => 'sent_to_n8n',
            'n8n_status' => 'accepted',
            'n8n_response' => $response->json() ?: ['body' => $response->body()],
        ]);

        $this->audit($request, 'sent_to_n8n', $socialMediaPost, [
            'request_id' => $requestId,
            'channel' => $socialMediaPost->channel,
            'campaign' => $socialMediaPost->campaign,
        ]);

        return response()->json([
            'message' => 'Social post sent to n8n for publishing approval.',
            'request_id' => $requestId,
            'data' => $this->present($socialMediaPost->fresh(['batch', 'creator'])),
        ], 202);
    }

    private function authorizeMutation(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['developer', 'admin', 'head', 'staff'], true), 403);
    }

    private function present(SocialMediaPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'message' => $post->message,
            'channel' => $post->channel,
            'campaign' => $post->campaign,
            'status' => $post->status,
            'approval_mode' => $post->approval_mode,
            'scheduled_for' => $post->scheduled_for,
            'submitted_at' => $post->submitted_at,
            'published_at' => $post->published_at,
            'n8n_request_id' => $post->n8n_request_id,
            'n8n_status' => $post->n8n_status,
            'error_message' => $post->error_message,
            'external_post_id' => $post->external_post_id,
            'external_permalink' => $post->external_permalink,
            'engagement' => [
                'reactions' => (int) data_get($post->metadata, 'facebook.engagement.reactions', 0),
                'comments' => (int) data_get($post->metadata, 'facebook.engagement.comments', 0),
                'shares' => (int) data_get($post->metadata, 'facebook.engagement.shares', 0),
            ],
            'page_reacted' => (bool) data_get($post->metadata, 'facebook.page_reacted', false),
            'page_reaction_type' => data_get($post->metadata, 'facebook.page_reaction_type'),
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
            'batch' => $post->batch ? [
                'id' => $post->batch->id,
                'name' => $post->batch->name,
                'academic_year' => $post->batch->academic_year,
                'semester' => $post->batch->semester,
                'submission_deadline' => $post->batch->submission_deadline,
            ] : null,
            'creator' => $post->creator ? [
                'id' => $post->creator->id,
                'name' => $post->creator->name,
                'email' => $post->creator->email,
            ] : null,
        ];
    }

    private function presentFacebookComment(array $row): array
    {
        return [
            'id' => (string) data_get($row, 'id'),
            'message' => (string) data_get($row, 'message', ''),
            'author_name' => (string) data_get($row, 'from.name', 'Facebook user'),
            'author_id' => data_get($row, 'from.id'),
            'created_at' => data_get($row, 'created_time'),
            'like_count' => (int) data_get($row, 'like_count', 0),
            'comment_count' => (int) data_get($row, 'comment_count', 0),
        ];
    }

    private function facebookGraphErrorResponse($response, string $message): JsonResponse
    {
        $body = $response->json();
        $graphError = is_array($body) ? data_get($body, 'error') : null;

        return response()->json([
            'message' => $message,
            'response' => [
                'error' => is_array($graphError) ? [
                    'message' => data_get($graphError, 'message'),
                    'type' => data_get($graphError, 'type'),
                    'code' => data_get($graphError, 'code'),
                    'subcode' => data_get($graphError, 'error_subcode'),
                ] : null,
            ],
        ], 502);
    }

    private function buildTemplate(?Batch $batch): array
    {
        $portalUrl = rtrim((string) config('app.frontend_url'), '/').'/login';
        $supportEmail = (string) config('mail.from.address', 'info@tcc.edu.ph');
        $campaign = $batch
            ? Str::of($batch->name)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value()
            : 'unifast_tes_announcement';

        if ($campaign === '') {
            $campaign = 'batch_release';
        }

        $deadline = $batch?->submission_deadline;
        $deadlineText = $deadline
            ? $deadline->timezone('Asia/Manila')->format('F j, Y \a\t g:i A')
            : 'the deadline announced by the UniFAST/TES office';
        $windowStatus = $batch?->computedWindowStatus();
        $granteeCount = $batch?->grantees_count ?? 0;
        $batchName = $batch?->name ?? 'UniFAST TES';
        $academicContext = $batch
            ? trim("{$batch->academic_year} {$batch->semester}")
            : 'the current application period';

        $message = implode("\n\n", array_filter([
            "TCC UniFAST TES Advisory: {$batchName}",
            "Tagoloan Community College informs qualified TES grantees for {$academicContext} that the student portal is ready for account access, verification, and requirements submission.",
            $batch ? "Linked batch: {$batch->name}\nSubmission window status: ".Str::headline((string) $windowStatus)."\nListed grantees: ".number_format($granteeCount) : null,
            "Deadline: {$deadlineText}",
            'Students are advised to sign in through the official portal, review their requirements, and complete submissions before the deadline. Use only official TCC and UniFAST channels for updates.',
            "Portal: {$portalUrl}",
            "For assistance, contact the UniFAST/TES office or email {$supportEmail}.",
            '#TCCUniFAST #TES #TagoloanCommunityCollege',
        ]));

        return [
            'title' => "{$batchName} Facebook Advisory",
            'channel' => 'facebook',
            'campaign' => $campaign,
            'batch_id' => $batch?->id,
            'approval_mode' => 'approval_required',
            'scheduled_for' => null,
            'message' => $message,
            'facts' => [
                'portal_url' => $portalUrl,
                'support_email' => $supportEmail,
                'deadline' => $deadline?->toIso8601String(),
                'deadline_label' => $deadlineText,
                'grantees_count' => $granteeCount,
                'window_status' => $windowStatus,
                'generated_at' => now()->toIso8601String(),
            ],
            'batch' => $batch ? [
                'id' => $batch->id,
                'name' => $batch->name,
                'academic_year' => $batch->academic_year,
                'semester' => $batch->semester,
                'submission_deadline' => $batch->submission_deadline,
                'is_active' => $batch->is_active,
                'window_status' => $windowStatus,
                'grantees_count' => $granteeCount,
            ] : null,
        ];
    }

    private function audit(Request $request, string $action, ?SocialMediaPost $post, array $context = []): void
    {
        AuditLog::create([
            'actor' => $request->user()?->name ?? 'System',
            'role' => ucfirst((string) ($request->user()?->role ?? 'system')),
            'action' => "social_post_{$action}",
            'module' => 'Social Media Posts',
            'target' => $post?->title ?? 'Facebook Page',
            'context' => $context,
            'ip_address' => $request->ip(),
        ]);
    }
}
