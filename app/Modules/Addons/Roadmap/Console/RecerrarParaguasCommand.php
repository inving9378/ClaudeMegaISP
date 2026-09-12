<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * #9991041 (sub-item de seguimiento de #9991034) — RE-DISPARA el hook de cierre en cascada
 * (`RoadmapItem::saved`, bloque PARAGUAS, evento `paraguas_cerrado`) sobre los paraguas que YA
 * cumplen la condición de cierre (aprobado_irving + excluir_pool_automatico=true + descompuesto +
 * sin hijos abiertos) pero se quedaron atascados porque nadie volvió a tocar a NINGUNO de sus
 * hijos después de que el último cerrara — el hook solo corre en el `saved` del HIJO, nunca por
 * sí solo sobre el padre (#9991034 corrigió que el hook SÍ cierre cuando corre; este comando es
 * el "nadie lo vuelve a disparar" para los que ya estaban atascados de antes del fix).
 *
 * MECANISMO (verificado en tinker con transacción+rollback antes de escribir este comando, ver
 * `comentarios_claude` de #9991041): re-`save()` — SIN cambiar ningún campo — sobre un hijo YA
 * CERRADO de cada paraguas candidato. `Model::save()` dispara el evento `saved` aunque no haya
 * atributos dirty (`finishSave()` lo hace incondicional), así que ningún dato se modifica: solo
 * se re-ejecuta el guard que ya vive en el modelo.
 *
 * Dry-run por default (q2 de #9991041, aprobado por Irving): sin `--ejecutar` solo LISTA los
 * candidatos y qué hijo tocaría cada uno, sin escribir nada. Fallos parciales no abortan el lote
 * (q3): cada paraguas se procesa en su propio try/catch y los que fallan quedan en un reporte
 * final aparte, sin bloquear a los que sí pudieron cerrar.
 */
class RecerrarParaguasCommand extends Command
{
    protected $signature = 'circuito:recerrar-paraguas
                            {--ejecutar : Ejecuta el re-disparo real (sin esta opción, solo hace dry-run)}';

    protected $description = '#9991041: re-dispara el hook de cierre en cascada sobre paraguas ya listos para cerrar pero atascados por falta de un save() posterior en sus hijos.';

    public function handle(RoadmapCircuitoService $svc): int
    {
        $candidatos = $this->candidatos();

        if ($candidatos->isEmpty()) {
            $this->info('No hay paraguas candidatos ahora mismo (aprobado_irving + excluir_pool_automatico + descompuesto + sin hijos abiertos).');

            return self::SUCCESS;
        }

        $this->line('Candidatos detectados: ' . $candidatos->count());

        $filas = [];
        $plan  = [];
        foreach ($candidatos as $padre) {
            $hijo    = $this->hijoCerradoDe($padre);
            $filas[] = [
                $padre->id,
                Str::limit((string) $padre->title, 60),
                $hijo ? "#{$hijo->id}" : '(sin hijo cerrado — no se puede re-disparar)',
            ];
            if ($hijo) {
                $plan[$padre->id] = $hijo;
            }
        }
        $this->table(['padre', 'título', 'hijo a tocar'], $filas);

        if (! $this->option('ejecutar')) {
            $this->warn('[DRY-RUN] Nada se escribió. Corre con --ejecutar para disparar la cascada real.');

            return self::SUCCESS;
        }

        $ok     = [];
        $fallos = [];

        foreach ($plan as $padreId => $hijo) {
            try {
                // Touch-save: sin cambiar ningún campo, solo para re-disparar el hook `saved` del
                // hijo (que a su vez revisa y, si corresponde, cierra al padre).
                $hijo->save();

                $padreFresco = RoadmapItem::find($padreId);
                if ($padreFresco && $padreFresco->estado_aprobacion === 'completado') {
                    $ok[] = $padreId;
                } elseif ($padreFresco && ! empty($padreFresco->branch) && empty($padreFresco->merge_commit)) {
                    // El hook de cascada SÍ marcó cierreParaguas y disparó estado_aprobacion=completado,
                    // pero el paraguas TIENE SU PROPIA rama (aparte de la de sus hijos) sin merge_commit.
                    // Un guard distinto lo reenrutó de vuelta en el mismo save(): el bloque (1) de
                    // RoadmapItem::saving (nivel_riesgo=C → esperando_merge_irving) o el bloqueante de
                    // "rama sin merge" de JarvisService::verificarCierre (cualquier nivel). No es un
                    // fallo de este comando: el paraguas tiene código propio sin mergear y
                    // legítimamente necesita que Irving lo revise/mergee antes de completarse.
                    $fallos[$padreId] = 'Tiene rama propia (' . $padreFresco->branch . ') sin merge_commit: '
                        . 'un guard de cierre lo reenrutó de vuelta a ' . $padreFresco->estado_aprobacion
                        . ' (esperando_merge_irving=' . var_export((bool) $padreFresco->esperando_merge_irving, true)
                        . '). No es un bug de la cascada — espera que Irving revise/mergee esa rama.';
                } else {
                    $fallos[$padreId] = 'El hijo se guardó pero el padre no quedó completado (estado actual: '
                        . ($padreFresco->estado_aprobacion ?? 'AUSENTE')
                        . '). Puede que otra terminal lo haya tocado entre medio, o que ya no cumpla '
                        . 'las condiciones del guard.';
                }
            } catch (\Throwable $e) {
                $fallos[$padreId] = $e->getMessage();
            }
        }

        foreach ($ok as $id) {
            $this->info("#{$id} cerrado por cascada.");
        }
        foreach ($fallos as $id => $motivo) {
            $this->error("#{$id} NO cerró: {$motivo}");
        }

        Log::channel('roadmap_externo')->info('recerrar-paraguas', [
            'cerrados' => $ok,
            'fallos'   => $fallos,
        ]);

        $this->line('');
        $this->info(sprintf(
            'Resumen: %d cerrado(s), %d fallo(s) de %d candidato(s).',
            count($ok),
            count($fallos),
            $candidatos->count()
        ));

        $ejecutables = $svc->ejecutablesParalelo([], 200);
        $this->line('ejecutablesParalelo([], 200) ahora devuelve ' . count($ejecutables) . ' item(s).');

        return $fallos ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Mismo criterio medido en #9991041 (vuelta wt-2, 2026-09-12): aprobado_irving +
     * excluir_pool_automatico=true + ya descompuesto (tiene al menos un hijo, cerrado o no) + sin
     * ningún hijo todavía abierto. Se re-consulta en vivo, nunca se cachea la lista del item.
     *
     * @return \Illuminate\Support\Collection<int,RoadmapItem>
     */
    private function candidatos()
    {
        return RoadmapItem::where('estado_aprobacion', 'aprobado_irving')
            ->where('excluir_pool_automatico', true)
            ->get()
            ->filter(fn (RoadmapItem $i) => $i->yaFueDescompuesto() && ! $i->tieneSubItemsAbiertos())
            ->values();
    }

    /**
     * Cualquier hijo directo ya cerrado (completado/cancelado/rechazado/done/cancelled) — el guard
     * del hook (`RoadmapItem::saved`, bloque PARAGUAS) solo mira `estado_aprobacion`/`status` del
     * hijo para decidir si re-evalúa al padre, NO `archivado_at`. Medido en dev (2026-09-12): los
     * hijos de estos 20 paraguas están TODOS archivados (se archivan al cerrar), así que filtrar
     * por `whereNull('archivado_at')` aquí los dejaría a todos sin candidato — justo el bug que
     * este comando existe para destrabar.
     */
    private function hijoCerradoDe(RoadmapItem $padre): ?RoadmapItem
    {
        return RoadmapItem::where('origen_item_id', $padre->id)
            ->where(function ($q) {
                $q->whereIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])
                  ->orWhereIn('status', ['done', 'cancelled']);
            })
            ->latest('updated_at')
            ->first();
    }
}
