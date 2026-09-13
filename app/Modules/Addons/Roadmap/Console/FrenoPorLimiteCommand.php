<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Support\FrenoCircuito;
use Illuminate\Console\Command;

/**
 * FASE 4b (#9990418): pone el freno-con-expiración (FASE 4a, #9990417) cuando `vuelta.sh` detecta
 * que la CUENTA de Claude (no el item) se quedó sin límite de sesión (causa=limite_cuenta,
 * #9990411/#9990416). Sin esto, `circuito:parquear-timeout` devolvía el item a la cola sin
 * penalización pero NINGÚN freno detenía al resto de las terminales — todas seguían chocando
 * contra el mismo límite de cuenta hasta que cada una lo detectara por su cuenta.
 *
 * Por qué un comando aparte y no `circuito:pausar`: ambos llegan a `FrenoCircuito::poner()`, pero
 * `circuito:pausar` (#170) es el freno de mano MANUAL — nunca pasa `$expiraEn`, así que nunca se
 * autolimpia (correcto para el kill switch de la Torre, #342). Éste es el único llamador que pasa
 * `$expiraEn`, y por diseño NO pasa por `RoadmapCircuitoService::setPaused()` (exige
 * `auth()->user()->can('circuito.pause')`, permiso que el ejecutor CLI on-box no tiene — es
 * justo el candado #342 que lo bloquea a propósito).
 *
 * TTL fijo (decisión de Irving, #9990418 q1+q2): 30 minutos por default
 * (`config('circuito.freno.limite_cuenta_ttl_seg')`). La hora de reset que `vuelta.sh` extrae del
 * log (`--hora-reset`) es SOLO informativa — queda en el motivo del freno para quien lo lea, pero
 * NO se usa para calcular `expira_en` (ver el comentario en `config/circuito.php`).
 *
 * Si el freno YA está puesto (manual, o de otra terminal que detectó el mismo límite segundos
 * antes) no se pisa — mismo criterio que `circuito:pausar`: apretar sobre un freno ya puesto no
 * aporta nada y podría acortar sin querer una pausa manual pensada para durar más.
 */
class FrenoPorLimiteCommand extends Command
{
    protected $signature = 'circuito:freno-por-limite
        {--ttl= : segundos hasta que el freno se autolimpia (default config circuito.freno.limite_cuenta_ttl_seg, 1800 = 30min)}
        {--hora-reset= : hora de reset de la cuenta si vuelta.sh la pudo leer (solo informativa, no se usa para calcular la expiración)}';

    protected $description = 'Pone el freno-con-expiración del circuito cuando la cuenta de Claude se quedó sin límite de sesión (FASE 4b, #9990418).';

    public function handle(): int
    {
        if (FrenoCircuito::activo()) {
            $d = FrenoCircuito::detalle();
            $this->warn('El freno YA estaba puesto — no se pisa (podría ser manual o de otra terminal).');
            $this->line('  motivo: ' . ($d['motivo'] ?? '?'));
            $this->line('  quién:  ' . ($d['quien'] ?? '?'));

            return self::SUCCESS;
        }

        $ttl       = (int) ($this->option('ttl') ?: config('circuito.freno.limite_cuenta_ttl_seg', 1800));
        $ttl       = max(60, $ttl);
        $horaReset = trim((string) $this->option('hora-reset'));
        $expiraEn  = now()->addSeconds($ttl)->toIso8601String();

        $motivo = 'Límite de sesión de la cuenta (Claude) alcanzado — freno automático de '
            . (int) round($ttl / 60) . ' min, se autolimpia solo.'
            . ($horaReset !== '' ? " Reset estimado (informativo, sin verificar fecha/zona): {$horaReset}." : '');

        FrenoCircuito::poner($motivo, 'circuito:limite_cuenta', $expiraEn);

        $this->info("FRENO PUESTO por límite de cuenta. Expira: {$expiraEn} (TTL {$ttl}s).");
        $this->line('  archivo: ' . FrenoCircuito::ruta());
        $this->line('  motivo:  ' . $motivo);

        return self::SUCCESS;
    }
}
