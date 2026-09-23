<?php

namespace App\Modules\Addons\VoIP\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\VoIP\Models\Extension;
use Illuminate\Http\JsonResponse;

/**
 * MegaVoz Fase 2 — credenciales para el mini-teléfono WebRTC del navegador.
 *
 * Sin permiso propio: cada quien pide SUS PROPIAS credenciales, nunca las de
 * otro — el user_id sale de la sesión (auth()->id()), jamás de un parámetro
 * que llegue del cliente. Es lo mismo que ya asume `ReclamadorExtensionAutomatico`:
 * un usuario tiene como mucho una extensión WebRTC propia.
 */
class MiTelefonoController extends Controller
{
    public function credenciales(): JsonResponse
    {
        $extension = Extension::where('user_id', auth()->id())
            ->where('es_webrtc', true)
            ->where('activo', true)
            ->first();

        if (! $extension) {
            return response()->json(['tiene_telefono' => false]);
        }

        return response()->json([
            'tiene_telefono' => true,
            'numero'         => $extension->numero,
            'nombre'         => $extension->nombre,
            'secret'         => $extension->secret_plain,
            'wss_url'        => 'wss://' . request()->getHttpHost() . '/ws',
            // El dominio de identidad SIP no tiene que ser real ni resolver:
            // solo identifica al endpoint, la ruta real la decide el transporte.
            'realm'          => request()->getHost(),
        ]);
    }
}
