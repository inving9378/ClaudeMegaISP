<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Exports\MapaRedBomExport;
use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedProyect;
use App\Modules\Addons\MapaRed\Services\Export\BomExportService;
use App\Modules\Addons\MapaRed\Services\Export\GeoJsonExportService;
use App\Modules\Addons\MapaRed\Services\Export\KmlExportService;
use Maatwebsite\Excel\Facades\Excel;

/**
 * MR-25 Fase 4 (item roadmap #9990445) — exportadores de un proyecto del Mapa de Red.
 * La "carta de empalme PDF" del DoD (D21) YA EXISTE como endpoint propio, scoped por
 * elemento contenedor en vez de por proyecto (MR-19, item #9990565:
 * EmpalmesController::cartaPdf, GET /mapa-red/empalmes/carta-pdf) — no se duplica aquí.
 * Gate: mismo mapa_red_view de ruta que el resto de /mapa-red/api/** (config/route_permission.php).
 */
class ProyectExportController extends Controller
{
    public function kml(int $id, KmlExportService $service)
    {
        $proyecto = MapaRedProyect::findOrFail($id);
        $xml = $service->exportar($proyecto);

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.google-earth.kml+xml',
            'Content-Disposition' => 'attachment; filename="proyecto-' . $id . '.kml"',
        ]);
    }

    public function geojson(int $id, GeoJsonExportService $service)
    {
        $proyecto = MapaRedProyect::findOrFail($id);
        $data = $service->exportar($proyecto);

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="proyecto-' . $id . '.geojson"',
        ]);
    }

    public function bom(int $id, BomExportService $service)
    {
        $proyecto = MapaRedProyect::findOrFail($id);
        $bom = $service->exportar($proyecto);

        return Excel::download(new MapaRedBomExport($bom), "bom-proyecto-{$id}.xlsx");
    }
}
