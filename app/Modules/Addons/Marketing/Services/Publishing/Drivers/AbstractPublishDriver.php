<?php

namespace App\Modules\Addons\Marketing\Services\Publishing\Drivers;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Publication;
use App\Models\Marketing\PublicationChannel;
use App\Services\Core\UsesApiIntegration;

abstract class AbstractPublishDriver implements PublishDriverInterface
{
    use UsesApiIntegration;

    protected PublicationChannel $channel;
    protected int $companyId;

    public function __construct(PublicationChannel $channel, int $companyId = 1)
    {
        $this->channel   = $channel;
        $this->companyId = $companyId;
    }

    public function canPublish(GeneratedContent $content): array
    {
        $aspectRatio = $content->input_variables['_aspect_ratio']
            ?? $content->generation_metadata['aspect_ratio']
            ?? null;

        $supported = $this->channel->supported_aspect_ratios ?? [];
        if ($aspectRatio && !in_array($aspectRatio, $supported)) {
            return ['can' => false, 'reason' => "Aspect ratio {$aspectRatio} no soportado por {$this->channel->name}"];
        }

        $duration = $content->generation_metadata['duration_sec']
            ?? $content->generation_metadata['duration']
            ?? 0;
        if ($duration > $this->channel->max_duration_seconds) {
            return ['can' => false, 'reason' => "Duración {$duration}s excede máximo de {$this->channel->max_duration_seconds}s"];
        }

        $creds = $this->validateCredentials();
        if (!$creds['valid']) {
            return ['can' => false, 'reason' => 'Credenciales no listas: ' . $creds['message']];
        }

        return ['can' => true, 'reason' => null];
    }

    public function fetchMetrics(Publication $pub): array
    {
        return [];
    }

    protected function videoPath(GeneratedContent $content): string
    {
        return storage_path('app/' . ltrim($content->output_path, '/'));
    }
}
