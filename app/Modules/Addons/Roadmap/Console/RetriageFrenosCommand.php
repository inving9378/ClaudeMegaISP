<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;

/**
 * FASE 2A.4 — RE-TRIAGE DE FRENOS, con la regla ASIMÉTRICA de Irving (2026-08-18).
 *
 *   · Freno del CLASIFICADOR → caduca solo. Es un consejo automático; si nadie lo confirmó, vence.
 *   · Freno HUMANO          → **nunca caduca.** Es una decisión de Irving y el sistema no la revoca
 *                             por antigüedad. Se RESURFACEA: el digest la vuelve a poner enfrente.
 *
 * Esa distinción es todo el punto. Los 33 frenos vivos no son items bloqueados por error: son
 * decisiones que se olvidó haber tomado. Un caducado automático se las revocaría a la mala; un
 * recordatorio se las devuelve para que decida de nuevo con la cabeza fresca. Lo que hacía falta no
 * era que el sistema quitara el freno, era que alguien dijera «esto lleva 47 días frenado por ti».
 *
 * ESTE COMANDO NUNCA QUITA UN FRENO HUMANO. Es la única garantía que sostiene la regla, así que
 * está escrita tres veces: en la config (por ausencia de la clave), aquí (fail-closed explícito) y
 * en el test `RetriageNoRevocaFrenoHumanoTest`.
 *
 * Dry-run por defecto (convención del circuito): sin `--apply` sólo muestra el plan.
 */
class RetriageFrenosCommand extends Command
{
    protected $signature = 'circuito:re-triage
                            {--apply : escribe los cambios (sin esto sólo muestra el plan)}
                            {--dias= : sobreescribe circuito.retriage.clasificador_caduca_dias}';

    protected $description = 'Re-triage de frenos (2A.4): el del clasificador caduca solo; el HUMANO nunca caduca, se resurfacea.';

    public function handle(): int
    {
        // FAIL-CLOSED. Si alguien alguna vez agrega una caducidad para el freno humano, este
        // comando se niega a correr en vez de empezar a revocar decisiones de Irving en silencio.
        if (config()->has('circuito.retriage.humano_caduca_dias')
            || config('circuito.retriage.humano_caduca', false)) {
            $this->error('ABORTADO: alguien configuró una caducidad para el FRENO HUMANO.');
            $this->line('El freno humano NO caduca — es una decisión de Irving, no una opinión con');
            $this->line('fecha. Quita esa clave de config/circuito.php antes de volver a correr esto.');

            return self::FAILURE;
        }

        $aplicar = (bool) $this->option('apply');
        $dias    = (int) ($this->option('dias') ?: config('circuito.retriage.clasificador_caduca_dias', 14));

        $this->newLine();
        $this->info('RE-TRIAGE DE FRENOS (2A.4)' . ($aplicar ? '' : ' — DRY RUN, no escribe nada'));
        $this->newLine();

        $vencidos = $this->caducarClasificador($dias, $aplicar);
        $this->newLine();
        $this->resurfacearHumanos();

        $this->newLine();
        if (! $aplicar && $vencidos > 0) {
            $this->comment("Corre con --apply para vencer esos {$vencidos} consejo(s) del clasificador.");
        }

        return self::SUCCESS;
    }

    /**
     * El consejo del clasificador vence si nadie lo confirmó en `$dias`. Confirmarlo = convertirlo
     * en freno humano; por eso basta con mirar `origen_bloqueo`: si sigue diciendo 'clasificador',
     * nadie lo ratificó. Vencerlo NO desbloquea nada (el freno del clasificador ya no frenaba desde
     * 2A.3): sólo deja de opinar.
     */
    private function caducarClasificador(int $dias, bool $aplicar): int
    {
        $corte = now()->subDays($dias);

        $candidatos = RoadmapItem::query()
            ->whereNull('archivado_at')
            ->where('origen_bloqueo', 'clasificador')
            ->get(['id', 'title', 'origen_bloqueo', 'motivo_bloqueo', 'bloqueo_expira_en',
                   'bloqueo_renovaciones', 'clasificado_at', 'updated_at', 'log']);

        $vencen = $candidatos->filter(function (RoadmapItem $i) use ($corte) {
            $puesto = $i->bloqueo_expira_en ?: ($i->clasificado_at ?: $i->updated_at);

            return $i->bloqueo_expira_en
                ? $i->bloqueo_expira_en->isPast()
                : ($puesto && $puesto->lt($corte));
        });

        $this->line("<options=bold>1. Freno del CLASIFICADOR — caduca solo ({$dias} días sin confirmar)</>");
        $this->line("   Vivos: {$candidatos->count()}   ·   vencen ahora: {$vencen->count()}");

        if ($vencen->isEmpty()) {
            $this->line('   <fg=green>Nada que vencer.</>');

            return 0;
        }

        foreach ($vencen as $i) {
            $this->line("   #{$i->id}  " . mb_strimwidth((string) $i->title, 0, 70, '…'));
        }

        if (! $aplicar) {
            return $vencen->count();
        }

        foreach ($vencen as $i) {
            // El hook de traza (2A.4) escribe solo la entrada de `log` al ver el isDirty.
            $i->origen_bloqueo    = null;
            $i->bloqueo_expira_en = null;
            $i->motivo_bloqueo    = trim("Consejo del clasificador vencido por re-triage ({$dias} días sin que nadie lo confirmara). "
                . 'No desbloquea nada: el freno del clasificador no frenaba — sólo deja de opinar.');
            $i->save();
        }
        $this->line("   <fg=green>Vencidos {$vencen->count()}.</>");

        return $vencen->count();
    }

    /**
     * El freno HUMANO no se toca. Se ordena para el recordatorio: primero los que acumulan más
     * APROBACIONES MUDAS —la señal más limpia de "aquí hay un desacuerdo entre lo que decidiste y
     * lo que quieres"— y después por antigüedad.
     */
    private function resurfacearHumanos(): void
    {
        $frenos = $this->frenosHumanos();

        $this->line('<options=bold>2. Freno HUMANO — NUNCA caduca. Se resurfacea.</>');
        $this->line('   Vivos: ' . count($frenos) . '   ·   revocados por este comando: 0 (por diseño)');

        if (! $frenos) {
            return;
        }

        $this->table(
            ['item', 'días', 'mudas', 'rótulo', 'título'],
            array_map(fn ($f) => [
                '#' . $f['id'],
                $f['dias'] . ($f['exacto'] ? '' : '+'),
                $f['mudas'] ?: '—',
                $f['rotulo'],
                mb_strimwidth($f['title'], 0, 46, '…'),
            ], array_slice($frenos, 0, (int) config('circuito.retriage.resurface_top', 12)))
        );
        $this->comment('   «días» con + = cota inferior: el freno es anterior al rastro más viejo del item.');
        $this->comment('   El digest los repite cada ' . config('circuito.retriage.resurface_dias', 7) . ' días (circuito:digest --frenos para forzarlo).');
    }

    /**
     * Los frenos humanos vivos, ya ordenados para el recordatorio. Es el punto ÚNICO que consumen
     * este comando y `circuito:digest` — dos listados distintos del mismo hecho fue exactamente el
     * origen de la deriva que 2A.5 acaba de cerrar.
     *
     * @return array<int,array{id:int,title:string,dias:int,exacto:bool,mudas:int,rotulo:string,desde:?string}>
     */
    public static function frenosHumanos(): array
    {
        $out = RoadmapItem::query()
            ->whereNull('archivado_at')
            ->where(fn ($q) => RoadmapItem::sqlConFrenoHumano($q))
            ->get()
            ->map(function (RoadmapItem $i) {
                [$desde, $exacto] = $i->frenoDesde();

                return [
                    'id'     => (int) $i->id,
                    'title'  => (string) $i->title,
                    'dias'   => $desde ? (int) $desde->diffInDays(now()) : 0,
                    'exacto' => $exacto,
                    'mudas'  => $i->aprobacionesMudas(),
                    'rotulo' => $i->textoDelFreno(),
                    'desde'  => $desde?->toDateString(),
                ];
            })
            ->all();

        // Muchas aprobaciones mudas → al principio. Es la señal de que Irving quiere una cosa y
        // decidió otra. A igualdad de mudas, manda la antigüedad.
        usort($out, fn ($a, $b) => [$b['mudas'], $b['dias']] <=> [$a['mudas'], $a['dias']]);

        return $out;
    }
}
