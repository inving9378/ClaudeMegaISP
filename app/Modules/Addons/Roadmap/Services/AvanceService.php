<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Models\RoadmapItemReport;

/**
 * #625/#626 — JARVIS dueño del avance (fase 1: vueltas vacías y reclamos fantasma; fase 2: módulos
 * faltantes, paraguas con hijos cerrados, terminados sin cerrar y fuera de pool sin seguimiento).
 *
 * Seguimiento, NO despacho: el scheduler sigue siendo el único despachador (#432 B1). Aquí solo se
 * detecta lo que no avanza y se saca del camino con una marca reversible y auditada en el log.
 */
class AvanceService
{
    /** Vueltas vacías seguidas (misma causa) a partir de las cuales el item sale del pool. */
    public const VUELTAS_VACIAS_MAX = 3;

    /** Horas sin lease antes de considerar fantasma un `en_progreso` sin worker_sid. */
    public const HORAS_FANTASMA = 4;

    private const EVENTO_VUELTA_VACIA = 'claim_liberado_al_morir_la_vuelta';

    /** @return array<string, int[]> */
    public function barrer(bool $apply = true): array
    {
        $r = [
            'vueltas_vacias' => $this->frenarVueltasVacias($apply),
            'fantasmas'      => $this->marcarFantasmas($apply),
        ];
        // Fase 2 (#626): cada paso es independiente; uno que falle no tumba a los demás.
        foreach ([
            'modulos_completados'  => 'completarModulosFaltantes',
            'paraguas_cerrados'    => 'cerrarParaguasConHijosCerrados',
            'terminados_cerrados'  => 'cerrarTerminadosSinCerrar',
            'fuera_de_pool'        => 'seguimientoFueraDePool',
        ] as $clave => $metodo) {
            try {
                $r[$clave] = $this->{$metodo}($apply);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("JARVIS avance {$metodo} falló: " . $e->getMessage());
                $r[$clave] = [];
            }
        }

        return $r;
    }

    private function anotar(RoadmapItem $item, string $evento, string $motivo): void
    {
        $log = is_array($item->log) ? $item->log : [];
        $log[] = ['ts' => now()->toIso8601String(), 'por' => 'jarvis-avance', 'evento' => $evento, 'motivo' => $motivo];
        $item->log = $log;
    }

    /** Item abierto sin módulo → hereda el del item de origen; si no hay, el clasificador por footprint. */
    public function completarModulosFaltantes(bool $apply = true): array
    {
        $ids = [];
        $q = RoadmapItem::query()
            ->where(fn ($w) => $w->whereNull('modulo')->orWhere('modulo', ''))
            ->whereNull('archivado_at')
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado']);

        foreach ($q->limit(50)->get() as $item) {
            $modulo = null;
            $origen = 'footprint';
            if ($item->origen_item_id) {
                $modulo = trim((string) RoadmapItem::whereKey($item->origen_item_id)->value('modulo')) ?: null;
                $origen = 'item de origen #' . $item->origen_item_id;
            }
            $modulo ??= app(JarvisService::class)->clasificarModulo($item->title . ' ' . $item->description);
            if (! $modulo) {
                continue;
            }
            $ids[] = (int) $item->id;
            if ($apply) {
                $item->modulo = $modulo;
                $this->anotar($item, 'modulo_completado', "Sin módulo → «{$modulo}» (por {$origen}). Reversible: editar el módulo.");
                $item->save();
            }
        }

        return $ids;
    }

    /** Paraguas retenido (`aprobado_irving`) con hijos y ninguno abierto: el hook de cascada se lo perdió → cierra. */
    public function cerrarParaguasConHijosCerrados(bool $apply = true): array
    {
        $ids = [];
        $q = RoadmapItem::query()
            ->where('estado_aprobacion', 'aprobado_irving')
            ->whereNull('worker_sid')
            ->whereNull('archivado_at')
            ->whereExists(fn ($w) => $w->selectRaw('1')->from('roadmap_items as h')->whereColumn('h.origen_item_id', 'roadmap_items.id'));

        foreach ($q->limit(50)->get() as $item) {
            if ($item->tieneSubItemsAbiertos()) {
                continue;
            }
            $ids[] = (int) $item->id;
            if ($apply) {
                $this->anotar($item, 'paraguas_cerrado', 'Todos sus sub-items están cerrados: el paraguas se completa (evidencia en los hijos).');
                $item->cierreParaguas = true;
                $item->estado_aprobacion = 'completado';
                $item->save();
            }
        }

        return $ids;
    }

    /** Con merge_commit pero sin cerrar y sin worker: el trabajo ya está en main → completado (el gate de cierre sigue vigente). */
    public function cerrarTerminadosSinCerrar(bool $apply = true): array
    {
        $ids = [];
        $q = RoadmapItem::query()
            ->whereIn('estado_aprobacion', ['en_progreso', 'aprobado_claude', 'aprobado_revisor', 'aprobado_irving'])
            ->whereNull('worker_sid')
            ->whereNull('archivado_at')
            ->whereNotNull('merge_commit')->where('merge_commit', '!=', '')
            ->where('updated_at', '<', now()->subHours(self::HORAS_FANTASMA));

        foreach ($q->limit(50)->get() as $item) {
            if ($item->tieneSubItemsAbiertos()) {
                continue;
            }
            $ids[] = (int) $item->id;
            if ($apply) {
                $this->anotar($item, 'terminado_cerrado', 'Tiene merge_commit ' . substr((string) $item->merge_commit, 0, 9) . ' en main pero seguía abierto: se completa.');
                $item->estado_aprobacion = 'completado';
                $item->save(); // verificarCierre puede parquearlo si faltan reporte/enlace: queda auditado.
            }
        }

        return $ids;
    }

    /**
     * Fuera del pool tras un cierre rechazado (`cierre_incompleto`) sin motivo de cierre-sin-código y sin rama:
     * si la terminal ya dejó por escrito su reporte de cierre, ese texto es el motivo → se llena y se completa.
     * Si no, se deja UNA marca de seguimiento (idempotente) para que alguien lo mire.
     */
    public function seguimientoFueraDePool(bool $apply = true): array
    {
        $ids = [];
        $q = RoadmapItem::query()
            ->where('excluir_pool_automatico', true)
            ->whereNull('worker_sid')
            ->whereNull('archivado_at')
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado']);

        foreach ($q->limit(100)->get() as $item) {
            $log = is_array($item->log) ? $item->log : [];
            $ultimo = null;
            foreach (array_reverse($log) as $e) {
                if (in_array($e['evento'] ?? null, ['cierre_incompleto', 'cierre_rescatado_por_nota', 'fuera_de_pool_sin_seguimiento'], true)) {
                    $ultimo = $e;
                    break;
                }
            }
            if (($ultimo['evento'] ?? null) === 'cierre_rescatado_por_nota' || ($ultimo['evento'] ?? null) === 'fuera_de_pool_sin_seguimiento') {
                continue;
            }

            $sinCodigo = $ultimo && empty($item->merge_commit) && empty($item->branch)
                && trim((string) $item->cierre_sin_codigo_motivo) === '';
            $nota = $sinCodigo
                ? RoadmapItemReport::where('roadmap_item_id', $item->id)->where('tipo', 'cierre')->latest('id')->value('resumen')
                : null;

            if ($ultimo && $nota && trim($nota) !== '') {
                $ids[] = (int) $item->id;
                if ($apply) {
                    $item->cierre_sin_codigo_motivo = trim($nota);
                    $item->excluir_pool_automatico = false;
                    $this->anotar($item, 'cierre_rescatado_por_nota', 'La terminal ya explicó en su reporte de cierre por qué no hubo código; se usa como cierre_sin_codigo_motivo y se completa.');
                    $item->estado_aprobacion = 'completado';
                    $item->save();
                }
            } elseif ($ultimo === null && $item->excluir_pool_automatico && $item->updated_at < now()->subDays(1)
                && empty($item->motivo_bloqueo) && ! $item->esperando_merge_irving) {
                $ids[] = (int) $item->id;
                if ($apply) {
                    $this->anotar($item, 'fuera_de_pool_sin_seguimiento', 'Fuera del pool más de 24 h sin motivo registrado: necesita que alguien decida (reactivar o cerrar).');
                    $item->save();
                }
            }
        }

        return $ids;
    }

    /** Items cuyas últimas N entradas del log son vueltas vacías consecutivas → fuera del pool. */
    public function frenarVueltasVacias(bool $apply = true): array
    {
        $ids = [];
        $q = RoadmapItem::query()
            ->whereIn('estado_aprobacion', ['aprobado_claude', 'aprobado_revisor', 'aprobado_irving'])
            ->where('excluir_pool_automatico', false)
            ->whereNull('worker_sid');

        foreach ($q->get() as $item) {
            $log = is_array($item->log) ? $item->log : [];
            $ultimas = array_slice($log, -self::VUELTAS_VACIAS_MAX);
            if (count($ultimas) < self::VUELTAS_VACIAS_MAX) {
                continue;
            }
            $todasVacias = collect($ultimas)->every(fn ($e) => ($e['evento'] ?? null) === self::EVENTO_VUELTA_VACIA);
            if (! $todasVacias) {
                continue;
            }
            $ids[] = (int) $item->id;
            if (! $apply) {
                continue;
            }
            $motivo = 'JARVIS ' . now()->format('Y-m-d H:i') . ': ' . self::VUELTAS_VACIAS_MAX
                . ' vueltas seguidas terminaron sin cerrar el item (vueltas vacías que gastan cuota). Se saca del pool. '
                . 'Falta una acción de Irving: elegir opción en la consulta/brief pendiente o cerrarlo. '
                . 'Reversible: quitar excluir_pool_automatico.';
            $item->excluir_pool_automatico = true;
            $item->motivo_bloqueo = $item->motivo_bloqueo ? $item->motivo_bloqueo . "\n" . $motivo : $motivo;
            $log[] = ['ts' => now()->toIso8601String(), 'por' => 'jarvis-avance', 'evento' => 'vueltas_vacias_frenado', 'motivo' => $motivo];
            $item->log = $log;
            $item->save();
        }

        return $ids;
    }

    /** `en_progreso` sin worker_sid y frío por horas: nadie lo trabaja → se marca humano para no ocupar módulo. */
    public function marcarFantasmas(bool $apply = true): array
    {
        $ids = [];
        $corte = now()->subHours(self::HORAS_FANTASMA);
        $q = RoadmapItem::query()
            ->where('estado_aprobacion', 'en_progreso')
            ->whereNull('worker_sid')
            ->where('en_desarrollo_humano', false)
            ->where('updated_at', '<', $corte);

        foreach ($q->get() as $item) {
            $ids[] = (int) $item->id;
            if (! $apply) {
                continue;
            }
            $log = is_array($item->log) ? $item->log : [];
            $log[] = ['ts' => now()->toIso8601String(), 'por' => 'jarvis-avance', 'evento' => 'reclamo_fantasma_marcado',
                'motivo' => 'en_progreso sin worker_sid por más de ' . self::HORAS_FANTASMA . ' h: se marca en_desarrollo_humano para que no cuente como módulo en vuelo. Reversible.'];
            $item->en_desarrollo_humano = true;
            $item->log = $log;
            $item->save();
        }

        return $ids;
    }
}
