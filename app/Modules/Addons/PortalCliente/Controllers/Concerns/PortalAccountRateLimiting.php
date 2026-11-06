<?php

namespace App\Modules\Addons\PortalCliente\Controllers\Concerns;

use App\Modules\Addons\PortalCliente\Models\PortalClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * #256: límite POR CUENTA (independiente de la IP) que comparten los flujos públicos de acceso
 * del Portal Cliente: login, registro, recuperar y recuperar con código (#473).
 */
trait PortalAccountRateLimiting
{
    protected function normalizeTel(?string $tel): string
    {
        if (! $tel) return '';
        return preg_replace('/[^0-9]/', '', $tel);
    }

    /**
     * #256 FASE 1: llave del limitador POR CUENTA (independiente de la IP), keyed por el id
     * ya resuelto del cliente (así variantes de formato del mismo identificador comparten
     * contador) o, si no resolvió a ninguna cuenta, por el identificador crudo normalizado.
     */
    protected function accountRateLimitKey(string $prefix, ?PortalClient $cmi, string $rawIdentifier): string
    {
        $identity = $cmi ? 'id:' . $cmi->id : 'raw:' . mb_strtolower(trim($rawIdentifier));
        return $prefix . ':' . $identity;
    }

    /**
     * #256 FASE 1: ventana fija de 15 min (900s) por cuenta — umbral 10 intentos (recomendación
     * de Irving en el item: "10 fallos/15min por cuenta"). Se auto-expira sola (no es lockout
     * duro/permanente: no requiere intervención de un admin para desbloquear).
     * #256 FASE 0: deja rastro en log para medir el patrón real de fallos por cuenta.
     */
    protected function hitAccountRateLimiter(string $key, string $contexto): void
    {
        $attempts = RateLimiter::hit($key, 900);

        Log::warning('portal-auth: fallo con rate-limit por cuenta', [
            'contexto' => $contexto,
            'key'      => $key,
            'attempts' => $attempts,
        ]);
    }
}
