<?php

namespace App\Modules\Addons\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MultivariantCampaign;
use App\Models\Marketing\Publication;
use App\Models\Marketing\PublicationChannel;
use App\Modules\Addons\Marketing\Services\Publishing\ChannelManager;
use App\Modules\Addons\Marketing\Services\Publishing\PostPublisherService;
use App\Modules\Addons\Marketing\Services\Publishing\SmartRouter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PublishingController extends Controller
{
    public function __construct(
        private ChannelManager     $channelManager,
        private PostPublisherService $publisher,
        private SmartRouter        $smartRouter,
    ) {}

    // ── Canales ──────────────────────────────────────────────────────────────

    public function listChannels(Request $request): JsonResponse
    {
        $channels = PublicationChannel::forCompany(1)
            ->orderBy('platform')
            ->orderBy('channel_type')
            ->get()
            ->map(fn($ch) => [
                'id'                         => $ch->id,
                'platform'                   => $ch->platform,
                'channel_type'               => $ch->channel_type,
                'name'                       => $ch->name,
                'slug'                       => $ch->slug,
                'active'                     => $ch->active,
                'credentials_ready'          => $ch->credentials_ready,
                'credentials_status_message' => $ch->credentials_status_message,
                'credentials_validated_at'   => $ch->credentials_validated_at,
                'supported_aspect_ratios'    => $ch->supported_aspect_ratios,
                'max_duration_seconds'       => $ch->max_duration_seconds,
            ]);

        return response()->json(['channels' => $channels]);
    }

    public function validateChannel(Request $request, int $id): JsonResponse
    {
        $channel = PublicationChannel::findOrFail($id);
        $driver  = $this->channelManager->getDriver($channel);
        $result  = $driver->validateCredentials();

        $channel->update([
            'credentials_ready'          => $result['valid'],
            'credentials_status_message' => $result['message'],
            'credentials_validated_at'   => now(),
        ]);

        return response()->json($result);
    }

    public function updateChannelConfig(Request $request, int $id): JsonResponse
    {
        $channel = PublicationChannel::findOrFail($id);
        $data    = $request->validate([
            'platform_config' => 'array',
            'active'          => 'boolean',
        ]);

        $channel->update($data);

        return response()->json(['updated' => true, 'channel' => $channel]);
    }

    // ── Smart Router ─────────────────────────────────────────────────────────

    public function routeCampaign(Request $request, int $campaignId): JsonResponse
    {
        $campaign = MultivariantCampaign::findOrFail($campaignId);
        $routing  = $this->smartRouter->routeCampaign(
            $campaign->variant_content_ids ?? [],
            $campaign->company_id,
        );

        return response()->json(['routing' => $routing]);
    }

    // ── Publicaciones ────────────────────────────────────────────────────────

    public function publishCampaign(Request $request, int $campaignId): JsonResponse
    {
        $data = $request->validate([
            'channel_ids'    => 'required|array',
            'channel_ids.*'  => 'integer|exists:marketing_publication_channels,id',
            'scheduled_for'  => 'nullable|date',
            'caption'        => 'nullable|string|max:2200',
            'hashtags'       => 'nullable|array',
        ]);

        $campaign     = MultivariantCampaign::findOrFail($campaignId);
        $scheduledFor = isset($data['scheduled_for']) ? Carbon::parse($data['scheduled_for']) : null;

        $publications = $this->publisher->queueCampaign(
            campaign:     $campaign,
            channelIds:   $data['channel_ids'],
            scheduledFor: $scheduledFor,
            caption:      $data['caption'] ?? '',
            hashtags:     $data['hashtags'] ?? [],
        );

        return response()->json([
            'queued'       => count($publications),
            'publications' => collect($publications)->map(fn($p) => [
                'id'           => $p->id,
                'status'       => $p->status,
                'channel_id'   => $p->pub_channel_id,
                'scheduled_for'=> $p->scheduled_for,
            ]),
        ]);
    }

    public function listPublications(Request $request): JsonResponse
    {
        $query = Publication::with(['pubChannel', 'content'])
            ->where('company_id', 1)
            ->whereNotNull('pub_channel_id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($platform = $request->query('platform')) {
            $query->whereHas('pubChannel', fn($q) => $q->where('platform', $platform));
        }
        if ($campaignId = $request->query('campaign_id')) {
            $query->where('campaign_id', $campaignId);
        }

        $publications = $query->orderByDesc('created_at')->paginate(30);

        return response()->json($publications);
    }

    public function showPublication(int $id): JsonResponse
    {
        $pub = Publication::with(['pubChannel', 'content', 'logs'])->findOrFail($id);
        return response()->json($pub);
    }

    public function retryPublication(int $id): JsonResponse
    {
        $pub = Publication::findOrFail($id);
        if (!in_array($pub->status, ['failed', 'waiting_credentials'])) {
            return response()->json(['error' => 'Solo se pueden reintentar publicaciones fallidas'], 422);
        }

        $result = $this->publisher->retry($pub);
        return response()->json($result);
    }

    public function cancelPublication(int $id): JsonResponse
    {
        $pub    = Publication::findOrFail($id);
        $result = $this->publisher->cancel($pub);
        return response()->json($result);
    }

    public function fetchMetrics(int $id): JsonResponse
    {
        $pub     = Publication::findOrFail($id);
        $metrics = $this->publisher->fetchMetricsForPublication($pub);
        return response()->json(['metrics' => $metrics]);
    }

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function dashboardStats(): JsonResponse
    {
        $base = Publication::where('company_id', 1)->whereNotNull('pub_channel_id');

        $stats = [
            'total'               => (clone $base)->count(),
            'published'           => (clone $base)->where('status', 'published')->count(),
            'queued'              => (clone $base)->whereIn('status', ['queued', 'scheduled'])->count(),
            'failed'              => (clone $base)->where('status', 'failed')->count(),
            'waiting_credentials' => (clone $base)->where('status', 'waiting_credentials')->count(),
        ];

        $byChannel = PublicationChannel::forCompany(1)->withCount([
            'publications as published_count' => fn($q) => $q->where('status', 'published'),
        ])->get()->map(fn($ch) => [
            'channel_name'    => $ch->name,
            'platform'        => $ch->platform,
            'credentials_ready'=> $ch->credentials_ready,
            'published_count' => $ch->published_count,
        ]);

        return response()->json(['stats' => $stats, 'by_channel' => $byChannel]);
    }

    public function dashboardRecent(): JsonResponse
    {
        $recent = Publication::with('pubChannel')
            ->where('company_id', 1)
            ->where('status', 'published')
            ->whereNotNull('pub_channel_id')
            ->orderByDesc('published_at')
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id'               => $p->id,
                'channel_name'     => $p->pubChannel?->name,
                'platform'         => $p->pubChannel?->platform,
                'published_at'     => $p->published_at,
                'external_post_url'=> $p->external_post_url,
                'ab_variant_tag'   => $p->ab_variant_tag,
                'metrics'          => $p->metrics,
            ]);

        return response()->json(['publications' => $recent]);
    }
}
