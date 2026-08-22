<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\SocialMediaPost;
use App\Support\PaginatedJson;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $latestWithPage = SocialMediaPost::query()
            ->whereNotNull('metadata')
            ->latest('updated_at')
            ->get()
            ->first(fn (SocialMediaPost $post) => is_array(data_get($post->metadata, 'facebook_page')));
        $page = $latestWithPage ? data_get($latestWithPage->metadata, 'facebook_page') : null;
        $n8nConfigured = trim((string) config('services.tcc_unifast_n8n.webhook_url')) !== ''
            && trim((string) config('services.tcc_unifast_n8n.webhook_secret')) !== '';
        $facebookConfirmed = is_array($page) || $latestPublished !== null;
        $n8nReachable = $latestPost !== null
            && ($latestPost->n8n_status === 'accepted'
                || data_get($latestPost->metadata, 'last_n8n_callback_at') !== null);
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
            'message' => ['required', 'string', 'min:20', 'max:5000'],
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
            $socialMediaPost->update([
                'status' => 'failed',
                'n8n_status' => 'http_'.$response->status(),
                'n8n_response' => $response->json() ?: ['body' => $response->body()],
                'error_message' => 'n8n rejected the publish request.',
            ]);

            return response()->json([
                'message' => 'n8n rejected the publish request.',
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

    private function audit(Request $request, string $action, SocialMediaPost $post, array $context = []): void
    {
        AuditLog::create([
            'actor' => $request->user()?->name ?? 'System',
            'role' => ucfirst((string) ($request->user()?->role ?? 'system')),
            'action' => "social_post_{$action}",
            'module' => 'Social Media Posts',
            'target' => $post->title,
            'context' => $context,
            'ip_address' => $request->ip(),
        ]);
    }
}
