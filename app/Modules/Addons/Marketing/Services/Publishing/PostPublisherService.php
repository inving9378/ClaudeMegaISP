<?php

namespace App\Modules\Addons\Marketing\Services\Publishing;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\MultivariantCampaign;
use App\Models\Marketing\Publication;
use App\Models\Marketing\PublicationChannel;
use App\Modules\Addons\Marketing\Jobs\FetchMetricsJob;
use App\Modules\Addons\Marketing\Jobs\PublishPostJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PostPublisherService
{
    public function __construct(
        private ChannelManager $channelManager,
        private SmartRouter $smartRouter,
    ) {}

    /**
     * Crea publicaciones en cola para una campaña multivariante.
     * No publica inmediatamente — crea registros con status apropiado.
     */
    public function queueCampaign(
        MultivariantCampaign $campaign,
        array $channelIds,
        ?Carbon $scheduledFor = null,
        string $caption = '',
        array $hashtags = [],
    ): array {
        $contentIds = $campaign->variant_content_ids ?? [];
        if (empty($contentIds)) {
            return [];
        }

        $contents     = GeneratedContent::whereIn('id', $contentIds)->get();
        $publications = [];

        foreach ($contents as $content) {
            $routing = $this->smartRouter->routeContent($content, $campaign->company_id);

            foreach ($routing as $route) {
                if (!in_array($route['channel_id'], $channelIds)) {
                    continue;
                }
                if (!$route['aspect_ratio_match']) {
                    continue;
                }

                $channel    = PublicationChannel::find($route['channel_id']);
                $nicheSlug  = $content->input_variables['_creative_brief']['niche_slug']
                    ?? $content->input_variables['niche_slug']
                    ?? 'unknown';

                $pub = Publication::create([
                    'company_id'      => $campaign->company_id,
                    'content_id'      => $content->id,
                    'pub_channel_id'  => $route['channel_id'],
                    'campaign_id'     => $campaign->id,
                    'caption'         => $caption ?: $this->buildCaption($content, $campaign),
                    'hashtags'        => $hashtags ?: $this->buildHashtags($content),
                    'status'          => $route['credentials_ready'] ? 'queued' : 'waiting_credentials',
                    'scheduled_for'   => $scheduledFor,
                    'ab_variant_tag'  => "niche:{$nicheSlug}",
                    'retry_count'     => 0,
                ]);

                $pub->addLog('created', "Publicación creada para canal {$channel?->name} — status: {$pub->status}");
                $publications[] = $pub;
            }
        }

        // Dispatch inmediato si no hay scheduling o si el tiempo ya pasó
        $pubsToDispatch = collect($publications)
            ->filter(fn($p) => $p->status === 'queued' && (!$scheduledFor || $scheduledFor->isPast()))
            ->all();

        foreach ($pubsToDispatch as $pub) {
            PublishPostJob::dispatch($pub);
        }

        return $publications;
    }

    /**
     * Publica un solo item de forma inmediata (desde el queue worker).
     */
    public function publishNow(Publication $pub): array
    {
        if (!$pub->pub_channel_id) {
            return ['success' => false, 'error' => 'Sin canal de publicación asignado'];
        }

        $channel = PublicationChannel::find($pub->pub_channel_id);
        if (!$channel) {
            return ['success' => false, 'error' => 'Canal no encontrado'];
        }

        $driver = $this->channelManager->getDriver($channel, $pub->company_id);
        $result = $driver->publish($pub);

        if ($result['success']) {
            $pub->update([
                'status'            => 'published',
                'published_at'      => now(),
                'external_post_id'  => $result['external_post_id'] ?? null,
                'external_post_url' => $result['external_post_url'] ?? null,
            ]);
            $pub->addLog('published', 'Publicado exitosamente', $result);

            FetchMetricsJob::dispatch($pub)->delay(now()->addHour());
            FetchMetricsJob::dispatch($pub)->delay(now()->addDay());
        } else {
            $pub->update([
                'status'         => 'failed',
                'failure_reason' => $result['error'] ?? 'Error desconocido',
                'retry_count'    => $pub->retry_count + 1,
                'next_retry_at'  => now()->addMinutes(15),
            ]);
            $pub->addLog('failed', $result['error'] ?? 'Error desconocido', $result);
        }

        return $result;
    }

    public function retry(Publication $pub): array
    {
        $pub->update([
            'status'         => 'queued',
            'failure_reason' => null,
        ]);
        $pub->addLog('retry', 'Reintento manual iniciado');
        PublishPostJob::dispatch($pub);

        return ['queued' => true];
    }

    public function cancel(Publication $pub): array
    {
        if (in_array($pub->status, ['published', 'publishing'])) {
            return ['success' => false, 'error' => 'No se puede cancelar una publicación ya en proceso'];
        }

        $pub->update(['status' => 'cancelled']);
        $pub->addLog('cancelled', 'Cancelado por usuario');

        return ['success' => true];
    }

    public function fetchMetricsForPublication(Publication $pub): array
    {
        if (!$pub->pub_channel_id || !$pub->external_post_id) {
            return [];
        }

        $channel = PublicationChannel::find($pub->pub_channel_id);
        if (!$channel) {
            return [];
        }

        $driver  = $this->channelManager->getDriver($channel, $pub->company_id);
        $metrics = $driver->fetchMetrics($pub);

        if (!empty($metrics)) {
            $current = $pub->metrics ?? [];
            $merged  = array_merge($current, $metrics, ['updated_at' => now()->toIso8601String()]);

            $pub->update([
                'metrics'            => $merged,
                'metrics_updated_at' => now(),
            ]);
            $pub->addLog('metrics_updated', 'Métricas actualizadas', $metrics);
        }

        return $metrics;
    }

    private function buildCaption(GeneratedContent $content, MultivariantCampaign $campaign): string
    {
        $niche = $content->input_variables['_creative_brief']['niche_name']
            ?? $content->input_variables['niche_name']
            ?? '';

        $parts = [];
        if ($campaign->name) {
            $parts[] = $campaign->name;
        }
        if ($niche) {
            $parts[] = "Para: {$niche}";
        }

        return implode(' — ', $parts);
    }

    private function buildHashtags(GeneratedContent $content): array
    {
        $defaults = ['meganet', 'internet', 'conectividad'];

        $niche = $content->input_variables['_creative_brief']['niche_slug']
            ?? $content->input_variables['niche_slug']
            ?? '';

        if ($niche) {
            $defaults[] = $niche;
        }

        return $defaults;
    }
}
