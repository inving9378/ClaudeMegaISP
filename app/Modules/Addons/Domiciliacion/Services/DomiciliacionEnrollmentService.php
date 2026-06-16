<?php

namespace App\Modules\Addons\Domiciliacion\Services;

use App\Modules\Addons\Domiciliacion\Models\ClientRecurringCard;
use App\Modules\Addons\PortalCliente\Services\OpenpayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DomiciliacionEnrollmentService
{
    public function __construct(private OpenpayService $openpay) {}

    /**
     * Enrolls a card for a client. PAN is never received here —
     * the browser tokenizes it via openpay.js; only the token arrives.
     *
     * Any previously active card is cancelled before saving the new one.
     *
     * @param  int    $clientId
     * @param  string $token         openpay.js browser token
     * @param  string $channel       'portal'|'app'|'admin'|'liga'
     * @param  int    $createdBy     user_id (0 for portal/liga self-enrollment)
     * @param  array  $customerData  ['name','last_name','email','phone_number']
     * @return ClientRecurringCard
     */
    public function enrolar(
        int    $clientId,
        string $token,
        string $channel,
        int    $createdBy,
        array  $customerData
    ): ClientRecurringCard {
        $this->cancelarTarjetasActivas($clientId);

        $customerId = $this->openpay->crearClienteOpenpay($customerData);
        $card       = $this->openpay->guardarTarjeta($customerId, $token);

        return DB::transaction(function () use ($clientId, $customerId, $card, $channel, $createdBy) {
            return ClientRecurringCard::create([
                'client_id'           => $clientId,
                'openpay_customer_id' => $customerId,
                'openpay_card_id'     => $card->id,
                'card_brand'          => $card->brand ?? null,
                'card_last4'          => $card->card_number ?? null,  // OpenPay returns last 4 digits in this field
                'card_exp'            => ($card->expiration_month ?? '') . '/' . ($card->expiration_year ?? ''),
                'cardholder'          => $card->holder_name ?? null,
                'status'              => 'active',
                'consent_at'          => now(),
                'consent_channel'     => $channel,
                'created_by'          => $createdBy,
            ]);
        });
    }

    /**
     * Cancels a specific card. Scope-checks client_id to prevent IDOR.
     */
    public function cancelar(int $cardId, int $clientId): void
    {
        $card = ClientRecurringCard::where('id', $cardId)
            ->where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        try {
            $this->openpay->eliminarTarjeta($card->openpay_customer_id, $card->openpay_card_id);
        } catch (\Throwable $e) {
            Log::warning('domiciliacion.cancelar: error eliminando tarjeta en OpenPay', [
                'card_id' => $cardId,
                'error'   => $e->getMessage(),
            ]);
        }

        $card->update(['status' => 'cancelled']);
        $card->delete();
    }

    /**
     * Cancels all active cards for a client before enrolling a new one.
     * OpenPay cleanup is best-effort; local records are always updated.
     */
    private function cancelarTarjetasActivas(int $clientId): void
    {
        ClientRecurringCard::forClient($clientId)->active()->get()
            ->each(function (ClientRecurringCard $card) {
                try {
                    $this->openpay->eliminarTarjeta($card->openpay_customer_id, $card->openpay_card_id);
                } catch (\Throwable) {
                    // silencioso: si OpenPay falla, igual cerramos localmente
                }
                $card->update(['status' => 'cancelled']);
                $card->delete();
            });
    }
}
