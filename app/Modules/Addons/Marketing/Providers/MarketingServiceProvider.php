<?php

namespace App\Modules\Addons\Marketing\Providers;

use App\Modules\Addons\Marketing\Repositories\CampaignRepository;
use App\Modules\Addons\Marketing\Services\AIContentService;
use App\Modules\Addons\Marketing\Services\ImageGeneratorService;
use App\Modules\Addons\Marketing\Services\LeadQualifierService;
use App\Modules\Addons\Marketing\Services\PublicationSchedulerService;
use App\Modules\Addons\Marketing\Services\Publishing\ChannelManager;
use App\Modules\Addons\Marketing\Services\Publishing\PostPublisherService;
use App\Modules\Addons\Marketing\Services\Publishing\SmartRouter;
use App\Modules\Addons\Marketing\Services\Publishing\TokenRefresher;
use App\Services\Core\ApiIntegrationService;
use Illuminate\Support\ServiceProvider;

class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AIContentService::class);
        $this->app->singleton(ImageGeneratorService::class);
        $this->app->singleton(PublicationSchedulerService::class);
        $this->app->singleton(LeadQualifierService::class);
        $this->app->singleton(CampaignRepository::class);

        // Fase 5 — Publicador Multicanal
        $this->app->singleton(ChannelManager::class);
        $this->app->singleton(SmartRouter::class, fn ($app) => new SmartRouter(
            $app->make(ChannelManager::class)
        ));
        $this->app->singleton(PostPublisherService::class, fn ($app) => new PostPublisherService(
            $app->make(ChannelManager::class),
            $app->make(SmartRouter::class),
        ));
        $this->app->singleton(TokenRefresher::class, fn ($app) => new TokenRefresher(
            $app->make(ApiIntegrationService::class),
        ));
    }
}
