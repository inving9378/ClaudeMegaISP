<?php

namespace App\Providers;

use App\Models\ClientInternetService;
use App\Models\ClientMainInformation;
use App\Models\ClientUser;
use App\Models\CrmMainInformation;
use App\Models\File;
use App\Models\Mikrotik;
use App\Models\Payment;
use App\Models\Network;
use App\Observers\Client\Payment\PaymentObserver;
use App\Observers\ClientInternetServiceObserver;
use App\Observers\ClientMainInformationObserver;
use App\Observers\ClientUserObserver;
use App\Observers\CrmMainInformationObserver;
use App\Observers\FileObserver;
use App\Observers\MikrotikObserver;
use App\Observers\MikrotikConfigObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Observers\NetworkDeleteInMikrotikObserver;
use App\Observers\ClientBundleServiceObserver;
use App\Models\ClientBundleService;
use App\Models\ClientCustomService;
use App\Models\ClientVozService;
use App\Models\FieldModule;
use App\Models\MikrotikConfig;
use App\Observers\ClientCustomServiceObserver;
use App\Observers\ClientVozServiceObserver;
use App\Observers\FieldModuleObserver;
use App\Observers\SettingAdditionalFieldObserver;
use App\Events\ProspectRegistered;
use App\Events\ClientRegistered;
use App\Listeners\CalculateProspectCommission;
use App\Listeners\CalculateClientCommission;
use App\Models\Balance;
use App\Models\BillingConfiguration;
use App\Observers\BillingConfigurationObserver;
use App\Observers\ClientBalanceObserver;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        ProspectRegistered::class => [
            CalculateProspectCommission::class,
        ],
        ClientRegistered::class => [
            CalculateClientCommission::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        File::observe(FileObserver::class);
        CrmMainInformation::observe(CrmMainInformationObserver::class);
        ClientMainInformation::observe(ClientMainInformationObserver::class);
        Payment::observe(PaymentObserver::class);
        Mikrotik::observe(MikrotikObserver::class);
        Network::observe(NetworkDeleteInMikrotikObserver::class);
        ClientInternetService::observe(ClientInternetServiceObserver::class);
        ClientBundleService::observe(ClientBundleServiceObserver::class);
        MikrotikConfig::observe(MikrotikConfigObserver::class);
        ClientUser::observe(ClientUserObserver::class);
        ClientCustomService::observe(ClientCustomServiceObserver::class);
        ClientVozService::observe(ClientVozServiceObserver::class);
        FieldModule::observe(FieldModuleObserver::class);
        BillingConfiguration::observe(BillingConfigurationObserver::class);
        Balance::observe(ClientBalanceObserver::class);
    }
}
