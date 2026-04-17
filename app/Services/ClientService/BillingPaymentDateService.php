<?php

namespace App\Services\ClientService;

use App\Http\Controllers\Utils\UtilController;
use App\Http\Repository\ClientRepository;
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
