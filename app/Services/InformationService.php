<?php

namespace App\Services;

use App\Http\Repository\ClientRepository;
use App\Http\Repository\CommandConfigRepository;
use App\Http\Traits\RouterConnection;
use App\Models\Client;
use App\Models\ClientBundleService;
use App\Models\ClientInternetService;
use App\Models\TypeBilling;
use App\Models\Router;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InformationService
{
    use RouterConnection;

    protected ClientRepository $clientRepository;
    protected CommandConfigRepository $commandConfigRepository;

    public function __construct(ClientRepository $clientRepository, CommandConfigRepository $commandConfigRepository)
    {
        $this->clientRepository = $clientRepository;
        $this->commandConfigRepository = $commandConfigRepository;
    }

    /**
     * Devuelve toda la información de clientes lista para mostrar en pantalla.
     */
    public function getInformation(): array
    {
        set_time_limit(0);
        ini_set('memory_limit', '8912M');

        $rules = [
           // 'Clientes que pagaron en despues del 10 de febrebro de 2026 y antes del dia 1/3/2026 , su billing_date es mayor a 27 y su fecha de corte es el dia 1/3/2026' => fn() => $this->getClientsWithPaymentDateAfterFeb2026AndBillingDateGreaterThan27AndCorteDateOnMarch1st2026()->get(),


            'Clientes activos sin periodo de gracia y fecha corte menor a hoy' => fn() => $this->clientsWithoutGracePeriod()->whereDate('fecha_corte', '<', Carbon::today())->get(),

            'Clientes activos sin periodo de gracia y llevan más de un mes sin pagar (recurrente)' => fn() => $this->getClientsWithoutPayment('recurrent')->get(),

            'Clientes activos sin periodo de gracia y llevan más de un mes sin pagar (no recurrente)' => fn() => $this->getClientsWithoutPayment('not_recurrent')->get(),

            'Clientes activos con periodo de gracia y balance positivo' => fn() => $this->clientsWithGracePeriod()->positiveBalance()->get(),

            'Clientes activos con periodo de gracia y balance negativo' => fn() => $this->clientsWithGracePeriod()->negativeBalance()->get(),

            'Clientes activos con periodo de gracia y fecha corte menor a hoy' => fn() => $this->clientsWithGracePeriod()->whereDate('fecha_corte', '<', Carbon::today())->get(),

            'Clientes activos con periodo de gracia y fecha corte mayor a hoy más 3 días' => fn() => $this->clientsWithGracePeriod()->where('fecha_corte', '>', DB::raw('DATE_ADD(fecha_pago, INTERVAL 3 DAY)'))->get(),

            'Clientes activos que no tienen fecha de corte' => fn() => $this->clientsWithoutGracePeriod()->whereNull('fecha_corte')->get(),

            'Clientes activos que no tienen fecha de pago' => fn() => $this->clientsWithoutGracePeriod()->whereNull('fecha_pago')->get(),

            'Clientes Internet Services sin IP' => fn() => $this->getClientsWhereHasInternetServiceAndNotIp(true),

            'Clientes con IP y sin servicios de Internet' => fn() => $this->getClientsWhereHasIpAndNotInternetService(true),

            'Clientes con servicios de Internet pero paquete no existe' => fn() => $this->getClientesConServiciosDeInternetQuePertenecenAunPaqueteYelPaqueteNoExiste(true),

            'Clientes con bundle pero sin servicios de Internet' => fn() => $this->getClientsWithBundleServiceWhereDoesntHaveInternet(true),

            'Clientes activos que no pagan hace más de 4 meses y no tienen plan de administración' => fn() => $this->getClientsActiveQueNoPaganHaceMasdeTresMesesYQueNoTenganPlanAdministracion()->get(),

            'Clientes recurrentes con saldo negativo y sin periodo de gracia' => fn() => $this->getClientesQueSeanRecurrentesEstenENNegativoYNoTengaPeriodoDeGracia()->get(),

            'Clientes con servicios de Internet administración' => fn() => $this->getClientesConServicioDeInternetAdministracion()->get(),

            'Clientes con pagos en diciembre 2025 o enero 2026 y facturas con periodo > 2026-01' => fn() => $this->getClientsWithInvoicesAfterJan2026(),
            'Clientes Recurrentes con periodos de facturas mal' => fn() => $this->getClientsWithInvoicesWithWrongPeriod(),
            'Clientes Recurrentes con facturas duplicados para el mismo periodo' => fn() => $this->getClientsWithInvoicesDuplicatesPeriod(),

        ];

        $data = [];
        foreach ($rules as $label => $callback) {
            $data[$label] = $callback();
        }

        return $data;
    }

    /* ===================== Métodos base ===================== */

    protected function clientsActiveQuery()
    {
        return Client::active()->with(['network_ip', 'client_main_information', 'internet_service', 'bundle_service', 'balance']);
    }

    protected function clientsWithGracePeriod()
    {
        return $this->clientsActiveQuery()->activeGracePeriod();
    }

    protected function clientsWithoutGracePeriod()
    {
        return $this->clientsActiveQuery()->notActiveGracePeriod();
    }

    protected function getClientsWithoutPayment(string $type = 'recurrent')
    {
        $query = $this->clientsWithoutGracePeriod()
            ->whereDoesntHave('payments', function ($q) use ($type) {
                $q->whereDate('created_at', '>', Carbon::now()->subMonth()->subDays(3)->toDateString());
            })
            ->whereDate('fecha_corte', '<', Carbon::today())
            ->noTenganPlanAdministrador();

        if ($type === 'recurrent') {
            $query->typeBillingRecurrent();
        } else {
            $query->typeBillingNotRecurrent();
        }

        return $query;
    }

    /* ===================== Métodos de chequeo específicos ===================== */

    public function getClientsActiveQueNoPaganHaceMasdeTresMesesYQueNoTenganPlanAdministracion()
    {
        return $this->clientsActiveQuery()
            ->whereDoesntHave('payments', function ($query) {
                $query->whereDate('created_at', '>', Carbon::now()->subMonths(4)->toDateString());
            })
            ->noTenganPlanAdministrador()
            ->whereNull('fecha_corte')
            ->whereNull('fecha_pago');
    }

    public function getClientesConServicioDeInternetAdministracion()
    {
        return Client::whereNull('fecha_corte')
            ->tenganPlanAdministrador();
    }

    public function getClientesQueSeanRecurrentesEstenENNegativoYNoTengaPeriodoDeGracia()
    {
        return $this->clientsWithoutGracePeriod()
            ->whereHas('client_main_information', fn($q) => $q->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT)
            )->whereHas('balance', fn($q) => $q->where('amount', '<', 0)
            );
    }

    public function getClientsWhereHasInternetServiceAndNotIp(bool $full = false)
    {
        $query = Client::with(['network_ip', 'client_main_information', 'internet_service', 'payments', 'transactions'])
            ->whereHas('client_main_information')
            ->whereHas('internet_service')
            ->whereDoesntHave('network_ip');

        return $full ? $query->get() : $query->pluck('id')->toArray();
    }

    public function getClientsWhereHasIpAndNotInternetService(bool $full = false)
    {
        $query = Client::with(['network_ip', 'client_main_information', 'internet_service', 'payments', 'transactions'])
            ->whereHas('client_main_information')
            ->whereHas('network_ip')
            ->whereDoesntHave('internet_service');

        return $full ? $query->get() : $query->pluck('id')->toArray();
    }

    public function getClientsWithBundleServiceWhereDoesntHaveInternet(bool $full = false)
    {
        $query = Client::with(['network_ip', 'client_main_information', 'bundle_service'])
            ->whereHas('client_main_information')
            ->whereHas('bundle_service')
            ->whereDoesntHave('internet_service');

        return $full ? $query->get() : $query->pluck('id')->toArray();
    }

    public function getClientesConServiciosDeInternetQuePertenecenAunPaqueteYelPaqueteNoExiste(bool $full = false)
    {
        $internetServices = ClientInternetService::whereNotNull('client_bundle_service_id')
            ->with('bundle_service')
            ->get();

        $clients = $internetServices->filter(fn($s) => !$s->bundle_service);

        return $full ? Client::whereIn('id', $clients->pluck('client_id'))->get() : $clients->pluck('client_id')->toArray();
    }

    /* ===================== IP duplicadas ===================== */

    public function getIpsDuplicadasEnSecrets(): array
    {
        $router = Router::find(2);
        $connectionRouter = $this->getConnectionByRouter($router);
        $allIpsInAddressList = $this->getAllPppSecretsIps($connectionRouter);

        $contadorIps = array_count_values($allIpsInAddressList);

        return array_keys(array_filter($contadorIps, fn($count) => $count > 1));
    }

    /**
     * Clientes con pagos en diciembre 2025 o enero 2026 y facturas con periodo > 2026-01
     */
    public function getClientsWithInvoicesAfterJan2026()
    {
        $startDate = Carbon::create(2025, 12, 1)->startOfDay();
        $endDate = Carbon::create(2026, 1, 31)->endOfDay();

        $clients = Client::whereHas('payments', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate])
                ->with('invoices'); // Cargamos todas las facturas
        })->with(['payments' => function ($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate])
                ->with('invoices');
        }])->get();

        $result = [];

        foreach ($clients as $client) {
            $relevantPayments = [];

            foreach ($client->payments as $payment) {
                $relevantInvoices = [];

                foreach ($payment->invoices as $invoice) {
                    if ($invoice->period > '2026-01') {
                        $relevantInvoices[] = [
                            'invoice_id' => $invoice->id,
                            'invoice_period' => $invoice->period,
                            'invoice_amount' => $invoice->amount ?? 0,
                        ];
                    }
                }

                if (count($relevantInvoices) > 0) {
                    $relevantPayments[] = [
                        'payment_id' => $payment->id,
                        'payment_date' => $payment->created_at->toDateString(),
                        'invoices' => $relevantInvoices,
                    ];
                }
            }

            if (count($relevantPayments) > 0) {
                $result[] = [
                    'client_id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->client_main_information->email ?? '',
                    'payments' => $relevantPayments,
                ];
            }
        }

        return $result;
    }

    private function getClientsWithInvoicesWithWrongPeriod()
    {
        $clients = Client::whereHas('payments')
            ->whereHas('client_main_information', fn($q) => $q->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT)
            )
            ->with(['payments.invoices'])
            ->get();

        $clientsWithWrongPeriod = [];
        $startPeriod = Carbon::createFromFormat('Y-m', '2025-10'); // periodos desde octubre 2025

        foreach ($clients as $client) {
            $allPeriods = [];

            foreach ($client->payments as $payment) {
                foreach ($payment->invoices as $invoice) {
                    $invoiceDate = Carbon::createFromFormat('Y-m', $invoice->period);
                    if ($invoiceDate >= $startPeriod) {
                        $allPeriods[] = $invoice->period; // solo a partir de 2025-10
                    }
                }
            }

            if (empty($allPeriods)) {
                continue; // este cliente no tiene facturas desde 2025-10
            }

            // Ordenamos los periodos
            sort($allPeriods);

            // Revisamos periodos consecutivos
            $previous = null;
            $hasGap = false;

            foreach ($allPeriods as $period) {
                if ($previous) {
                    $prevDate = Carbon::createFromFormat('Y-m', $previous);
                    $currentDate = Carbon::createFromFormat('Y-m', $period);
                    $diff = $prevDate->diffInMonths($currentDate);

                    if ($diff != 1) { // hay un salto
                        $hasGap = true;
                        break;
                    }
                }
                $previous = $period;
            }

            if ($hasGap) {
                $clientsWithWrongPeriod[] = $client;
            }
        }

        return $clientsWithWrongPeriod;
    }

    private function getClientsWithInvoicesDuplicatesPeriod()
    {
        // Traemos los clientes con pagos y facturas
        $clients = Client::whereHas('payments')
            ->whereHas('client_main_information', fn($q) => $q->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT)
            )
            ->with(['payments.invoices'])
            ->get();

        $clientsWithDuplicates = [];

        foreach ($clients as $client) {
            $allPeriods = [];

            // Recorremos todas las facturas de todos los pagos
            foreach ($client->payments as $payment) {
                foreach ($payment->invoices as $invoice) {
                    $allPeriods[] = $invoice->period;
                }
            }

            // Contamos cuántas veces aparece cada periodo
            $periodCounts = array_count_values($allPeriods);

            // Filtramos los que se repiten
            $duplicates = array_filter($periodCounts, fn($count) => $count > 1);

            if (!empty($duplicates)) {
                // Guardamos el cliente y los periodos duplicados
                $clientsWithDuplicates[] = $client;
            }
        }

        return $clientsWithDuplicates;
    }

    public function getClientsWithPaymentDateAfterFeb2026AndBillingDateGreaterThan27AndCorteDateOnMarch1st2026()
    {
        return Client::whereHas('payments', function ($q) {
            // Pagos después del 10 de febrero y antes del 1 de marzo de 2026
            $q->whereDate('created_at', '>', '2026-02-10');
        })
            ->whereHas('billing_configuration', function ($q) {
//                // billing_date mayor a 27
//                $q->where('billing_date', '>', 27);
            })
            ->whereHas('client_main_information', function ($q) {
                // Asegurar que sean recurrentes (según tu lógica previa)
                $q->where('type_of_billing_id', TypeBilling::TYPE_OF_BILLING_PREPAID_RECURRENT);
            })
            ->whereNull('fecha_corte')
            ->orWhere('fecha_corte', '2026-03-01')
            // Cargamos las relaciones para que al mostrar la info no haga consultas extra (N+1)
            ->with(['payments', 'client_main_information', 'billing_configuration', 'balance']);
    }


}
