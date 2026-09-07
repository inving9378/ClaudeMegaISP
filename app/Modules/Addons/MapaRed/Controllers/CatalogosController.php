<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedTipoSplitter;

/**
 * MR-24e Fase 1b (item roadmap #9990558) — catálogos de solo lectura para selects del mapa.
 * GAP detectado en la Fase 1a/investigación previa: MapaRedTipoSplitter (MR-13, #949) no tenía
 * controller/ruta propia (ver comentario de clase). Este controller es deliberadamente mínimo:
 * solo el listado de opciones para un <q-select>, NO un CRUD de splitters.
 */
class CatalogosController extends Controller
{
    public function tipoSplitter()
    {
        return response()->json(
            MapaRedTipoSplitter::orderBy('numero_puertos')->get(['id', 'nombre'])
        );
    }
}
