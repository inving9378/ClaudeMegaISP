<?php

namespace App\Modules\Addons\Flotas\Console;

use App\Modules\Addons\Flotas\Services\FleetSubscriptionService;
use Illuminate\Console\Command;

class CheckSubscriptionsCommand extends Command
{
    protected $signature = 'flotas:check-subscriptions';

    protected $description = 'Revisa suscripciones de Flotas: recordatorios de trial, expiración y cobros vencidos';

    public function handle(FleetSubscriptionService $service): int
    {
        $summary = $service->runDailyMaintenance();

        $this->info(sprintf(
            'Flotas suscripciones — recordatorios: %d · expiradas: %d · por facturar: %d',
            $summary['reminders'],
            $summary['expired'],
            $summary['due_for_billing'],
        ));

        return self::SUCCESS;
    }
}
