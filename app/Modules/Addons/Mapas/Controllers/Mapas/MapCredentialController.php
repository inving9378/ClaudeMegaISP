<?php

namespace App\Modules\Addons\Mapas\Controllers\Mapas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MapCredential;

class MapCredentialController extends Controller
{
    public function index()
    {
        return view('core-configuracion::maps.update-api-key');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'latitude' => 'required',
            'longitude' => 'required',
            'zoom' => 'required',
        ]);

        $mapCredential = new MapCredential();
        $mapCredential->latitude = $request->latitude;
        $mapCredential->longitude = $request->longitude;
        $mapCredential->zoom = $request->zoom;
        $mapCredential->api_key = $request->api_key;
        $mapCredential->save();

        return response()->json(['status' => 200, 'message' => 'Credenciales creadas correctamente']);
    }

    public function edit()
    {
        $credentials = MapCredential::all()->map(fn($m) => [
            'id'              => $m->id,
            'latitude'        => $m->latitude,
            'longitude'       => $m->longitude,
            'zoom'            => $m->zoom,
            'api_key_preview' => $m->api_key_preview,
            'has_key'         => (bool) $m->getAttributes()['api_key'] ?? false,
        ]);
        return response()->json($credentials);
    }

    /**
     * Config para el RENDER del mapa (LeafletMap, MegaFamilia y widgets de
     * posición geo). Devuelve la api_key REAL: es una key de CLIENTE que de
     * todos modos viaja al navegador para poder cargar el SDK de Google Maps.
     * El enmascarado (api_key_preview) solo aplica a la pantalla de CONFIG
     * (edit()), donde no se debe mostrar la key en el formulario.
     */
    public function renderConfig()
    {
        $m = MapCredential::first();
        if (!$m) {
            return response()->json(null);
        }
        return response()->json([
            'api_key'   => $m->api_key,
            'latitude'  => $m->latitude,
            'longitude' => $m->longitude,
            'zoom'      => $m->zoom,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'latitude' => 'required',
            'longitude' => 'required',
            'zoom' => 'required'
        ]);

        $mapCredential = MapCredential::find($id);
        $mapCredential->latitude = $request->latitude;
        $mapCredential->longitude = $request->longitude;
        $mapCredential->zoom = $request->zoom;
        if ($request->filled('api_key')) {
            $mapCredential->api_key = $request->api_key;
        }
        $mapCredential->save();

        return response()->json(['status' => 200, 'message' => 'Credenciales actualizadas correctamente']);
    }

    public function destroy($id)
    {
        $mapCredential = MapCredential::find($id);
        $mapCredential->delete();

        return response()->json(['status' => 200, 'message' => 'Credenciales eliminadas correctamente']);
    }
}
