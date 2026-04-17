<?php

namespace App\Services\ClientService;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Controllers\Utils\UtilController;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\PaymentRepository;
use App\Http\Repository\TransactionRepository;
use App\Models\Card;
use App\Models\Client;
use App\Models\TypeBilling;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BillingExpirationService
{
    protected $client;
    protected $tienePeriodoGracia = false;

    public function __construct(Client $client = null)
    {
        $this->client = $client;
    }

    public function setNewFechaCorteForClient($fecha = null, $cantidadDeDiasOmesesAMultiplicarLaFechaSegunEltipoDeBilling = 1, $fechaCorteAnterior = true, $tienePeriodoGracia = false)
    {
        $this->tienePeriodoGracia = $tienePeriodoGracia;
        $clientRepository = new ClientRepository();
        if ($fecha) {
            $clientRepository->setFechaCorte($this->client, $fecha);
            return $fecha;
        }

        $fechaCorte = false;
        if ($fechaCorteAnterior) {
            $fechaCorte = $this->client->fecha_corte;
        }

        $fecha = $this->getBillingExpirationByTypeOfBillingByFechaCorte($fechaCorte, $cantidadDeDiasOmesesAMultiplicarLaFechaSegunEltipoDeBilling);
        $clientRepository->setFechaCorte($this->client, $fecha);
        return $fecha;
    }

    private function getBillingExpirationByTypeOfBillingByFechaCorte($fechaCorteAnterior, $cantidadDeDiasOmesesAMultiplicarLaFechaSegunEltipoDeBilling)
    {
        $clientRepository = new ClientRepository();
        $typeOfBilling = $clientRepository->getTypeOfBilling($this->client);
        if ($fecha = $this->getFechaCorteForBillingPrepaidRecurrent($typeOfBilling, $fechaCorteAnterior, $cantidadDeDiasOmesesAMultiplicarLaFechaSegunEltipoDeBilling)) {
            return $fecha;
        }

        if ($fecha = $this->getFechaCorteForBillingPrepaidCustom($typeOfBilling, $fechaCorteAnterior, $cantidadDeDiasOmesesAMultiplicarLaFechaSegunEltipoDeBilling)) {
            return $fecha;
        }

        if ($fecha = $this->getFechaCorteForBillingDaily($typeOfBilling, $fechaCorteAnterior, $cantidadDeDiasOmesesAMultiplicarLaFechaSegunEltipoDeBilling)) {
            return $fecha;
        }
    }

    private function getFechaCorteForBillingPrepaidRecurrent($typeOfBilling, $fechaCorteAnterior, $billingMultiplier)
    {
        if ($typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT) {
            $billingConfiguration = $this->client->billing_configuration;
            $billingDate = $billingConfiguration->billing_date;
            $billingExpiration = $billingConfiguration->billing_expiration;

            if (!$fechaCorteAnterior) {
                if ($this->tienePeriodoGracia) {
                    if ($this->fechaDelUltimoCobroDeLosServiciosEsEquivalenteAlDiaDeHoy($billingDate)) {
                        $fechaCompleta = $this->adjustBillingDate($billingMultiplier, $billingDate, $billingExpiration);
                    } else {
                        $billingMultiplier--;
                        if ($billingMultiplier === 0) {
                            $month = Carbon::now()->startOfMonth();
                            $billingDate = min($billingDate, $month->daysInMonth);
                            $fechaCompleta = $month->addDays($billingDate - 1)
                                ->addDays($billingExpiration)
                                ->endOfDay()
                                ->toDateTimeString();
                        } else {
                            $fechaCompleta = $this->adjustBillingDate($billingMultiplier, $billingDate, $billingExpiration);
                        }
                    }
                    return $fechaCompleta;
                }

                $month = Carbon::now()->addMonthsWithoutOverflow($billingMultiplier)->startOfMonth();
                if ($billingDate > $month->daysInMonth) {
                    $billingDate = $month->daysInMonth;
                }
                return $month->addDays($billingDate - 1)->addDays($billingMultiplier)->endOfDay()->toDateTimeString();
            }

            //  Si ya tiene fecha de pago solo se debe agregar los dias del billing_expiration;
            $fechaPago = $this->client->fecha_pago;
            return Carbon::parse($fechaPago)->addDays($billingExpiration)->endOfDay()->toDateTimeString();
        }
        return null;
    }

    private function adjustBillingDate($billingMultiplier, $billingDate, $billingExpiration)
    {
        $month = Carbon::now()->addMonthsWithoutOverflow($billingMultiplier)->startOfMonth();
        $billingDate = min($billingDate, $month->daysInMonth);
        return $month->addDays($billingDate - 1) // Añade días al inicio del mes
            ->addDays($billingExpiration) // Añade la expiración configurada
            ->endOfDay() // Finaliza el día
            ->toDateTimeString(); // Convierte a string completo
    }

    private function getFechaCorteForBillingPrepaidCustom(mixed $typeOfBilling, $fechaCorteAnterior, $cantidadDeDiasOmesesAMultiplicarLaFechaSegunEltipoDeBilling)
    {
        if ($typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_CUSTOM) {
            return Carbon::parse($fechaCorteAnterior)->addMonthsWithoutOverflow($cantidadDeDiasOmesesAMultiplicarLaFechaSegunEltipoDeBilling)->endOfDay()->toDateTimeString();
        }
        return null;
    }

    private function getFechaCorteForBillingDaily(mixed $typeOfBilling, $fechaCorteAnterior, $cantidadDeDiasOmesesAMultiplicarLaFechaSegunEltipoDeBilling)
    {
        if ($typeOfBilling == TypeBilling::TYPE_OF_BILLING_PREPAID_DAILY) {
            return Carbon::parse($fechaCorteAnterior)->addDays($cantidadDeDiasOmesesAMultiplicarLaFechaSegunEltipoDeBilling)->endOfDay()->toDateTimeString();
        }
        return null;
    }

    private function fechaDelUltimoCobroDeLosServiciosEsEquivalenteAlDiaDeHoy($billingDate = false)
    {
        $clientRepository = new ClientRepository();
        $fechaDelUltimoCobroDeServicios = $clientRepository->obtenerLaFechaDelUltimoCobroDeLosServicios($this->client);
        if (!$fechaDelUltimoCobroDeServicios) return false;

        $fechaDelUltimoCobroDeServicios = Carbon::parse($fechaDelUltimoCobroDeServicios);

        // significa
        if ($billingDate !== false) {
            //TODO si el dia de la fecha del ultimo cobro de los servicios es menor al dia de la fecha de facturación
                                 //1                       //31
            if ($fechaDelUltimoCobroDeServicios->day < $billingDate) {
                return false;
            }
        }

        return $fechaDelUltimoCobroDeServicios->isSameMonth();
    }
}
