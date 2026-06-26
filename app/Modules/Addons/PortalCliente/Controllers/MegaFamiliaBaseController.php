<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use App\Services\Tenant\CurrentClientResolver;
use Illuminate\Support\Collection;

/**
 * Base de los controllers de ESCRITURA de MegaFamilia en el portal de cliente.
 *
 * Centraliza la REGLA DE SEGURIDAD ABSOLUTA (la misma en cada write de G1–G7):
 *   1. clientId() — CurrentClientResolver::resolve(), fail-closed (403 si null).
 *   2. accounts() — ParentalAccount::forClient($clientId), 403 si el cliente no
 *      tiene cuenta MegaFamilia. forClient es fail-closed (whereRaw('1=0') si null).
 *   3. requireProfile()/requireDevice() — el recurso hijo SOLO se resuelve si su
 *      account_id ∈ las cuentas del cliente; cualquier id ajeno → abort(403).
 *
 * Nunca se confía en el input: el ownership se verifica contra la BD, no contra
 * el account_id/profile_id que venga del request.
 */
abstract class MegaFamiliaBaseController extends Controller
{
    private ?Collection $accountsCache = null;

    /** client_id del cliente autenticado. Fail-closed: 403 si no se resuelve. */
    protected function clientId(): int
    {
        $id = app(CurrentClientResolver::class)->resolve();
        abort_if($id === null, 403, 'No se pudo resolver el cliente.');

        return $id;
    }

    /** Cuentas MegaFamilia del cliente (scope forClient). 403 si no hay ninguna. */
    protected function accounts(): Collection
    {
        if ($this->accountsCache === null) {
            $this->accountsCache = ParentalAccount::forClient($this->clientId())->get();
            abort_if($this->accountsCache->isEmpty(), 403, 'No tienes una cuenta MegaFamilia activa.');
        }

        return $this->accountsCache;
    }

    /** IDs de las cuentas del cliente — la ÚNICA puerta a los recursos hijos. */
    protected function accountIds(): array
    {
        return $this->accounts()->pluck('id')->all();
    }

    /**
     * Una cuenta del cliente. Si se pasa $id, debe pertenecerle (403 si no);
     * sin $id devuelve la primera (caso típico: un cliente = una cuenta).
     */
    protected function requireAccount(?int $id = null): ParentalAccount
    {
        $accounts = $this->accounts();

        if ($id !== null) {
            $account = $accounts->firstWhere('id', $id);
            abort_if(! $account, 403, 'Esa cuenta no te pertenece.');

            return $account;
        }

        return $accounts->first();
    }

    /** Perfil que cuelga de una cuenta del cliente; cualquier id ajeno → 403. */
    protected function requireProfile(int $id): ParentalProfile
    {
        $profile = ParentalProfile::whereIn('account_id', $this->accountIds())
            ->whereKey($id)
            ->first();
        abort_if(! $profile, 403, 'Ese perfil no te pertenece.');

        return $profile;
    }

    /** Dispositivo que cuelga de una cuenta del cliente; cualquier id ajeno → 403. */
    protected function requireDevice(int $id): ParentalDevice
    {
        $device = ParentalDevice::whereIn('account_id', $this->accountIds())
            ->whereKey($id)
            ->first();
        abort_if(! $device, 403, 'Ese dispositivo no te pertenece.');

        return $device;
    }
}
