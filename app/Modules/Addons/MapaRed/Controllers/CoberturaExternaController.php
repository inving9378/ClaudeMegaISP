<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Services\MapaRedCoberturaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Item #1159 — «¿hay cobertura en este punto?» para sistemas de fuera (el asistente Sofía, en
 * Vercel). Misma regla que el mapa (MapaRedCoberturaService::consultarPunto: caja con puertos
 * libres a `mapared.cobertura.radio_metros` o menos), pero contesta SOLO sí/no y la distancia:
 * nada de la red (ni la caja, ni su nombre, ni sus puertos). La clave la revisa `clave.externa`.
 */
class CoberturaExternaController extends Controller
{
    public function __construct(private MapaRedCoberturaService $service)
    {
    }

    public function consultar(Request $request)
    {
        $v = Validator::make($request->query(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);
        if ($v->fails()) {
            return response()->json([
                'error'   => 'parametros_invalidos',
                'mensaje' => 'Manda lat y lng en grados decimales, por ejemplo ?lat=19.72&lng=-99.09.',
            ], 422);
        }

        $r = $this->service->consultarPunto((float) $request->query('lat'), (float) $request->query('lng'));
        $distancia = $r['nap_mas_cercana']['distancia_metros'] ?? null;

        return response()
            ->json([
                'cobertura'   => (bool) $r['cobertura'],
                'distancia_m' => $distancia === null ? null : (int) round($distancia),
            ])
            ->header('Cache-Control', 'no-store');
    }
}
