<?php

namespace App\Modules\Addons\Payments\Support;

use Illuminate\Support\Facades\DB;

/**
 * Estado del cliente (client_main_information.estado) para elegir el texto de
 * los mensajes de conciliación por WhatsApp.
 *
 * Regla (segura): SOLO 'Activo' se trata como ACTIVO. Cualquier otro valor
 * (Bloqueado, Inactivo, Cancelado) se trata como suspendido → se le habla de
 * "reactivar tu servicio" en vez de prometerle navegación que quizá aún no
 * tiene. Para cambiar la política, ajusta ACTIVE_STATES.
 */
class ClientStatus
{
    /** Valores de estado considerados "activo" (servicio arriba). */
    private const ACTIVE_STATES = ['activo'];

    public static function isActive(?int $clientId): bool
    {
        if (!$clientId) {
            return true; // cliente desconocido → mensaje neutro (no promete reactivación)
        }
        $estado = DB::table('client_main_information')->where('client_id', $clientId)->value('estado');

        return in_array(mb_strtolower(trim((string) $estado)), self::ACTIVE_STATES, true);
    }
}
