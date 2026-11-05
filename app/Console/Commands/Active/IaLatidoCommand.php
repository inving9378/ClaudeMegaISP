<?php

namespace App\Console\Commands\Active;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Services\Core\SaludIA;
use Illuminate\Console\Command;
use Throwable;

/**
 * Latido de la IA del sistema (#1073): una llamada mínima con el modelo más barato, cada 15 min,
 * con la MISMA llave que usa el supervisor (Hub → env → marketing_settings). Así una llave
 * rechazada o una cuenta sin saldo se detectan aunque nadie esté llamando al modelo; el resultado
 * lo anota el propio ClaudeApiClient en SaludIA y el vigilante lo convierte en alarma.
 * Costo: menos de 1 MXN al mes con Haiku 5.5.
 */
class IaLatidoCommand extends Command
{
    protected $signature = 'ia:latido';

    protected $description = 'Latido de la IA (#1073): llamada mínima para saber si la llave, el saldo y la cuota siguen bien aunque no haya trabajo.';

    public function handle(): int
    {
        if (! config('circuito.ia_salud.latido.enabled', true)) {
            $this->line('Latido apagado (CIRCUITO_IA_LATIDO=false).');

            return self::SUCCESS;
        }

        $modelo = (string) config('circuito.ia_salud.latido.modelo', 'claude-haiku-5-5');
        try {
            (new ClaudeApiClient())->messages([
                'model'      => $modelo,
                'max_tokens' => 8,
                'messages'   => [['role' => 'user', 'content' => 'ok']],
            ]);
            SaludIA::marcarLatido('anthropic', true);
            $this->info("Latido OK ({$modelo}).");
        } catch (Throwable $e) {
            SaludIA::marcarLatido('anthropic', false, $e->getMessage());
            $this->warn('Latido con falla: ' . $e->getMessage());
        }

        return self::SUCCESS;
    }
}
