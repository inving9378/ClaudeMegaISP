<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;

/**
 * #546 — Reloj en regresión por terminal: estima cuánto tardará un item recién reclamado.
 *
 * Método: mediana histórica de duración (completed_at - trabajo_iniciado_at) de los últimos
 * items COMPLETADOS del mismo módulo + nivel_riesgo. Con menos de MIN_MUESTRAS datos comparables
 * (normal al arrancar: `trabajo_iniciado_at` es columna nueva), cae a un bucket fijo por nivel.
 */
class EstimadorTiempo
{
    /** Mínimo de muestras históricas comparables para confiar en la mediana. */
    private const MIN_MUESTRAS = 3;

    /** Cuántas muestras recientes considerar como máximo (ventana, no todo el histórico). */
    private const VENTANA_MUESTRAS = 20;

    /** Buckets por nivel de riesgo (segundos) — A corto, B medio, C largo. */
    private const BUCKETS = [
        'A' => 900,    // 15 min
        'B' => 2700,   // 45 min
        'C' => 5400,   // 90 min
    ];

    /** Bucket por default cuando el nivel de riesgo no es A/B/C reconocido. */
    private const BUCKET_DEFAULT = self::BUCKETS['B'];

    /**
     * TECHO REAL DE UNA VUELTA (2026-08-26). El reloj de la Torre prometía tiempos que el harness
     * tiene prohibido cumplir: los buckets son 900/2700/5400 s y `vuelta.sh` mata al agente con
     * `timeout 600`. Los seis slots mostraban 26-82 min mientras el trabajo real terminaba en 1-6,
     * y el 26-ago el item #190 se cortó a los 600 s con cero commits — el panel le daba 45 minutos.
     * Peor: llegó a la bandeja rotulado "no hubo avance", que se lee como item difícil cuando lo
     * que se acabó fue el reloj.
     *
     * Por eso `eta_segundos` sale TOPADO al techo: es lo que se persiste y lo que pinta el reloj,
     * y nunca puede prometer más de lo que la vuelta permite. El estimado CRUDO se conserva en
     * `eta_crudo_segundos` porque sí significa algo donde es accionable: `JarvisService::
     * caberEnVuelta()` lo compara contra su umbral para decidir si un item hay que descomponerlo
     * ANTES de picar código. Topar ahí también habría vuelto "cabe" a todo por construcción.
     *
     * @return array{eta_segundos:int, eta_crudo_segundos:int, eta_metodo:string, techo_segundos:int, topada:bool}
     *         eta_metodo = 'historico'|'heuristico' (el método REAL; el techo no lo cambia)
     */
    public function estimar(?string $modulo, ?string $nivelRiesgo): array
    {
        $segundos = $this->medianaHistorica($modulo, $nivelRiesgo);
        $metodo   = $segundos !== null ? 'historico' : 'heuristico';
        $crudo    = $segundos ?? (self::BUCKETS[$nivelRiesgo] ?? self::BUCKET_DEFAULT);

        // #9990338 — el techo real por nivel_riesgo (antes uniforme a 600s vía config() directo,
        // aunque el guard real de la vuelta —SchedulerCommand::vidaMaximaSegundos()— permita más
        // para B/C). Mismo punto de verdad que usa el propio guard.
        $techo = RoadmapCircuitoService::vidaMaximaSegundos($nivelRiesgo);

        return [
            'eta_segundos'       => min($crudo, $techo),
            'eta_crudo_segundos' => $crudo,
            'eta_metodo'         => $metodo,
            'techo_segundos'     => $techo,
            'topada'             => $crudo > $techo,
        ];
    }

    /** Mediana de duraciones (segundos) de items completados comparables, o null si no hay suficientes. */
    private function medianaHistorica(?string $modulo, ?string $nivelRiesgo): ?int
    {
        $modulo = trim((string) $modulo);
        if ($modulo === '' || ! $nivelRiesgo) {
            return null;   // sin footprint o sin nivel no hay con qué comparar
        }

        $duraciones = RoadmapItem::query()
            ->where('modulo', $modulo)
            ->where('nivel_riesgo', $nivelRiesgo)
            ->where('estado_aprobacion', 'completado')
            ->whereNotNull('trabajo_iniciado_at')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(self::VENTANA_MUESTRAS)
            ->get(['trabajo_iniciado_at', 'completed_at'])
            ->map(fn (RoadmapItem $i) => $i->trabajo_iniciado_at->diffInSeconds($i->completed_at))
            ->filter(fn ($segundos) => $segundos > 0)
            ->sort()->values();

        if ($duraciones->count() < self::MIN_MUESTRAS) {
            return null;
        }

        $n   = $duraciones->count();
        $mid = intdiv($n, 2);
        $mediana = ($n % 2 === 0)
            ? ($duraciones[$mid - 1] + $duraciones[$mid]) / 2
            : $duraciones[$mid];

        return (int) round($mediana);
    }
}
