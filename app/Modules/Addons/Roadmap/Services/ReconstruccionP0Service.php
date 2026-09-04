<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use InvalidArgumentException;

/**
 * Item #744 (Fase 3 de #624) — mecanismo de reconstrucción de items P0 no-mergeados.
 *
 * Decisión de Irving (q3 del brief original de #624, opción 8e6b4a088d40e077): un item perdido
 * en el incidente P0 se reconstruye como un item NUEVO que referencia al original vía
 * `reabre_item_id`, en vez de reabrir/reescribir el id original (que en muchos casos ni siquiera
 * existe hoy como fila — ver docs/roadmap-p0-inventario-crudo-item624.md).
 *
 * ⚠️ Esta clase SOLO es el mecanismo. NO decide qué ids reconstruir ni corre nada por su cuenta —
 * eso es la Fase 2 (el reporte donde Irving marca qué candidatos reconstruir), fuera de alcance
 * de #744. Se prueba con un caso sintético en tinker; no se invoca en producción todavía.
 *
 * Reusa RoadmapIntakeService (punto único de alta de items): el reconstruido nace igual que
 * cualquier item nuevo, `pendiente_revision` — reconstruir no es aprobar. `reabre_item_id` es un
 * entero de referencia histórica, nunca una FK verificable (el id original puede no existir o
 * estar reasignado a un item real sin relación, ambos casos documentados en el inventario).
 */
class ReconstruccionP0Service
{
    public function __construct(
        private RoadmapIntakeService $intake,
        private RoadmapReportService $reportes
    ) {
    }

    /**
     * Crea el item reconstruido. Devuelve el item ya persistido, con `reabre_item_id` seteado y
     * la nota de reconstrucción ya reflejada en `comentarios_claude`.
     *
     * @param  int  $idOriginal  id del item perdido en el incidente P0 (puede no existir en BD)
     * @param  string  $titulo  título a copiar del original (el llamador decide si prefijarlo)
     * @param  string|null  $descripcion  descripción/spec a copiar del original, si se tiene
     * @param  string  $autor  quién ejecuta la reconstrucción (sid de terminal u otro actor)
     * @param  string|null  $modulo  módulo del item reconstruido, si se conoce
     * @param  string|null  $notaExtra  contexto adicional para el historial (ej. evidencia de log)
     */
    public function reconstruir(
        int $idOriginal,
        string $titulo,
        ?string $descripcion,
        string $autor,
        ?string $modulo = null,
        ?string $notaExtra = null
    ): RoadmapItem {
        if ($idOriginal <= 0) {
            throw new InvalidArgumentException('El id original debe ser un entero positivo.');
        }

        $item = $this->intake->crear([
            'title'       => $titulo,
            'description' => $descripcion,
            'modulo'      => $modulo,
        ], $autor, true);

        $item->forceFill(['reabre_item_id' => $idOriginal])->save();

        $nota = "Reconstruido del incidente P0, id original #{$idOriginal}.";
        if ($notaExtra !== null && trim($notaExtra) !== '') {
            $nota .= ' ' . trim($notaExtra);
        }

        $this->reportes->append(
            $item,
            $autor,
            'nota',
            $nota,
            null,
            ['reabre_item_id' => $idOriginal]
        );

        return $item->refresh();
    }
}
