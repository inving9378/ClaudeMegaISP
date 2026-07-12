<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\WatchdogService;
use Illuminate\Console\Command;

/**
 * WATCHDOG del equipo de workers (#334). Cron on-box como meganet (cada 1-2 min). Detecta cuando el
 * scheduler o un worker no trabaja debiendo, intenta SANAR solo (acotado), audita y ESCALA a Irving
 * solo si no puede. Respeta el kill switch (en pausa no relanza). Un solo watchdog a la vez (flock).
 */
class WatchdogCommand extends Command
{
    protected $signature = 'circuito:watchdog {--dry : solo reporta la salud, no recupera}';

    protected $description = 'Vigila los N workers + scheduler y auto-recupera anomalías (#334).';

    private const LOCK = '/home/meganet/circuito/watchdog.lock';

    public function handle(WatchdogService $wd): int
    {
        // Un solo watchdog a la vez.
        @mkdir(dirname(self::LOCK), 0775, true);
        $lock = @fopen(self::LOCK, 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return self::SUCCESS;
        }

        try {
            if ($this->option('dry')) {
                foreach ($wd->saludSlots() as $s) {
                    $ico = $s['estado'] === 'trabajando' ? '🟢' : ($s['estado'] === 'caido' ? '🔴' : '⚪');
                    $this->line("{$ico} {$s['sid']} · {$s['estado']} — {$s['detalle']}");
                }

                return self::SUCCESS;
            }

            $r = $wd->revisar();

            if ($r['pausado'] ?? false) {
                $this->info('Watchdog: circuito pausado — no se recupera nada (kill switch).');

                return self::SUCCESS;
            }

            foreach ($r['acciones'] as $a) {
                $this->line('↻ ' . $a);
            }
            foreach ($r['alertas'] as $al) {
                $this->warn('⚠ ESCALA a Irving [' . $al['tipo'] . ($al['sid'] ? " · {$al['sid']}" : '') . ']: ' . $al['detalle']);
            }
            $this->info('Watchdog: ' . count($r['acciones']) . ' recuperación(es), ' . count($r['alertas']) . ' escalada(s).');

            return self::SUCCESS;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
