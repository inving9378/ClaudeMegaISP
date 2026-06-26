<?php

namespace App\Services\MegaFamilia;

use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalPlan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Única fuente de activación/creación de parental_accounts (MegaFamilia).
 *
 * Antes la activación era un raw insert en PortalCliente\MarketplaceController que
 * bypassaba los observers y funcionaba solo porque seteaba client_isp_id inline.
 * Este Service centraliza esa escritura sobre Eloquent y la fundación de tenancy.
 *
 * TENANCY: parental_accounts es la RAÍZ del árbol de tenancy. NO usa el observer
 * DerivesClientIspId (ese deriva el tenant en los HIJOS a partir de la cuenta). Por
 * eso el guardrail de la cuenta es ESTE Service: client_isp_id SIEMPRE poblado; si
 * llega null/0 se lanza excepción y NO se crea cuenta huérfana (fail-closed de
 * escritura — era el bug de la fila sin cliente).
 *
 * Idempotente por user_id (replica el comportamiento del portal):
 *   - sin cuenta        → crea (status active, plan Demo, términos aceptados)
 *   - cuenta no-active  → reactiva y la devuelve
 *   - cuenta active     → la devuelve tal cual (no duplica)
 *
 * Pensado para reuso por un futuro gestor de servicios y por admin/API (fuera de
 * alcance de esta corrida): es puro de dominio, no conoce ningún guard.
 */
class ParentalAccountActivationService
{
    /**
     * Activa MegaFamilia para un usuario.
     *
     * @param  int    $userId       users.id del titular de la cuenta.
     * @param  int    $clientIspId  clients.id (raíz de tenancy). Obligatorio > 0.
     * @param  array  $ctx          opcional: terms_ip, terms_version, plan_id.
     * @return ParentalAccount      la cuenta creada o ya existente (idempotente).
     *
     * @throws InvalidArgumentException si $clientIspId no es válido (no crea huérfana).
     */
    public function activate(int $userId, int $clientIspId, array $ctx = []): ParentalAccount
    {
        if ($clientIspId <= 0) {
            throw new InvalidArgumentException(
                "[ParentalAccountActivation] client_isp_id inválido ($clientIspId) para user_id=$userId: "
                . 'no se crea cuenta huérfana (fail-closed de escritura).'
            );
        }

        return DB::transaction(function () use ($userId, $clientIspId, $ctx) {
            $account = ParentalAccount::where('user_id', $userId)->first();

            if ($account) {
                if ($account->status !== 'active') {
                    $account->status = 'active';
                    $account->save();
                }

                return $account;
            }

            $account = new ParentalAccount();
            $account->user_id                = $userId;
            $account->client_isp_id          = $clientIspId;
            $account->plan_id                = $ctx['plan_id'] ?? $this->defaultPlanId();
            $account->status                 = 'active';
            $account->licensed_at            = now();
            $account->terms_accepted_at      = now();
            $account->terms_ip               = $ctx['terms_ip'] ?? null;
            $account->terms_version_accepted = $ctx['terms_version'] ?? 1;
            $account->save();

            return $account;
        });
    }

    /**
     * Suspende la cuenta del usuario (no borra datos). Idempotente.
     */
    public function suspend(int $userId): ?ParentalAccount
    {
        $account = ParentalAccount::where('user_id', $userId)->first();

        if ($account && $account->status !== 'suspended') {
            $account->status = 'suspended';
            $account->save();
        }

        return $account;
    }

    /**
     * Reactiva una cuenta suspendida/cancelada. Idempotente. NO crea cuenta nueva
     * (para eso está activate(), que necesita el client_isp_id).
     */
    public function reactivate(int $userId): ?ParentalAccount
    {
        $account = ParentalAccount::where('user_id', $userId)->first();

        if ($account && $account->status !== 'active') {
            $account->status = 'active';
            $account->save();
        }

        return $account;
    }

    /**
     * Plan Demo por defecto: el primero del catálogo (igual que el flujo anterior).
     */
    private function defaultPlanId(): int
    {
        return (int) (ParentalPlan::query()->orderBy('id')->value('id') ?? 1);
    }
}
