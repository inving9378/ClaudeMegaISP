<?php

namespace App\Modules\Addons\MapaRed\Services\Export;

use App\Modules\Addons\MapaRed\Models\MapaRedProyect;
use App\Modules\Addons\MapaRed\Models\MapaRedTipoCable;

/**
 * MR-25 Fase 4 (item roadmap #9990445) — lista de materiales (BOM) de un proyecto.
 *
 * Alcance real, no inventado: `mapared_cables`/`mapared_splitters` (el modelo estructurado de
 * MR-09/MR-13) todavía no quedan ligados a un proyecto en los datos reales (el backfill de
 * MR-15 — mapared:backfill — no pobló `proyecto_id` ni `tipo_cable_id` al correr contra el
 * espejo legado; verificado: 0 de 250 cables con esas columnas pobladas). Lo único confiable
 * por proyecto es `mapared_layers` (project_id sí está poblado en las 7003 filas migradas).
 *
 * Por eso el BOM agrupa por LAYER (type=polyline -> cable, marker con dialog de caja -> NAP/
 * mufa/rack), detecta el tipo de cable por el mismo patrón `FO\d+` del nombre que usa el
 * backfill, y solo asigna precio cuando hay EXACTAMENTE UNA fila del catálogo con ese
 * número de hilos (match por aproximación, documentado en la columna "precio_nota" — nunca
 * inventa un precio cuando hay ambigüedad o no hay catálogo cargado).
 */
class BomExportService
{
    public function exportar(MapaRedProyect $proyecto): array
    {
        $layers = $proyecto->layers()->get();

        return [
            'cables' => $this->cables($layers->where('type', 'polyline')),
            'cajas' => $this->cajas($layers->where('type', 'marker')),
        ];
    }

    private function cables($polylines): array
    {
        $grupos = [];

        foreach ($polylines as $layer) {
            $nombre = $layer->data['name'] ?? $layer->text ?? '';
            $hilos = $this->deducirHilos($nombre);
            $clave = $hilos ? "FO{$hilos}" : 'Sin tipo detectado';

            $grupos[$clave] ??= ['tipo' => $clave, 'hilos' => $hilos, 'cantidad_tramos' => 0, 'longitud_metros_aprox' => 0.0];
            $grupos[$clave]['cantidad_tramos']++;
            $grupos[$clave]['longitud_metros_aprox'] += $this->longitudAproximada($layer->coords);
        }

        foreach ($grupos as &$grupo) {
            $grupo['longitud_metros_aprox'] = round($grupo['longitud_metros_aprox'], 1);
            [$grupo['precio_unitario'], $grupo['precio_nota']] = $this->precioCable($grupo['hilos']);
            $grupo['subtotal'] = $grupo['precio_unitario'] !== null
                ? round($grupo['precio_unitario'] * $grupo['longitud_metros_aprox'], 2)
                : null;
        }

        return array_values($grupos);
    }

    private function cajas($markers): array
    {
        $cajasDialogos = ['service_box', 'junction_box', 'rack'];
        $grupos = [];

        foreach ($markers as $layer) {
            if (!in_array($layer->dialog, $cajasDialogos, true)) {
                continue;
            }
            $grupos[$layer->dialog] ??= ['tipo' => $this->etiquetaCaja($layer->dialog), 'cantidad' => 0];
            $grupos[$layer->dialog]['cantidad']++;
        }

        return array_values($grupos);
    }

    private function etiquetaCaja(string $dialog): string
    {
        return match ($dialog) {
            'service_box' => 'NAP / caja de servicio',
            'junction_box' => 'Mufa',
            'rack' => 'Rack',
            default => $dialog,
        };
    }

    private function deducirHilos(string $nombre): ?int
    {
        if (preg_match('/FO\s*(\d+)/i', $nombre, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /** Precio solo si hay exactamente una fila de catálogo con ese número de hilos — nunca adivina entre varias. */
    private function precioCable(?int $hilos): array
    {
        if ($hilos === null) {
            return [null, 'Tipo de cable no detectado en el nombre de la ruta.'];
        }

        $candidatos = MapaRedTipoCable::where('numero_hilos', $hilos)->get();

        if ($candidatos->isEmpty()) {
            return [null, "Sin catálogo cargado para cable de {$hilos} hilos (dar de alta en Mapa de Red > Catálogos)."];
        }

        if ($candidatos->count() > 1) {
            return [null, "{$candidatos->count()} tipos de catálogo con {$hilos} hilos — precio ambiguo, revisar manualmente."];
        }

        return [(float) $candidatos->first()->precio, null];
    }

    /** Suma de distancias Haversine entre puntos consecutivos — estimado, no la ruta real tendida. */
    private function longitudAproximada(?array $coords): float
    {
        if (!is_array($coords) || count($coords) < 2) {
            return 0.0;
        }

        $total = 0.0;
        for ($i = 1; $i < count($coords); $i++) {
            $total += $this->haversine($coords[$i - 1], $coords[$i]);
        }
        return $total;
    }

    private function haversine(array $a, array $b): float
    {
        $r = 6371000; // metros
        $lat1 = deg2rad($a['lat'] ?? 0);
        $lat2 = deg2rad($b['lat'] ?? 0);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad(($b['lng'] ?? 0) - ($a['lng'] ?? 0));

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($h), sqrt(1 - $h));
    }
}
