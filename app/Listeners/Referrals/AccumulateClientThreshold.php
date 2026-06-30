<?php

namespace App\Listeners\Referrals;

use App\Events\InvoicePaid;
use App\Events\Referrals\EmbajadorActivated;
use App\Models\Client;
use App\Modules\Core\Clientes\Models\Client as ClientModel;
use App\Models\Referrals\ClientReferralProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AccumulateClientThreshold implements ShouldQueue
{
    public string $queue  = 'referrals';
    public int    $tries  = 3;
    public array  $backoff = [60, 300, 900];

    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;
        $amount  = (float) $invoice->total;

        if ($amount <= 0) {
            return;
        }

        $client = Client::find($invoice->client_id);
        if (!$client) {
            return;
        }

        $profile = ClientReferralProfile::where('client_id', $client->id)->first();

        if (!$profile) {
            $client->loadMissing('client_main_information');
            $code    = ClientReferralProfile::generateUniqueCode($client);
            $profile = ClientReferralProfile::create([
                'client_id'             => $client->id,
                'referral_code'         => $code,
                'referral_link'         => url('/registro?ref=' . $code),
                'plan_type'             => null,
                'is_eligible'           => false,
                'threshold_amount_paid' => 0,
            ]);
        }

        // Ya activado — no seguir acumulando
        if ($profile->is_eligible) {
            return;
        }

        $profile->threshold_amount_paid = (float) $profile->threshold_amount_paid + $amount;

        $precioMensual = $this->getPrecioMensual($client);
        $umbralPlan    = $precioMensual * 3;
        $umbralFijo    = 1500.0;
        $umbralFinal   = min($umbralPlan, $umbralFijo);

        $cruzaUmbral  = $profile->threshold_amount_paid >= $umbralFinal;
        $mesesActivo  = $client->created_at ? (int) $client->created_at->diffInMonths(now()) : 0;
        $cumpleTiempo = $mesesActivo >= 3;

        if ($cruzaUmbral || $cumpleTiempo) {
            $profile->is_eligible  = true;
            $profile->activated_at = now();
            $profile->save();

            Log::info('AccumulateClientThreshold: cliente activado como embajador', [
                'client_id'             => $client->id,
                'threshold_amount_paid' => $profile->threshold_amount_paid,
                'meses_activo'          => $mesesActivo,
                'razon'                 => $cruzaUmbral
                    ? "umbral_plan:{$precioMensual}x3={$umbralFinal}"
                    : "tiempo:{$mesesActivo}meses",
                'referral_code'         => $profile->referral_code,
            ]);

            event(new EmbajadorActivated($profile));
        } else {
            $profile->save();
        }
    }

    private function getPrecioMensual(ClientModel $client): float
    {
        $servicio = $client->internet_service()
            ->where('estado', 'Activado')
            ->with('internet')
            ->first();

        if (!$servicio) {
            return 400.0;
        }

        $precio = $servicio->price > 0
            ? (float) $servicio->price
            : (float) $servicio->internet->price;

        if (
            isset($servicio->internet->tax_include, $servicio->internet->tax) &&
            !$servicio->internet->tax_include &&
            $servicio->internet->tax > 0
        ) {
            $precio = $precio + ($precio * $servicio->internet->tax / 100);
        }

        return $precio;
    }

    public function failed(InvoicePaid $event, \Throwable $exception): void
    {
        Log::error('AccumulateClientThreshold: falló tras retries', [
            'invoice_id' => $event->invoice->id,
            'client_id'  => $event->invoice->client_id,
            'error'      => $exception->getMessage(),
        ]);
    }
}
