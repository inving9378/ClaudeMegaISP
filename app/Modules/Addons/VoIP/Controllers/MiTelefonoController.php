<?php

namespace App\Modules\Addons\VoIP\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Services\IABotCustomerService;
use App\Modules\Core\Clientes\Controllers\ClientInformationController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

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
            // Servidor TURN (25-sep-2026, ver config/voip.php 'turn') — la
            // contraseña viaja por AQUÍ (nunca hardcodeada en el .vue) para
            // no violar la convención de secretos-solo-en-.env. Null si aún
            // no está configurado (turn_username vacío) — el frontend debe
            // tolerar no traer TURN, cae solo al STUN público.
            'turn_url'        => config('voip.turn.url'),
            'turn_username'   => config('voip.turn.username'),
            'turn_credential' => config('voip.turn.credential'),
        ]);
    }

    /**
     * ¿Hay alguien registrado ahí ahora mismo? Mismo mecanismo (ARI) que ya
     * usa ExtensionController::verificar(), pero sin permiso voip.* — cualquier
     * usuario autenticado con teléfono propio puede consultar disponibilidad
     * de un destino antes de marcar (no expone nada sensible, solo en línea/no).
     */
    public function disponibilidad(string $numero): JsonResponse
    {
        if (! preg_match('/^[a-zA-Z0-9]{1,20}$/', $numero)) {
            return response()->json(['disponible' => false, 'estado' => 'invalido']);
        }

        $host = config('voip.ari_host');
        $port = config('voip.ari_port', 8088);
        $user = config('voip.ari_user');
        $pass = config('voip.ari_pass');

        try {
            $resp = Http::withBasicAuth($user, $pass)
                ->timeout(3)
                ->get("http://{$host}:{$port}/ari/endpoints/PJSIP/{$numero}");

            if ($resp->status() === 404) {
                return response()->json(['disponible' => false, 'estado' => 'no_existe']);
            }

            $body   = $resp->json();
            $estado = $body['state'] ?? 'unknown';

            return response()->json([
                'disponible' => $resp->successful() && $estado === 'online',
                'estado'     => $estado,
            ]);
        } catch (\Throwable $e) {
            // Fallo del propio chequeo (ARI caído, timeout) — no bloquea marcar,
            // solo no se puede confirmar disponibilidad de antemano.
            return response()->json(['disponible' => null, 'estado' => 'sin_datos']);
        }
    }

    /**
     * MegaVoz Fase 4 — ventana emergente al contestar/marcar: identifica al
     * cliente por CallerID y trae saldo + servicio + tickets abiertos.
     *
     * Reusa `IABotCustomerService` (ya existía en el módulo, sin consumidor
     * todavía — servía de base para el bot de IA de Fase 6) para el match de
     * teléfono y el servicio activo, y `ClientInformationController` (el
     * mismo que usa la ficha del cliente) para saldo y tickets — nada de
     * queries nuevas paralelas a las que ya existen.
     *
     * Sin permiso `voip.*` propio, mismo criterio que `disponibilidad()`: solo
     * llega aquí un usuario con teléfono asignado (staff ya vetado por
     * `ReclamadorExtensionAutomatico`), y es exactamente el caso de uso
     * — "quién me está llamando ahora".
     */
    public function ficha(string $numero): JsonResponse
    {
        if (! preg_match('/^[+0-9 ()\-]{3,20}$/', $numero)) {
            return response()->json(['found' => false]);
        }

        $identidad = app(IABotCustomerService::class)->identifyCustomer($numero);

        if (! $identidad['found']) {
            return response()->json(['found' => false]);
        }

        $clienteId = $identidad['id'];
        $clientInfo = app(ClientInformationController::class);
        $balance = $clientInfo->getClientWithBalance($clienteId);
        $tickets = $clientInfo->getClientTicketsOpen($clienteId);

        return response()->json([
            'found'           => true,
            'id'              => $clienteId,
            'name'            => $identidad['name'],
            'email'           => $identidad['email'],
            'balance'         => $balance['balance'],
            'active_services' => $identidad['active_services'],
            'tickets_open'    => $tickets['open'],
        ]);
    }
}
