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
     * @return array{eta_segundos:int, eta_metodo:string} eta_metodo = 'historico'|'heuristico'
     */
    public function estimar(?string $modulo, ?string $nivelRiesgo): array
    {
        $segundos = $this->medianaHistorica($modulo, $nivelRiesgo);
        if ($segundos !== null) {
            return ['eta_segundos' => $segundos, 'eta_metodo' => 'historico'];
        }

        return [
            'eta_segundos' => self::BUCKETS[$nivelRiesgo] ?? self::BUCKET_DEFAULT,
            'eta_metodo'   => 'heuristico',
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
