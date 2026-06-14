<?php

namespace App\Modules\Addons\PortalCliente\Services;

use Illuminate\Support\Facades\Auth;

/**
 * Framework de aislamiento multi-tenant para el Portal Cliente.
 *
 * REGLA DURA: toda consulta del portal que devuelva datos de cliente
 * DEBE pasar por assertClientId() o assertIsolation() antes de ejecutar.
 * Si no se puede garantizar el filtro → no se expone (condición de paro #9).
 *
 * El test anti-IDOR está en PortalAntiIdorTest (en este mismo directorio).
 */
class PortalClientScope
{
    /**
     * Devuelve el client_id del cliente autenticado en el guard 'cliente'.
     * Lanza excepción si no hay sesión activa (nunca debe llamarse sin auth).
     */
    public static function clientId(): int
    {
        $user = Auth::guard('cliente')->user();
        if (! $user || ! $user->client_id) {
            abort(401, 'Sesión de portal no válida.');
        }
        return (int) $user->client_id;
    }

    /**
     * Aplica condición WHERE client_id al query builder.
     * Uso: $query->tap(PortalClientScope::apply());
     */
    public static function apply(): \Closure
    {
        $clientId = self::clientId();
        return fn ($q) => $q->where('client_id', $clientId);
    }

    /**
     * Verifica que un registro pertenece al cliente autenticado.
     * Si no pertenece → abort(404) para evitar fuga de existencia.
     */
    public static function assertOwns(int $recordClientId): void
    {
        if ($recordClientId !== self::clientId()) {
            abort(404);
        }
    }

    /**
     * Verifica aislamiento en una query ya ejecutada (campo polymorphic).
     * Para payments: paymentable_id = client_id.
     */
    public static function assertPolymorphic(int $paymentableId, string $paymentableType): void
    {
        $validTypes = [
            'App\\Models\\Client',
            'App\\Modules\\Core\\Clientes\\Models\\Client',
        ];
        if ($paymentableId !== self::clientId() || ! in_array($paymentableType, $validTypes)) {
            abort(404);
        }
    }
}
