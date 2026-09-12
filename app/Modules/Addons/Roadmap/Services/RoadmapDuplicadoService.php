<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;

/**
 * #9990999 — CIRC-02 prevención (q2 de #9990903, opción 1 recomendada por Irving,
 * opcion_elegida=c6c8e349a68facca): dedupe por similitud de título/descripción al crear items
 * en la bandeja.
 *
 * Caso real que lo motivó: #9990854 y #9990874 nacieron con ~19 min de diferencia con títulos
 * casi idénticos ("corrección del Circuito CC — para desatorar el flujo" vs "Items de
 * corrección del Circuito CC — para desatorar el flujo", 92%+ de similitud) y nadie lo notó
 * hasta ejecutar uno.
 *
 * NO bloquea la creación — un seguimiento legítimo puede parecerse a propósito (riesgo que la
 * propia decisión de Irving reconoce). Solo detecta; quien llama decide qué hacer con el aviso
 * (`RoadmapItem::booted()` lo usa para escalar a `requiere_irving` + dejar nota).
 */
class RoadmapDuplicadoService
{
    /** Umbral de la decisión de Irving en q2 de #9990903: "más de 80% de similitud". */
    public const UMBRAL_SIMILITUD = 80.0;

    /** Mismo criterio de "bandeja abierta" usado en todo el módulo (ver grep cruzado en el item). */
    private const ESTADOS_CERRADOS = ['completado', 'cancelado', 'rechazado'];

    /** Acota el costo de similar_text() por candidato — sólo importa el arranque del texto. */
    private const LARGO_COMPARACION = 600;

    /** Textos más cortos que esto no dan señal fiable (ruido de falsos positivos). */
    private const LARGO_MINIMO = 15;

    /**
     * Busca, entre los items NO cerrados, el más parecido a (title, description). Devuelve null
     * si ninguno alcanza el umbral.
     *
     * @return array{id:int,title:string,similitud:float}|null
     */
    public function buscar(string $title, ?string $description, ?int $exceptId = null): ?array
    {
        $texto = self::normalizar($title . ' ' . (string) $description);
        if (mb_strlen($texto) < self::LARGO_MINIMO) {
            return null;
        }

        $query = RoadmapItem::query()
            ->whereNotIn('estado_aprobacion', self::ESTADOS_CERRADOS)
            ->select(['id', 'title', 'description']);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $mejor = null;
        foreach ($query->cursor() as $candidato) {
            $textoCandidato = self::normalizar($candidato->title . ' ' . (string) $candidato->description);
            if (mb_strlen($textoCandidato) < self::LARGO_MINIMO) {
                continue;
            }

            $pct = self::similitudNormalizada($texto, $textoCandidato);
            if ($pct >= self::UMBRAL_SIMILITUD && ($mejor === null || $pct > $mejor['similitud'])) {
                $mejor = ['id' => $candidato->id, 'title' => (string) $candidato->title, 'similitud' => $pct];
            }
        }

        return $mejor;
    }

    /**
     * Similitud (0-100) entre dos textos SIN normalizar — normaliza internamente. Función pura,
     * sin dependencia de BD/Laravel: la cubre `RoadmapDuplicadoServiceSimilitudTest` (PHPUnit
     * puro, sin bootear el framework).
     */
    public static function similitud(string $a, string $b): float
    {
        return self::similitudNormalizada(self::normalizar($a), self::normalizar($b));
    }

    private static function similitudNormalizada(string $a, string $b): float
    {
        similar_text($a, $b, $pct);

        return round($pct, 1);
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_substr(trim($texto), 0, self::LARGO_COMPARACION);
        $texto = mb_strtolower($texto);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }
}
