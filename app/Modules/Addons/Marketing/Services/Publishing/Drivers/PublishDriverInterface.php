<?php

namespace App\Modules\Addons\Marketing\Services\Publishing\Drivers;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Publication;

interface PublishDriverInterface
{
    /** @return array{can: bool, reason: string|null} */
    public function canPublish(GeneratedContent $content): array;

    /** @return array{valid: bool, message: string} */
    public function validateCredentials(): array;

    /** @return array{success: bool, external_post_id?: string, external_post_url?: string, error?: string} */
    public function publish(Publication $pub): array;

    /** @return array{likes?: int, views?: int, comments?: int, shares?: int, reach?: int} */
    public function fetchMetrics(Publication $pub): array;

    /** @return string[] list of required config keys */
    public function getRequiredCredentials(): array;
}
