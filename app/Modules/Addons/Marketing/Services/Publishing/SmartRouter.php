<?php

namespace App\Modules\Addons\Marketing\Services\Publishing;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\PublicationChannel;

class SmartRouter
{
    public function __construct(private ChannelManager $manager) {}

    /**
     * Devuelve los canales óptimos para publicar el contenido dado.
     * Incluye canales sin credenciales (waiting_credentials) para informar al usuario.
     */
    public function routeContent(GeneratedContent $content, int $companyId = 1): array
    {
        $aspectRatio = $content->input_variables['_aspect_ratio']
            ?? $content->generation_metadata['aspect_ratio']
            ?? '9:16';

        $channels = PublicationChannel::forCompany($companyId)
            ->active()
            ->supportingRatio($aspectRatio)
            ->get();

        $routed = [];
        foreach ($channels as $channel) {
            try {
                $driver = $this->manager->getDriver($channel, $companyId);
                $check  = $driver->canPublish($content);

                $routed[] = [
                    'channel_id'         => $channel->id,
                    'channel_name'       => $channel->name,
                    'platform'           => $channel->platform,
                    'channel_type'       => $channel->channel_type,
                    'can_publish_now'    => $check['can'],
                    'reason'             => $check['reason'],
                    'credentials_ready'  => $channel->credentials_ready,
                    'aspect_ratio_match' => true,
                ];
            } catch (\Throwable $e) {
                $routed[] = [
                    'channel_id'        => $channel->id,
                    'channel_name'      => $channel->name,
                    'platform'          => $channel->platform,
                    'channel_type'      => $channel->channel_type,
                    'can_publish_now'   => false,
                    'reason'            => $e->getMessage(),
                    'credentials_ready' => false,
                    'aspect_ratio_match'=> true,
                ];
            }
        }

        // Add incompatible channels for display
        $incompatible = PublicationChannel::forCompany($companyId)
            ->active()
            ->where(function ($q) use ($aspectRatio) {
                $q->whereJsonDoesntContain('supported_aspect_ratios', $aspectRatio);
            })
            ->get();

        foreach ($incompatible as $channel) {
            $routed[] = [
                'channel_id'        => $channel->id,
                'channel_name'      => $channel->name,
                'platform'          => $channel->platform,
                'channel_type'      => $channel->channel_type,
                'can_publish_now'   => false,
                'reason'            => "Aspect ratio {$aspectRatio} no soportado",
                'credentials_ready' => $channel->credentials_ready,
                'aspect_ratio_match'=> false,
            ];
        }

        return $routed;
    }

    /**
     * Para una campaña multivariante completa: agrupa por nicho y muestra routing de cada video.
     */
    public function routeCampaign(array $contentIds, int $companyId = 1): array
    {
        $results = [];
        $contents = GeneratedContent::whereIn('id', $contentIds)->get();

        foreach ($contents as $content) {
            $niche = $content->input_variables['_creative_brief']['niche_slug']
                ?? $content->input_variables['niche_slug']
                ?? 'unknown';

            $results[$niche] = [
                'content_id'   => $content->id,
                'niche'        => $niche,
                'aspect_ratio' => $content->input_variables['_aspect_ratio'] ?? '9:16',
                'duration_sec' => $content->generation_metadata['duration_sec'] ?? 0,
                'channels'     => $this->routeContent($content, $companyId),
            ];
        }

        return array_values($results);
    }
}
