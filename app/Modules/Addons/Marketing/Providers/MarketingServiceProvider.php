<?php

namespace App\Modules\Addons\Marketing\Providers;

use App\Modules\Addons\Marketing\Repositories\CampaignRepository;
use App\Modules\Addons\Marketing\Services\AIContentService;
use App\Modules\Addons\Marketing\Services\ImageGeneratorService;
use App\Modules\Addons\Marketing\Services\LeadQualifierService;
use App\Modules\Addons\Marketing\Services\PublicationSchedulerService;
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
    }
}
