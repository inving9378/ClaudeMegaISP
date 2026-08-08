<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Models\RoadmapItemReport;
use InvalidArgumentException;

/**
 * TORRE V2 — PUNTO ÚNICO del historial de reportes de un item.
 *
 * Todo lo que reporte una terminal, Thomas, Irving o la vía externa entra por aquí. Antes cada
 * quien concatenaba a mano sobre `comentarios_claude` (`$i->comentarios_claude .= "\nEJECUTADO: …"`)
 * desde el prompt del ejecutor: con seis terminales sobre la misma columna, dos escrituras
 * simultáneas se pisan y el rastro se pierde.
 *
 * Aquí el reporte es una fila propia e inmutable, y el resumen de la columna vieja se mantiene
 * como CONSECUENCIA (para no romper la UI actual, que lee `comentarios_claude`), acotado por tamaño.
 */
class RoadmapReportService
{
    /**
     * `comentarios_claude` es TEXT (~64 KB). Con seis terminales reportando durante meses eso se
     * llena y una escritura empieza a truncar en medio de un carácter multibyte. El historial
     * completo vive en `roadmap_item_reports`; la columna solo conserva la COLA reciente.
     */
    private const RESUMEN_MAX = 12000;

    /**
     * Agrega un reporte al historial del item. Append-only: nunca actualiza ni borra una fila
     * previa. Devuelve el reporte creado.
     *
     * @param  array  $meta  datos estructurados del evento (opción elegida, commit, archivos…)
     */
    public function append(
        RoadmapItem $item,
        string $autor,
        string $tipo,
        string $resumen,
        ?string $cuerpo = null,
        array $meta = []
    ): RoadmapItemReport {
        $tipo = trim($tipo) !== '' ? trim($tipo) : 'nota';
        if (! in_array($tipo, RoadmapItemReport::TIPOS, true)) {
            throw new InvalidArgumentException(
                "Tipo de reporte inválido '{$tipo}'. Válidos: " . implode(', ', RoadmapItemReport::TIPOS)
            );
        }

        $resumen = trim($resumen);
        if ($resumen === '') {
            throw new InvalidArgumentException('El reporte necesita un resumen de una línea.');
        }

        $reporte = RoadmapItemReport::create([
            'roadmap_item_id' => $item->id,
            'autor'           => mb_substr(trim($autor) !== '' ? trim($autor) : 'desconocido', 0, 64),
            'tipo'            => $tipo,
            'resumen'         => mb_substr($resumen, 0, 500),
            'cuerpo'          => $cuerpo,
            'meta'            => $meta ?: null,
        ]);

        $this->reflejarEnResumen($item, $reporte);

        return $reporte;
    }

    /**
     * Mantiene `comentarios_claude` como espejo LEGIBLE del historial (la UI actual y el prompt del
     * revisor la siguen leyendo). Nunca pisa lo anterior: agrega al final y, si el texto se pasa
     * del tope, recorta por el PRINCIPIO conservando la cola reciente y avisando del recorte.
     */
    private function reflejarEnResumen(RoadmapItem $item, RoadmapItemReport $reporte): void
    {
        $sello = '[' . $reporte->created_at->format('Y-m-d H:i') . " · {$reporte->autor} · {$reporte->tipo}] ";
        $linea = $sello . $reporte->resumen;

        $actual = (string) $item->comentarios_claude;
        $nuevo  = trim($actual === '' ? $linea : $actual . "\n" . $linea);

        if (mb_strlen($nuevo) > self::RESUMEN_MAX) {
            $aviso = "[…historial recortado — completo en la pestaña de reportes del item…]\n";
            $nuevo = $aviso . mb_substr($nuevo, -1 * (self::RESUMEN_MAX - mb_strlen($aviso)));
        }

        // Escritura acotada a la columna espejo: no toca estado, nivel ni nada del despacho.
        $item->forceFill(['comentarios_claude' => $nuevo])->save();
    }

    /** Historial del item, del más nuevo al más viejo, serializado para API/UI. */
    public function historial(int $itemId, int $limite = 50): array
    {
        return RoadmapItemReport::where('roadmap_item_id', $itemId)
            ->recientes()
            ->limit(max(1, min($limite, 200)))
            ->get()
            ->map(fn (RoadmapItemReport $r) => $r->toApi())
            ->values()
            ->all();
    }

    /** Cuántos reportes tiene el item (para pintar el contador sin traer las filas). */
    public function conteo(int $itemId): int
    {
        return RoadmapItemReport::where('roadmap_item_id', $itemId)->count();
    }
}
