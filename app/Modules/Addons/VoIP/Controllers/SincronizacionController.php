<?php

namespace App\Modules\Addons\VoIP\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\VoIP\Services\SincronizadorAsterisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * «Sincronizar con Asterisk» de la pantalla de Extensiones: primero muestra lo que haría
 * (GET) y solo lo hace al confirmar (POST). Corre como el usuario del servidor web, que es
 * quien puede escribir el archivo de registraciones de troncales.
 */
class SincronizacionController extends Controller
{
    public function __construct(private SincronizadorAsterisk $sincronizador)
    {
    }

    private function permitido(): bool
    {
        $u = auth()->user();

        return $u && $u->can('voip.extensiones.provision') && $u->can('voip.troncales.provision');
    }

    public function revisar(): JsonResponse
    {
        if (! $this->permitido()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json($this->sincronizador->revisar(false));
    }

    public function aplicar(): JsonResponse
    {
        if (! $this->permitido()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $lock = Cache::lock('voip-sincronizacion', 300);
        if (! $lock->get()) {
            return response()->json(['error' => 'Ya hay una sincronización en curso.'], 409);
        }
        try {
            $r = $this->sincronizador->revisar(true);
            Log::info('VoIP: sincronización con Asterisk por ' . auth()->user()->login_user, [
                'tareas' => count($r['tareas']), 'pendientes' => count($r['pendientes']), 'fallas' => $r['fallas'],
            ]);
            Cache::forget('voip_ext_estados');

            return response()->json($r);
        } finally {
            $lock->release();
        }
    }
}
