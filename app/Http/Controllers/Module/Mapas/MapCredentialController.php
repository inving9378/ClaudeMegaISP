<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MapCredential;

class MapCredentialController extends Controller
{
    public function index()
    {
        return view('meganet.module.setting.maps.update-api-key');
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
        $mapCredential = MapCredential::all();
        return response()->json($mapCredential);
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
        $mapCredential->api_key = $request->api_key;
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
