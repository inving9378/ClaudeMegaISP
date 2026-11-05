<?php

namespace App\Modules\Addons\Flotas\Console;

use App\Modules\Addons\Flotas\Services\Notifications\ClientRuleEngine;
use Illuminate\Console\Command;

class CheckClientRulesCommand extends Command
{
    protected $signature = 'flotas:check-client-rules';

    protected $description = 'Evalúa las reglas de alerta del cliente que no dependen de un ping (sin conexión, documento, mantenimiento)';

    public function handle(ClientRuleEngine $engine): int
    {
        $this->info('Alertas de cliente generadas: ' . $engine->sweep());
        return self::SUCCESS;
    }
}
