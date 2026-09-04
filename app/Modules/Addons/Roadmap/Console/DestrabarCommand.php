<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\JarvisService;
use Illuminate\Console\Command;

/**
 * #566 — DESTRABE: Jarvis resuelve lo que hoy se queda esperando a Irving.
 *
 * Ordena el trabajo por lo que cada item ESPERA DE VERDAD (`JarvisService::pendienteReal`), que es
 * la pieza que faltaba. El bucle de #117 (13 vueltas) y las 9 aprobaciones de #19 no eran falta de
 * permiso: era que **aprobar se trataba como responder**. Un item cuya única pendiente era el
 * merge volvía a `aprobado_irving` con cada clic, el pool lo re-despachaba, el worker veía que no
 * había nada que hacer y lo re-escalaba. Aprobar más fuerte nunca lo iba a mover.
 *
 * Por eso aquí cada item se enruta a lo que le falta:
 *   merge       → auto-merge si es reversible y no toca prod (E1)
 *   ejecucion   → auto-decisión si hay default/patrón/reversibilidad (E2)
 *   respuesta   → al consolidado, con la recomendación de Jarvis (E4)
 *   dependencia → se deja, con su motivo
 *
 * Dry-run por default. Cap y kill-switch en `config/circuito.php → jarvis.automerge` y
 * `jarvis.mecanico`; el kill-switch global sigue siendo `circuito_pausado`.
 */
class DestrabarCommand extends Command
{
    protected $signature = 'circuito:destrabar-bandeja
        {--apply : escribe los cambios (sin esto solo muestra el plan)}
        {--limit=120 : tope de items a revisar}
        {--cap= : sobreescribe el cap de auto-merges de ESTA corrida (el de config rige el régimen normal)}';

    protected $description = 'Jarvis destraba: auto-mergea lo verificado, decide lo reversible y consolida lo estratégico (#566).';

    /**
     * #864 — columnas MÍNIMAS que este comando (y `JarvisService::pendienteReal/elegibleAutoMerge/
     * autoMergear/evaluarYaDecidido/aprobarYaDecidido/clasificarMecanico/aprobarMecanico/consolidar`,
     * más los hooks `saving` del modelo que corren en cada `save()`) realmente leen o escriben sobre
     * los items que salen de esta consulta. `roadmap_items` tiene 96 columnas, varias TEXT/JSON
     * grandes (log, comentarios_claude, opciones, preguntas, reporte_tecnico, validacion_brief…) que
     * un `select *` con este WHERE + `ORDER BY id LIMIT 120` arrastraba al filesort — con
     * sort_buffer_size=256KB eso tronaba "Out of sort memory" cada minuto desde el 2026-08-11
     * (item #864). Si Jarvis empieza a leer/escribir otra columna sobre un item salido de ESTA
     * consulta, hay que sumarla aquí.
     *
     * ⚠️ UNA COLUMNA QUE NO EXISTE AQUÍ NO FALLA SILENCIOSA: revienta con `1054 Unknown column` y
     * tumba la corrida entera, y `SchedulerCommand::tickDestrabe()` se traga la excepción para no
     * frenar el reparto. Es exactamente cómo `branch_has_content` / `branch_ahead_count` dejaron
     * este motor muerto: eran columnas de una migración FANTASMA (aplicada a la BD de dev desde un
     * worktree, sin archivo en `main` — ver `docs/auditoria-migraciones-fantasma-item-533.md`), así
     * que la restauración por PITR del 2026-08-25 se las llevó y no había migración que las
     * repusiera. Se retiraron en vez de recrearlas porque **nadie en el repo las escribía**: el
     * "chequeo periódico" que las sellaba ya no existe, y `pendienteReal()` tiene a git como fuente
     * autoritativa (`archivosDeRama`). Reponerlas habría sido revivir un fantasma para leer siempre
     * `false`.
     */
    private const COLUMNAS_NECESARIAS = [
        'id', 'title', 'description', 'prompt', 'comentarios_claude', 'modulo',
        'nivel_riesgo', 'estado_aprobacion', 'status', 'archivado_at',
        'branch', 'merge_commit', 'esperando_merge_irving', 'origen_bloqueo',
        'preguntas', 'opciones', 'opcion_elegida', 'log',
        'aprobado_por', 'revisado_at', 'excluir_pool_automatico', 'bloqueado_por_bucle',
        // #710 — `evaluarYaDecidido()` ahora compara el fingerprint sellado (guardado aquí cuando
        // `contarEscalacion()` marcó `bloqueado_por_bucle=true`) contra el fingerprint ACTUAL del
        // item, para no re-aprobar un bloqueo anti-bucle cuya causa sigue igual. Sin esta columna
        // en el select, `$item->escalaciones_fingerprint` llega `null` en SILENCIO (no revienta,
        // sólo queda desatendida como con `requiere_sesion_supervisada` en #893 más abajo) y el
        // guard nunca frenaría nada por esta vía.
        'escalaciones_fingerprint',
        // #893 — `evaluarYaDecidido()` frena si `requiere_sesion_supervisada` está en true, pero
        // esa columna NO estaba aquí: sobre un item salido de ESTA consulta llega desatendida
        // (Eloquent la devuelve `null` sin error, a diferencia del 1054 que describe el aviso de
        // arriba), así que el guard leía `null` y nunca frenaba. Item #36 lo evidenció: quedaba
        // marcado `requiere_sesion_supervisada=true` a mano y este comando lo re-aprobaba solo
        // (carril `aprobarYaDecidido`) unos minutos después, una y otra vez.
        'requiere_sesion_supervisada',
    ];

    public function handle(JarvisService $jarvis): int
    {
        $aplicar = (bool) $this->option('apply');
        // El cap de config rige el régimen normal (un ciclo cada minuto). El override existe para
        // el destrabe INICIAL, donde hay una pila histórica que drenar de una: son merges que ya
        // debieron pasar hace semanas, no ritmo nuevo.
        $capMerge = $this->option('cap') !== null
            ? max(1, (int) $this->option('cap'))
            : (int) config('circuito.jarvis.automerge.cap_por_ciclo', 5);

        // Todo lo que está esperando algo de Irving, incluidos los parqueados y los del anti-bucle:
        // el punto del destrabe es justamente mirar esas bolsas.
        // #864 — EL ORDER BY VA SOBRE `id` A SECAS, y las columnas se traen DESPUÉS.
        //
        // Acotar el `select` (primer intento) fue la dirección correcta pero no bastó:
        // `COLUMNAS_NECESARIAS` conserva las cinco gordas —`prompt`, `comentarios_claude`,
        // `preguntas`, `opciones`, `log` (TEXT/JSON)— y son justo las que MySQL tiene que meter en
        // el sort buffer (262 KB aquí) para resolver `ORDER BY id LIMIT N`. Seguía reventando con
        //     SQLSTATE[HY001] 1038 Out of sort memory
        //
        // Ordenando sólo ids, cada fila del sort son 8 bytes y el problema desaparece POR
        // CONSTRUCCIÓN, no por caber. Eso importa porque el fallo era INTERMITENTE: acertaba cuando
        // el conjunto encogía y volvía a caer al crecer, así que un arreglo que sólo lo hiciera
        // "caber hoy" se rompería otra vez sin avisar. Ocho días y ~11,600 fallos fue lo que costó
        // que nadie lo viera.
        $ids = RoadmapItem::whereNull('archivado_at')
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])
            ->where(fn ($q) => $q
                ->whereIn('estado_aprobacion', ['requiere_irving', 'pendiente_revision', 'aprobado_irving'])
                ->orWhere('esperando_merge_irving', true)
                ->orWhere('bloqueado_por_bucle', true))
            ->where('status', '!=', 'done')
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->pluck('id')
            ->all();

        // Ya sin ORDER BY sobre filas anchas: `whereIn` por PK y el orden lo da el propio array.
        $items = $ids
            ? RoadmapItem::select(self::COLUMNAS_NECESARIAS)->whereIn('id', $ids)->get()->sortBy('id')->values()
            : collect();

        $mergeados = [];
        $decididos = [];
        $consolida = [];
        $retenidos = [];   // motivo => [ids]
        $capAlcanzado = false;

        foreach ($items as $item) {
            $p = $jarvis->pendienteReal($item);

            switch ($p['pendiente']) {
                case 'merge':
                    if (count($mergeados) >= $capMerge) {
                        $capAlcanzado = true;
                        $retenidos['Cap de auto-merges por ciclo alcanzado (vuelve el próximo ciclo)'][] = $item->id;
                        break;
                    }
                    $e = $aplicar ? $jarvis->autoMergear($item) : ['ok' => $jarvis->elegibleAutoMerge($item)['elegible'],
                        'motivo' => $jarvis->elegibleAutoMerge($item)['motivo']];
                    if ($e['ok']) {
                        $mergeados[] = $item->id;
                        $this->line(($aplicar ? '' : 'DRY ') . "→ MERGE     #{$item->id}  " . $this->corto($item));
                    } else {
                        $retenidos[$this->agrupar($e['motivo'])][] = $item->id;
                    }
                    break;

                case 'ejecucion':
                    // Dos carriles, del más barato al más amplio:
                    //  (a) YA DECIDIDO — el brief está contestado y el item seguía retenido sin
                    //      que faltara nadie. Es la bolsa más grande y la menos discutible.
                    //  (b) MECÁNICO — no había nada que decidir desde el enunciado.
                    $r = $aplicar ? $jarvis->aprobarYaDecidido($item) : $jarvis->evaluarYaDecidido($item);

                    if (! $r['aprobado']) {
                        $r = $aplicar
                            ? $jarvis->aprobarMecanico($item)
                            : ['aprobado' => $jarvis->clasificarMecanico($item)['mecanico'],
                                'motivo'  => $jarvis->clasificarMecanico($item)['motivo']];
                    }

                    if ($r['aprobado']) {
                        $decididos[] = $item->id;
                        $this->line(($aplicar ? '' : 'DRY ') . "→ DECIDIDO  #{$item->id}  " . $this->corto($item));
                    } else {
                        $retenidos[$this->agrupar($r['motivo'])][] = $item->id;
                    }
                    break;

                case 'respuesta':
                    $consolida[] = $item;
                    $this->line(($aplicar ? '' : 'DRY ') . "→ CONSOLIDA #{$item->id}  " . $this->corto($item));
                    break;

                default:
                    $retenidos[$this->agrupar($p['detalle'])][] = $item->id;
            }
        }

        // ── Reporte ──────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('════ DESTRABE ════');
        $this->line('Revisados: ' . $items->count());
        $this->line('Auto-mergeados: ' . count($mergeados) . ($capAlcanzado ? " (cap {$capMerge} alcanzado)" : ''));
        $this->line('Auto-decididos: ' . count($decididos));
        $this->line('Al consolidado de Irving: ' . count($consolida));
        $this->line('Retenidos: ' . array_sum(array_map('count', $retenidos)));

        if ($retenidos) {
            $this->newLine();
            $this->comment('Retenidos, agrupados por motivo:');
            foreach ($retenidos as $motivo => $ids) {
                sort($ids);
                $this->line("  · {$motivo}");
                $this->line('      ' . count($ids) . ': #' . implode(' #', array_slice($ids, 0, 25)));
            }
        }

        // E4 — lo estratégico se junta en UNA pregunta, no en N items bloqueados por separado.
        if ($consolida && config('circuito.jarvis.consolidado.enabled', true)) {
            $puntos = $jarvis->consolidar($consolida);
            if ($aplicar) {
                $path = $jarvis->escribirConsolidado($puntos);
                $this->newLine();
                $this->info('Consolidado escrito en: ' . $path);
            }
            $this->newLine();
            $this->comment('Para definir de una pasada (' . count($puntos) . '):');
            foreach ($puntos as $p) {
                $this->line("  #{$p['id']} · {$p['pregunta']}");
                $this->line('      Jarvis recomienda: ' . ($p['recomendacion'] ?? '—')
                    . ($p['reversible'] ? '  ♻️ reversible' : ''));
            }
        }

        if (! $aplicar) {
            $this->newLine();
            $this->comment('DRY — nada escrito. Corre con --apply.');
        }

        return self::SUCCESS;
    }

    private function corto(RoadmapItem $i): string
    {
        return mb_strimwidth(preg_replace('/\s+/', ' ', (string) $i->title), 0, 56, '…');
    }

    private function agrupar(string $motivo): string
    {
        return match (true) {
            str_contains($motivo, 'frontera dura')        => 'Frontera dura (prod / borrar datos / dinero / credenciales)',
            str_contains($motivo, 'ruta sensible')        => 'La rama toca rutas sensibles (prod/deploy)',
            str_contains($motivo, 'git revert')           => 'Migración destructiva: no se deshace con git revert',
            str_contains($motivo, 'negocio/producto')     => 'Decisión de negocio o producto',
            str_contains($motivo, 'decisión de diseño')   => 'Nivel C — decisión de diseño de Irving',
            str_contains($motivo, 'Sin nivel')            => 'Sin triar (falta nivel de riesgo)',
            str_contains($motivo, 'señal mecánica')       => 'Sin señal mecánica reconocible (ante la duda, tuyo)',
            str_contains($motivo, 'Depende de')           => 'Espera a otro item',
            str_contains($motivo, 'no trae cambios')      => 'Rama vacía: no hay nada que integrar',
            str_contains($motivo, 'Tope diario')          => 'Tope diario del carril mecánico alcanzado',
            str_contains($motivo, 'Cap de auto-merges')   => 'Cap de auto-merges por ciclo alcanzado',
            default                                       => $motivo,
        };
    }
}
