<?php

namespace App\Traits;

use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use RuntimeException;

/**
 * Denormalización de tenancy para los hijos de MegaFamilia.
 *
 * El modelo declara su fuente:
 *     protected array $clientIspFrom = ['account', 'account_id'];   // tier-1
 *     protected array $clientIspFrom = ['profile', 'profile_id'];   // tier-2
 *     protected array $clientIspFrom = ['device',  'device_id'];    // tier-2 (locations)
 * = [nombre de la relación belongsTo, columna FK del padre].
 *
 * - `creating`: si `client_isp_id` ya viene seteado, lo respeta (idempotente). Si no,
 *   lo deriva del padre. Fallback transitivo: si el padre inmediato lo tiene NULL
 *   (fila vieja sin backfill), sube por `account_id` hasta `parental_accounts`.
 *   Si aun así no resuelve → excepción (FAIL-CLOSED de escritura), NUNCA inserta null.
 * - `updating`: el tenant es INMUTABLE — cambiar `client_isp_id` a un valor distinto
 *   del derivado del padre lanza excepción.
 *
 * Complementa (no reemplaza) a BelongsToClientTenant, que provee la lectura
 * scopeForClient(). Este trait solo puebla/protege la columna en escritura.
 */
trait DerivesClientIspId
{
    public static function bootDerivesClientIspId(): void
    {
        static::creating(function ($model) {
            if ($model->getAttribute('client_isp_id') !== null) {
                return; // idempotente: ya viene resuelto
            }
            $derived = $model->deriveClientIspId();
            if ($derived === null) {
                throw new RuntimeException(sprintf(
                    '[DerivesClientIspId] No se pudo derivar client_isp_id para %s (fk %s=%s). Escritura fail-closed.',
                    static::class,
                    $model->clientIspFrom[1] ?? '?',
                    var_export($model->getAttribute($model->clientIspFrom[1] ?? ''), true)
                ));
            }
            $model->setAttribute('client_isp_id', $derived);
        });

        static::updating(function ($model) {
            if (! $model->isDirty('client_isp_id')) {
                return;
            }
            $derived = $model->deriveClientIspId();
            if ($derived === null || (int) $model->getAttribute('client_isp_id') !== $derived) {
                throw new RuntimeException(sprintf(
                    '[DerivesClientIspId] client_isp_id es inmutable en %s#%s (intento %s, derivado %s).',
                    static::class,
                    $model->getKey(),
                    var_export($model->getAttribute('client_isp_id'), true),
                    var_export($derived, true)
                ));
            }
        });
    }

    /**
     * Deriva client_isp_id del padre. Paso 1: columna directa del padre (la cuenta la
     * tiene; profile/device la tienen tras el backfill). Paso 2 (fallback transitivo):
     * el padre cuelga de una cuenta vía account_id → parental_accounts.client_isp_id.
     */
    public function deriveClientIspId(): ?int
    {
        [$relation, $fk] = $this->clientIspFrom;

        $parentId = $this->getAttribute($fk);
        if ($parentId === null) {
            return null;
        }

        $parent = $this->{$relation}()->getRelated()->newQuery()->whereKey($parentId)->first();
        if (! $parent) {
            return null;
        }

        // Paso 1: columna directa del padre.
        $direct = $parent->getAttribute('client_isp_id');
        if ($direct !== null) {
            return (int) $direct;
        }

        // Paso 2: fallback transitivo hasta la cuenta.
        $accountId = $parent->getAttribute('account_id');
        if ($accountId !== null) {
            $cisp = ParentalAccount::whereKey($accountId)->value('client_isp_id');
            if ($cisp !== null) {
                return (int) $cisp;
            }
        }

        // El padre ya ES la cuenta (tier-1): su client_isp_id se leyó en el paso 1.
        return null;
    }
}
