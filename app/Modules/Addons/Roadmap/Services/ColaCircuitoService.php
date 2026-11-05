<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\Descomposicion\DependenciaGate;
use App\Modules\Addons\Roadmap\Support\DependenciaItems;
use Illuminate\Support\Facades\Cache;

/**
 * Limpieza de la Hoja de ruta — UNA sola definición de la cola para el despachador, la Panorama, la
 * pestaña Integración y Jarvis. Cada item vivo cae en exactamente uno de tres grupos:
 *
 *  - en curso:   una terminal (o una persona) lo trabaja ahora.
 *  - tomable:    el despachador lo tomaría en cuanto haya terminal libre. Es SU propio cálculo
 *                ({@see RoadmapCircuitoService::queSigue()}: la ronda más lo que espera turno porque su
 *                módulo ya tiene el tope de terminales), no una copia de sus reglas.
 *  - bloqueado:  con una causa de {@see CAUSAS}, sacada de las mismas reglas del despacho
 *                (`motivoNoDespachable()`, dependencias, paraguas). Nada se adivina.
 *
 * Antes cada pantalla contaba distinto: Jarvis leía la ronda del despachador («1 en cola») y la Hoja
 * de ruta llamaba «en cola» a todo lo aprobado sin terminal (~80), aunque estuviera apartado.
 */
class ColaCircuitoService
{
    /** causa => [texto llano, quién lo destraba: 'irving' | 'solo'] */
    public const CAUSAS = [
        'dependencia'      => ['espera a que termine otro pendiente', 'solo'],
        'paraguas'         => ['paraguas: cierra solo cuando terminen sus partes', 'solo'],
        'espera_creciente' => ['terminó vacío; vuelve a intentarse tras una espera', 'solo'],
        'espera_merge'     => ['terminado, espera tu merge', 'irving'],
        'decision'         => ['espera tu decisión', 'irving'],
        'insumo'           => ['espera algo que solo tú puedes dar', 'irving'],
        'anti_bucle'       => ['se repetía sin avanzar: falta tu decisión', 'irving'],
        'apartado'         => ['apartado de las terminales', 'irving'],
        'sesion'           => ['pide trabajarlo contigo presente', 'irving'],
        'sin_aprobar'      => ['todavía no está aprobado', 'irving'],
        'agendado'         => ['agendado para más adelante', 'solo'],
        'otra'             => ['otra causa', 'irving'],
    ];

    private const CERRADOS = ['completado', 'cancelado', 'rechazado'];

    public function __construct(private RoadmapCircuitoService $circuito)
    {
    }

    /** Foto de la cola (15 s de caché: la Torre la pide seguido y el cálculo recorre todo lo vivo). */
    public function foto(bool $fresca = false): array
    {
        if ($fresca) {
            Cache::forget('circuito.cola_foto');
        }

        return Cache::remember('circuito.cola_foto', 15, fn () => $this->calcular());
    }

    /** Lo que se pinta y se cuenta: totales + causas, sin las listas completas. */
    public function resumen(): array
    {
        $f = $this->foto();

        return [
            'tomables'        => $f['tomables']['total'],
            'salen_ya'        => $f['tomables']['salen_ya'],
            'esperan_turno'   => $f['tomables']['esperan_turno'],
            'bloqueados'      => $f['bloqueados']['total'],
            'en_curso'        => $f['en_curso']['total'],
            'causas'          => array_map(fn ($c) => ['causa' => $c['causa'], 'texto' => $c['texto'], 'total' => $c['total'],
                'destraba' => $c['destraba'], 'ejemplos' => array_slice($c['ids'], 0, 6)], $f['bloqueados']['por_causa']),
            'requiere_irving' => $f['requiere_irving'],
            'incoherencias'   => $f['incoherencias'],
            'at'              => $f['at'],
        ];
    }

    private function calcular(): array
    {
        $vivos = RoadmapItem::query()->whereNull('archivado_at')
            ->whereNotIn('estado_aprobacion', self::CERRADOS)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'done'))
            ->orderBy('id')->get();

        // El despachador decide qué es tomable; aquí solo se lee su resultado.
        $q        = $this->circuito->queSigue();
        $ronda    = array_map('intval', $q['ronda']);
        $enTurno  = array_map('intval', $q['en_turno']);
        $tomables = array_flip(array_merge($ronda, $enTurno));

        $enCurso = [];
        $tom     = [];
        $causas  = [];
        $porId   = [];
        foreach ($vivos as $i) {
            $id = (int) $i->id;
            $porId[$id] = $i;
            if ($i->estado_aprobacion === 'en_progreso' || $i->status === 'in_progress' || $i->en_desarrollo_humano) {
                $enCurso[] = $id;
            } elseif (isset($tomables[$id])) {
                $tom[] = $id;
            } else {
                $causas[$this->causa($i)][] = $id;
            }
        }

        // El grafo incluye lo archivado que sigue abierto: un paraguas archivado (p. ej. ya mergeado y
        // esperando a sus partes) sigue frenando a quien depende de él.
        $destraba = $this->cuantosDestraba(RoadmapItem::query()->whereNotIn('estado_aprobacion', self::CERRADOS)
            ->get(['id', 'depende_de', 'origen_item_id', 'position', 'descomposicion', 'subtasks']));
        $porCausa = [];
        foreach ($causas as $c => $ids) {
            [$texto, $quien] = self::CAUSAS[$c] ?? self::CAUSAS['otra'];
            $porCausa[] = ['causa' => $c, 'texto' => $texto, 'destraba' => $quien, 'total' => count($ids), 'ids' => $ids];
        }
        usort($porCausa, fn ($a, $b) => $b['total'] <=> $a['total']);

        // Lo que solo Irving puede mover, ordenado por cuántos pendientes libera (directos o en cadena).
        $irving = [];
        foreach ($causas as $c => $ids) {
            if ((self::CAUSAS[$c][1] ?? 'irving') !== 'irving') {
                continue;
            }
            foreach ($ids as $id) {
                $irving[] = ['id' => $id, 'titulo' => mb_strimwidth((string) $porId[$id]->title, 0, 80, '…'),
                    'causa' => $c, 'que_hacer' => $this->queHacer($c, $porId[$id]), 'destraba' => $destraba[$id] ?? 0,
                    'nivel' => $porId[$id]->nivel_riesgo];
            }
        }
        usort($irving, fn ($a, $b) => [$b['destraba'], $a['id']] <=> [$a['destraba'], $b['id']]);

        // Coherencia con la Hoja de ruta: «en cola» ahí debe ser exactamente lo tomable aquí.
        $incoherencias = [];
        foreach ($vivos as $i) {
            $id = (int) $i->id;
            if (in_array($id, $enCurso, true)) {
                continue;
            }
            $hojaDice = $i->estado_cola === 'en_cola';
            if ($hojaDice !== isset($tomables[$id])) {
                $incoherencias[] = ['id' => $id, 'hoja_de_ruta' => $i->estado_cola, 'despachador' => isset($tomables[$id]) ? 'tomable' : 'bloqueado'];
            }
        }

        return [
            'at'         => now()->toIso8601String(),
            'tomables'   => ['total' => count($tom), 'salen_ya' => count($ronda), 'esperan_turno' => count($enTurno), 'ids' => $tom],
            'bloqueados' => ['total' => array_sum(array_map('count', $causas)), 'por_causa' => $porCausa],
            'en_curso'   => ['total' => count($enCurso), 'ids' => $enCurso],
            'requiere_irving' => array_slice($irving, 0, 8),
            'incoherencias'   => $incoherencias,
        ];
    }

    /** La causa de un item vivo que el despachador NO tomaría, con sus propias reglas. */
    public function causa(RoadmapItem $i): string
    {
        if ($i->estado_aprobacion === 'requiere_irving') {
            return in_array($i->motivo_espera, [null, '', 'decision'], true) ? 'decision' : 'insumo';
        }
        if ($i->tieneSubItemsAbiertos()) {
            return 'paraguas';
        }
        if ($i->esperando_merge_irving && ! $i->merge_commit) {
            return 'espera_merge';
        }
        if ($this->circuito->esperandoDependencias((int) $i->id) !== null) {
            return 'dependencia';
        }
        if ($i->esperaVueltasVaciasHasta() !== null) {
            return 'espera_creciente';
        }

        $m = $i->motivoNoDespachable(false);
        $code = $m['code'] ?? null;

        return match (true) {
            $code === 'bloqueado_por_dependencia' => 'dependencia',
            $code === 'esperando_merge'           => 'espera_merge',
            $code === 'bloqueado_por_bucle'       => 'anti_bucle',
            $code === 'sesion_supervisada'        => 'sesion',
            $code === 'agendado'                  => 'agendado',
            $code === 'freno_humano'              => 'decision',
            $code === 'fuera_del_pool'            => $i->motivo_espera === 'decision' ? 'decision' : 'apartado',
            in_array($code, ['no_despachable', 'sin_aprobar'], true),
            in_array($i->estado_aprobacion, ['pendiente_revision', 'aprobado_revisor'], true) => 'sin_aprobar',
            default => 'otra',
        };
    }

    private function queHacer(string $causa, RoadmapItem $i): string
    {
        return match ($causa) {
            'espera_merge' => 'Mergearlo (Torre → Integración)',
            'decision', 'anti_bucle' => 'Decidir en tu bandeja (o «Quitar el freno y aprobar»)',
            'insumo'       => 'Dar lo que pide: ' . ($i->motivo_espera ?: 'ver el item'),
            'sesion'       => 'Trabajarlo en sesión contigo',
            'sin_aprobar'  => 'Aprobarlo o rechazarlo',
            'apartado'     => 'Decidir si vuelve a la cola: ' . mb_strimwidth((string) ($i->motivo_bloqueo ?: 'sin motivo anotado'), 0, 90, '…'),
            default        => 'Revisar el item',
        };
    }

    /**
     * Cuántos pendientes vivos quedan esperando, directo o en cadena, a cada item: por `depende_de`
     * abierto, por la posición dentro de su fase (la sección 10 espera a la 7; {@see DependenciaGate})
     * y porque un paraguas espera a sus partes. Es el orden de la lista de lo que pide a Irving.
     *
     * @return array<int,int>
     */
    private function cuantosDestraba($vivos): array
    {
        $vivosIds = $vivos->pluck('id')->map(fn ($v) => (int) $v)->flip();
        $esperan  = [];   // bloqueador => [quienes lo esperan]
        $gate     = config('circuito.dependencia_gate.enabled', true) ? app(DependenciaGate::class) : null;
        $seccion  = [];   // fase => [posición => id vivo]
        foreach ($vivos as $w) {
            if ($w->origen_item_id) {
                $seccion[(int) $w->origen_item_id][(int) $w->position] = (int) $w->id;
            }
        }
        foreach ($vivos as $w) {
            foreach ($gate && $w->origen_item_id ? $gate->dependeDeDe($w) : [] as $pos) {
                $b = $seccion[(int) $w->origen_item_id][$pos] ?? null;
                if ($b && $b !== (int) $w->id) {
                    $esperan[$b][] = (int) $w->id;
                }
            }
            foreach (DependenciaItems::ids($w->depende_de) as $d) {
                if (isset($vivosIds[(int) $d])) {
                    $esperan[(int) $d][] = (int) $w->id;
                }
            }
            if ($w->origen_item_id && isset($vivosIds[(int) $w->origen_item_id])) {
                $esperan[(int) $w->id][] = (int) $w->origen_item_id;   // el paraguas espera a su parte
            }
        }

        $total = [];
        foreach (array_keys($esperan) as $b) {
            $vistos = [];
            $pila   = $esperan[$b];
            while ($pila) {
                $x = array_pop($pila);
                if (isset($vistos[$x])) {
                    continue;
                }
                $vistos[$x] = true;
                foreach ($esperan[$x] ?? [] as $y) {
                    $pila[] = $y;
                }
            }
            $total[$b] = count($vistos);
        }

        return $total;
    }
}
