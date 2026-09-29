<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Models\User;
use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Services\ReclamadorExtensionAutomatico;
use Illuminate\Console\Command;

/**
 * Backfill (28-sep-2026): crea la gemela WebRTC (`web{numero}`) de cada
 * extensión que todavía no la tiene.
 *
 * El alta automática (`ExtensionController::store()/update()`,
 * `ReclamadorExtensionAutomatico::reclamarParaColaborador()`) ya crea la
 * gemela para toda extensión nueva desde este mismo cambio — este comando es
 * solo para las que ya existían antes (ej. 1002/1003/1004/1011, asignadas
 * antes del fix del 24-sep que cubrió el alta a mano pero no de forma
 * retroactiva, y las sembradas por departamento sin `user_id`).
 *
 * Idempotente: se apoya en `crearGemelaWebrtc()`, que ya no hace nada si la
 * gemela existe y su dueño no cambió.
 */
class GenerarGemelasWebrtcCommand extends Command
{
    protected $signature = 'voip:generar-gemelas-webrtc
                            {--dry-run : Solo lista qué se crearía, sin escribir nada}';

    protected $description = 'Crea la gemela WebRTC (webNNNN) de cada extensión que aún no la tiene.';

    public function handle(ReclamadorExtensionAutomatico $reclamador): int
    {
        $pendientes = Extension::where('es_webrtc', false)
            ->whereRaw("numero NOT LIKE 'web%'")
            ->get()
            ->reject(fn (Extension $e) => Extension::where('numero', 'web' . $e->numero)->exists())
            ->values();

        if ($pendientes->isEmpty()) {
            $this->info('Todas las extensiones ya tienen su gemela WebRTC.');

            return self::SUCCESS;
        }

        $this->table(
            ['numero', 'nombre', 'user_id'],
            $pendientes->map(fn (Extension $e) => [$e->numero, $e->nombre, $e->user_id ?? '—'])->all()
        );

        if ($this->option('dry-run')) {
            $this->info($pendientes->count() . ' gemela(s) pendiente(s) — dry-run, no se escribió nada.');

            return self::SUCCESS;
        }

        $creadas = $fallidas = 0;

        foreach ($pendientes as $extension) {
            $user = $extension->user_id ? User::find($extension->user_id) : null;

            try {
                $reclamador->crearGemelaWebrtc($extension, $user);
                $this->line("  web{$extension->numero} creada.");
                $creadas++;
            } catch (\Throwable $e) {
                $this->error("  web{$extension->numero} falló: {$e->getMessage()}");
                $fallidas++;
            }
        }

        $this->info("Listo — {$creadas} gemela(s) creada(s), {$fallidas} fallida(s).");

        return $fallidas > 0 ? self::FAILURE : self::SUCCESS;
    }
}
