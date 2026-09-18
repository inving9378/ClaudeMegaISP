<?php

namespace App\Modules\Core\Clientes\Services;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Controllers\Utils\UtilController;
use App\Modules\Core\Clientes\Repositories\ClientRepository;
use App\Http\Repository\TransactionRepository;
use App\Models\TypeBilling;
use App\Services\LogService;
use Carbon\Carbon;

class BillingPaymentDateService
{
    public function getNewFechaPagoByClient($client, $cuantasVecesSeLePuedeCobrar = 1, $includeLogs = true)
    {
        $fechaPago = $client->fecha_pago;
        $log = new LogService();

        $restarDia = false;
        if (!$fechaPago) {
            $restarDia = true;
            $fechaPago = Carbon::now();
        }

        $clientRepository = new ClientRepository();
        $typeOfBilling = $clientRepository->getTypeOfBilling($client);
        if ($includeLogs) {
            $log->log($client, 'Cliente #' . $client->id . ' se le puede cobrar ' . $cuantasVecesSeLePuedeCobrar . ' y tiene fecha de pago ' . $fechaPago);
        }

        if ($typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT) {
            $billingConfiguration = $client->billing_configuration;
            $billingDate = $billingConfiguration->billing_date;

            // Pago TARDE (después de fecha_corte, el corte con gracia que ya le
            // tocaba a ESTE ciclo): el nuevo ciclo se ancla al día REAL en que
            // pagó (hoy), no al día de facturación fijo configurado. Sin esto,
            // un cliente que debía pagar el 15 y paga el 20 seguía viendo su
            // próximo corte el 15 del mes siguiente — ~5 días menos de servicio
            // de los que en realidad pagó, sin importar cuántos días de atraso
            // llevara. Pago a tiempo o adelantado: se conserva el comportamiento
            // de siempre (ancla al día de facturación fijo) — no se toca ese
            // caso, no fue lo reportado.
            //
            // OJO: comparar contra fecha_pago (no fecha_corte) hubiera marcado
            // TODO pago como "tarde" — al momento de renovar, "hoy" SIEMPRE es
            // posterior a la fecha_pago del ciclo anterior (por eso se está
            // renovando). fecha_corte ya incluye el billing_expiration (días de
            // gracia) y todavía no se ha recalculado en este punto de la
            // llamada (setNewFechaCorteForClient corre después en
            // ClientBillingService::billingServicesByClient) — es el corte real
            // que aplicaba a ESTE pago.
            //
            // SEGUNDA SEÑAL (2026-09-18, corrige un gap real del fix anterior):
            // fecha_corte NO SIRVE para detectar el caso más común de pago
            // tardío — un cliente RECURRENT que de verdad se suspendió. En ese
            // camino: (1) al bloquearse, el observer pone fecha_corte=null
            // (SuspendService::ifClientChangeToBlockedRemoveDateCorte); (2) si
            // paga dentro del período de gracia, ClientBillingService::
            // billingForce() llama removePeriodoGracia() ANTES de llegar aquí,
            // que vuelve a escribir fecha_corte con un valor NUEVO (calculado
            // desde "hoy", no relacionado con el corte original) — así que para
            // cuando este método corre, fecha_corte ya no es "el corte real que
            // aplicaba a este pago", es un valor recién sobreescrito que casi
            // siempre da $pagoTarde=false aunque el cliente lleve días
            // suspendido. Verificado con datos reales: cliente #17, corte
            // 01-jul, paga 06-jul ya suspendido → con solo fecha_corte el guard
            // daba 30-jul (❌, igual que sin el fix) en vez de 06-ago (✅).
            // Por eso se suma: si el cliente está bloqueado AHORA MISMO (justo
            // antes de que ClientBillingService::cobrarYActivarCliente() lo
            // reactive, que corre DESPUÉS de este método en la misma request),
            // es un hecho que su corte ya pasó — no hace falta saber la fecha
            // exacta del corte viejo para eso, solo que sí pasó.
            $clienteSuspendidoAhora = optional($client->client_main_information)->estado === ComunConstantsController::STATE_BLOCKED;
            $pagoTarde = !$restarDia && (
                ($client->fecha_corte && Carbon::now()->gt(Carbon::parse($client->fecha_corte)))
                || $clienteSuspendidoAhora
            );
            if ($pagoTarde) {
                $newFechaPago = Carbon::now()->addMonthsWithoutOverflow($cuantasVecesSeLePuedeCobrar)->endOfDay()->toDateTimeString();
                if ($includeLogs) {
                    $log->log($client, 'Cliente #' . $client->id . ' pagó tarde (le tocaba ' . $fechaPago . ') — nueva fecha de pago anclada al día real del pago: ' . $newFechaPago);
                }
                return $newFechaPago;
            }

            $month = Carbon::parse($fechaPago)->addMonthsWithoutOverflow($cuantasVecesSeLePuedeCobrar)->startOfMonth();
            if ($billingDate > $month->daysInMonth) {
                $billingDate = $month->daysInMonth;
            }

            $newFechaPago = $month->addDays($billingDate - 1)->endOfDay()->toDateTimeString();
            if ($includeLogs) {
                $log->log($client, 'Cliente #' . $client->id . ' nueva fecha de pago ' . $newFechaPago);
            }
            return $newFechaPago;
        }

        if ($typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM) {
            if ($restarDia) {
                return Carbon::parse($fechaPago)->addMonthsWithoutOverflow($cuantasVecesSeLePuedeCobrar)->subDay()->endOfDay()->toDateTimeString();
            }

            // Mismo fix que PREPAID_RECURRENT (ver comentario arriba): sin esto,
            // un pago tardío sumaba los N meses a la fecha_pago ANTERIOR en vez
            // de anclar al día real del pago — el cliente perdía los días de
            // atraso sin importar cuántos fueran. Pago a tiempo o adelantado:
            // sin cambios.
            $pagoTarde = $client->fecha_corte && Carbon::now()->gt(Carbon::parse($client->fecha_corte));
            if ($pagoTarde) {
                $newFechaPago = Carbon::now()->addMonthsWithoutOverflow($cuantasVecesSeLePuedeCobrar)->endOfDay()->toDateTimeString();
                if ($includeLogs) {
                    $log->log($client, 'Cliente #' . $client->id . ' pagó tarde (le tocaba ' . $fechaPago . ') — nueva fecha de pago anclada al día real del pago: ' . $newFechaPago);
                }
                return $newFechaPago;
            }

            return Carbon::parse($fechaPago)->addMonthsWithoutOverflow($cuantasVecesSeLePuedeCobrar)->endOfDay()->toDateTimeString();
        }

        if ($typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_DAILY) {
            return Carbon::parse($fechaPago)->addDays($cuantasVecesSeLePuedeCobrar)->endOfDay()->toDateTimeString();
        }

        throw new \Exception('El cliente ' . $client->id . ' no tiene typeOfBilling seleccionado.');
    }


    public function setNewPaymentDateWhenBillingDateChange($client, $newBillingDate)
    {
        if ($client->fecha_pago) {
            $fecha_pago = Carbon::parse($client->fecha_pago)->day($newBillingDate)->toDateTimeString();
            $logService = new LogService();
            $logService->log($client, 'Cliente #' . $client->id . ' se establece nueva fecha de pago => Anterior: ' . $client->fecha_pago . ' Actual: ' . $fecha_pago . ' por ' . auth()->user()->name);

            $client->fecha_pago = $fecha_pago;
            $client->save();
        }
    }
}
