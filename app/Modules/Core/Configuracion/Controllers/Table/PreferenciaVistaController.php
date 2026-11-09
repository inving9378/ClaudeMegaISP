<?php

namespace App\Modules\Core\Configuracion\Controllers\Table;

use App\Http\Controllers\Controller;
use App\Models\PreferenciaVistaUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Preferencias de vista por usuario: lo que cada quien deja como predeterminado en un listado.
 *
 * El usuario sale siempre de la sesión, nunca de la petición: cada quien lee y escribe solo lo
 * suyo. Vistas, preferencias y la regla de cada valor: config/preferencias_vista.php.
 */
class PreferenciaVistaController extends Controller
{
    public function show(Request $request, string $vista): JsonResponse
    {
        $permitidas = $this->preferenciasDe($vista);

        $preferencias = PreferenciaVistaUsuario::where('user_id', $request->user()->id)
            ->where('vista', $vista)
            ->whereIn('preferencia', array_keys($permitidas))
            ->get()
            ->mapWithKeys(fn ($fila) => [$fila->preferencia => $fila->valor]);

        // no-store: al volver con «atrás» el navegador reutilizaba la respuesta anterior (no-cache no
        // basta) y la pantalla no veía el predeterminado recién guardado.
        return response()->json(['preferencias' => (object) $preferencias->all()])
            ->header('Cache-Control', 'no-store, private');
    }

    /** Guarda el valor; con `valor: null` la borra y la vista vuelve a su valor inicial. */
    public function update(Request $request, string $vista, string $preferencia): JsonResponse
    {
        $reglas = $this->preferenciasDe($vista)[$preferencia] ?? abort(404, 'Preferencia no permitida.');

        abort_unless($request->exists('valor'), 422, 'Falta el valor.');
        $valor = $request->input('valor');
        $filtro = ['user_id' => $request->user()->id, 'vista' => $vista, 'preferencia' => $preferencia];

        if ($valor === null) {
            PreferenciaVistaUsuario::where($filtro)->delete();

            return response()->json(['preferencia' => $preferencia, 'valor' => null]);
        }

        abort_if(strlen(json_encode($valor)) > config('preferencias_vista.max_bytes'), 422, 'El valor es demasiado grande.');
        Validator::make(['valor' => $valor], ['valor' => $reglas])->validate();

        PreferenciaVistaUsuario::updateOrCreate($filtro, ['valor' => $valor]);

        return response()->json(['preferencia' => $preferencia, 'valor' => $valor]);
    }

    private function preferenciasDe(string $vista): array
    {
        return config("preferencias_vista.vistas.{$vista}") ?? abort(404, 'Vista no permitida.');
    }
}
