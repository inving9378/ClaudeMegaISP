<?php

namespace App\Http\Controllers;

use App\Http\Repository\ClientRepository;
use App\Http\Repository\PaymentRepository;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Ticket;
use App\Models\ClientMainInformation;
use App\Models\Transaction;
use App\Services\FinanceService;
use App\Services\ServerInfoService;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        if (auth()->user()->isClient()) {
            return view('meganet.module.started-page-client', $this->data);
        } else {
            return view('meganet.module.started-page', $this->data);
        }
    }

    public function getHomeStatisticsForTarjetsByStatus()
    {
        $array = [
            "Online" => [
                "estado" => "Cliente en Linea",
                "total" => 0,
                "time_human" => "hace unos segundos",
                "icon" => "fas fa-exchange-alt",
                "link" => "/cliente/listar",
                "porcent" => "0.0",
                "permission" => "dashboard_view_card_client_inline"
            ],
            "ClientNew" => [
                "estado" => "Clientes nuevos",
                "total" => 0,
                "time_human" => "hace unos segundos",
                "icon" => "bx bx-user",
                "link" => "/cliente/listar",
                "porcent" => "0.0",
                "permission" => "dashboard_view_card_client_new"
            ],
            "TicketsOpen" => [
                "estado" => "Tickets nuevos/abiertos",
                "total" => 0,
                "time_human" => "hace unos segundos",
                "icon" => "bx bx-list-check",
                "link" => "/tickets/abiertos",
                "porcent" => "0.0",
                "permission" => "dashboard_view_card_tickets_open_new"
            ],
            "Devices" => [
                "estado" => "Los dispositivos sin respuesta",
                "total" => 0,
                "time_human" => "hace unos segundos",
                "icon" => "bx bx-plug",
                "link" => "/red/router/listar",
                "porcent" => "0.0",
                "permission" => "dashboard_view_card_device_not_responding"
            ]
        ];

        $client_online_info = ClientMainInformation::groupBy('estado')->select('estado', DB::raw('count(*) as total'))->where('estado', 'Online')->get();
        $clientCount = DB::table('client_main_information')->groupBy('estado')->count();

        $client_nuevo_info = ClientMainInformation::groupBy('estado')->select('estado', DB::raw('count(*) as total'))->where('estado', 'Nuevo')->get();

        $ticket_info = Ticket::groupBy('estado')->select('estado', DB::raw('count(*) as total'))->where('estado', 'Nuevo')->get();
        $ticketCount = DB::table('tickets')->groupBy('estado')->count();

        if (isset($client_online_info[0])) {
            $array['Online']['total'] = $client_online_info[0]['total'];
            $array['Online']['porcent'] = $client_online_info[0]['total'] * 100 / $clientCount;
        };

        if (isset($client_nuevo_info[0])) {
            $array['ClientNew']['total'] = $client_nuevo_info[0]['total'];
            $array['ClientNew']['porcent'] = $client_nuevo_info[0]['total'] * 100 / $clientCount;
        };

        if (isset($ticket_info[0])) {
            $array['TicketsOpen']['total'] = $ticket_info[0]['total'];
            $array['TicketsOpen']['porcent'] = $ticket_info[0]['total'] * 100 / $ticketCount;
        };

        return $array;
    }

    public function getStatisticsForTextCardInDashBoard()
    {
        $invoisesCount = ClientInvoice::where('created_at', '>=', \Carbon\Carbon::today()->toDateString())->count();
        $transactionsCount = Transaction::where('created_at', '>=', \Carbon\Carbon::today()->toDateString())->count();
        return ['invoises' => $invoisesCount, 'transactions' => $transactionsCount];
    }

    public function getStatsCardClientInDashBoard()
    {
        $array = [
            "Total" => [
                "estado" => "Total",
                "total" => 0,
                "link" => "listar"
            ],
            "Nuevo" => [
                "estado" => "Nuevo",
                "total" => 0,
                "link" => "#"
            ],
            "Activo" => [
                "estado" => "Activo",
                "total" => 0,
                "link" => "#"
            ],
            "Online" => [
                "estado" => "Online",
                "total" => 0,
                "link" => "#"
            ],
            "En línea hoy" => [
                "estado" => "En línea hoy",
                "total" => 0,
                "link" => "#"
            ],
            "Bloqueado" => [
                "estado" => "Bloqueado",
                "total" => 0,
                "link" => "#"
            ],
            "Inactivo" => [
                "estado" => "Inactivo",
                "total" => 0,
                "link" => "#"
            ],
            "Añadido ultimo mes" => [
                "estado" => "Añadido ultimo mes",
                "total" => 0,
                "link" => "#"
            ],
            "Añadido ultimo año" => [
                "estado" => "Añadido ultimo año",
                "total" => 0,
                "link" => "#"
            ],
            "Toca cobrarle hoy" => [
                "estado" => 'Toca cobrarle hoy',
                "total" => 0,
                "link" => "payment_today"
            ],
            "Toca suspender hoy" => [
                "estado" => 'Toca suspender hoy',
                "total" => 0,
                "link" => "suspend_today"
            ]
        ];

        $client_status_infos = ClientMainInformation::groupBy('estado')->select('estado', DB::raw('count(*) as total'))->get();
        $client_month = ClientMainInformation::where('created_at', '>=', Carbon::now()->subMonth()->toDateString())->count();
        $client_year = ClientMainInformation::where('created_at', '>=', Carbon::now()->subYear()->toDateString())->count();

        foreach ($client_status_infos as $key => $client_status_info) {
            $array[$client_status_info['estado']]['total'] = $client_status_info['total'];
        }


        $clientRepository = new ClientRepository();
        $toca_pagar_hoy = $clientRepository->getClientsToBillingServices()->count();
        $clientRepository = new ClientRepository();
        $toca_suspender_hoy = $clientRepository->getClientsToSuspendServices()->count();

        $clientRepository = new ClientRepository();
        $total = $clientRepository->count();

        $array["Añadido ultimo mes"]['total'] = $client_month;
        $array["Añadido ultimo año"]['total'] = $client_year;
        $array["Toca cobrarle hoy"]['total'] = $toca_pagar_hoy;
        $array["Toca suspender hoy"]['total'] = $toca_suspender_hoy;
        $array["Total"]['total'] = $total;
        return $array;
    }

    public function getStatsCardTicketsInDashBoard()
    {
        $array = [
            "Nuevo" => [
                "estado" => "Nuevo",
                "total" => 0,
            ],
            "Trabajo en curso" => [
                "estado" => "Trabajo en curso",
                "total" => 0,
            ],
            "Resuelto" => [
                "estado" => "Resuelto",
                "total" => 0,
            ],
            "Esperando al agente" => [
                "estado" => "Esperando al agente",
                "total" => 0,
            ],
        ];

        $ticket_status_infos = Ticket::groupBy('estado')->select('estado', DB::raw('count(*) as total'))->get();
        foreach ($ticket_status_infos as $key => $ticket_status_info) {
            $array[$ticket_status_info['estado']]['total'] = $ticket_status_info['total'];
        }
        return $array;
    }


    public function getStatsCardFinanceInDashBoard()
    {

        $paymentRepository = new FinanceService();
        $infoFinanceDashboard = $paymentRepository->getInfoFinanceDashboard();
        //Current Month
        $totalPaymentCurrentMonth = $infoFinanceDashboard['totalPaymentCurrentMonth'];
        $totalAmountPaymentCurrentMonth = $infoFinanceDashboard['totalAmountPaymentCurrentMonth'];
        $totalInvoiceCurrentMonth = $infoFinanceDashboard['totalInvoiceCurrentMonth'];
        $totalAmountInvoiceCurrentMonth = $infoFinanceDashboard['totalAmountInvoiceCurrentMonth'];
        $totalInvoicePendingCurrentMonth = $infoFinanceDashboard['totalInvoicePendingCurrentMonth'];
        $totalAmountInvoicePendingCurrentMonth = $infoFinanceDashboard['totalAmountInvoicePendingCurrentMonth'];

        //Last month
        $totalPaymentLastMonth = $infoFinanceDashboard['totalPaymentLastMonth'];
        $totalAmountPaymentLastMonth = $infoFinanceDashboard['totalAmountPaymentLastMonth'];
        $totalInvoiceLastMonth = $infoFinanceDashboard['totalInvoiceLastMonth'];
        $totalAmountInvoiceLastMonth = $infoFinanceDashboard['totalAmountInvoiceLastMonth'];
        $totalInvoicePendingLastMonth = $infoFinanceDashboard['totalInvoicePendingLastMonth'];
        $totalAmountInvoicePendingLastMonth = $infoFinanceDashboard['totalAmountInvoicePendingLastMonth'];


        $array = [
            "Mes Actual" => [
                "estado" => "",
                "total" => '',
            ],
            "Pagos" => [
                "estado" => "Pagos",
                "total" => $totalPaymentCurrentMonth . ' (' . $totalAmountPaymentCurrentMonth . ' $)',
            ],
            "Facturas Pagadas Por Clientes Recurrentes" => [
                "estado" => "Facturas Pagadas Por Clientes Recurrentes",
                "total" => $totalInvoiceCurrentMonth . ' (' . $totalAmountInvoiceCurrentMonth . ' $)',
            ],
            "Facturas por Pagar Clientes Recurrentes" => [
                "estado" => "Facturas por Pagar Clientes Recurrentes",
                "total" => $totalInvoicePendingCurrentMonth . ' (' . $totalAmountInvoicePendingCurrentMonth . ' $)',
            ],
            "Notas de Crédito" => [
                "estado" => "Notas de Crédito",
                "total" => 0,
            ],

            //Last month

            "Mes Pasado" => [
                "estado" => "",
                "total" => '',
            ],
            "Pagos " => [
                "estado" => "Pagos",
                "total" => $totalPaymentLastMonth . ' (' . $totalAmountPaymentLastMonth . ' $)',
            ],
            "Facturas Pagadas Por Clientes Recurrentes " => [
                "estado" => "Facturas Pagadas Por Clientes Recurrentes",
                "total" => $totalInvoiceLastMonth . ' (' . $totalAmountInvoiceLastMonth . ' $)',
            ],
            "Facturas por Pagar Clientes Recurrentes " => [
                "estado" => "Facturas por Pagar",
                "total" => $totalInvoicePendingLastMonth . ' (' . $totalAmountInvoicePendingLastMonth . ' $)',
            ],
            "Notas de Crédito " => [
                "estado" => "Notas de Crédito",
                "total" => 0,
            ],
        ];

        return $array;
    }

    public function getStatsCardServerInDashBoard()
    {
        $serverInfoService = new ServerInfoService();
        $info = $serverInfoService->serverInform();
        if (!empty($info)) {
            $array = [
                "Fecha y Hora actual" => [
                    "estado" => "Fecha y Hora actual",
                    "total" => $info['instant'],
                ],
                "Nombre del Host" => [
                    "estado" => "Nombre del servidor",
                    "total" => $info['hostname'],
                ],
                "Información del Sistema Operativo" => [
                    "estado" => "Versión del sistema operativo",
                    "total" => $info['osInfo'],
                ],
                "Tiempo de actividad" => [
                    "estado" => "Tiempo desde el último reinicio",
                    "total" => $info['uptime'],
                ],
                "Zona Horaria" => [
                    "estado" => "Zona horaria configurada",
                    "total" => $info['timezone'],
                ],
                "Fecha y Hora Local" => [
                    "estado" => "Fecha y hora local del servidor",
                    "total" => $info['localDateTime'],
                ],
                "Versión de PHP" => [
                    "estado" => "Versión de PHP instalada",
                    "total" => $info['phpVersion'],
                ],
                "Información del CPU" => [
                    "estado" => "Modelo del procesador",
                    "total" => $info['cpuInfo'],
                ],
                "Núcleos del CPU" => [
                    "estado" => "Cantidad de núcleos del procesador",
                    "total" => $info['cpuCores'],
                ],
                "Uso del CPU (%)" => [
                    "estado" => "Porcentaje de uso actual del CPU",
                    "total" => $info['cpuUsedPct'] . "%",
                ],
                "Memoria Usada (MB)" => [
                    "estado" => "Memoria RAM en uso",
                    "total" => $info['memUsed'] . " MB",
                ],
                "Porcentaje de Memoria Usada" => [
                    "estado" => "Porcentaje de RAM utilizada",
                    "total" => $info['memUsedPct'] . "%",
                ],
                "Memoria Total (MB)" => [
                    "estado" => "Capacidad total de RAM",
                    "total" => $info['memTotal'] . " MB",
                ],
                "Swap Usado (MB)" => [
                    "estado" => "Espacio de intercambio en uso",
                    "total" => $info['swapUsed'] . " MB",
                ],
                "Porcentaje de Swap Usado" => [
                    "estado" => "Porcentaje de espacio de intercambio utilizado",
                    "total" => $info['swapUsedPct'] . "%",
                ],
                "Swap Total (MB)" => [
                    "estado" => "Capacidad total de intercambio",
                    "total" => $info['swapTotal'] . " MB",
                ],
                "Cantidad de Procesos" => [
                    "estado" => "Número total de procesos en ejecución",
                    "total" => $info['processCount'],
                ],
                "Carga del Sistema (1 minuto)" => [
                    "estado" => "Carga promedio en 1 minuto",
                    "total" => $info['systemLoad1'],
                ],
                "Carga del Sistema (5 minutos)" => [
                    "estado" => "Carga promedio en 5 minutos",
                    "total" => $info['systemLoad5'],
                ],
                "Carga del Sistema (15 minutos)" => [
                    "estado" => "Carga promedio en 15 minutos",
                    "total" => $info['systemLoad15'],
                ],
                "Espacio Usado en Root (GB)" => [
                    "estado" => "Espacio usado en la raíz",
                    "total" => $info['hdRootUsed'] . " GB",
                ],
                "Porcentaje de Espacio Usado en Root" => [
                    "estado" => "Porcentaje de uso en la raíz",
                    "total" => $info['hdRootUsedPct'] . "%",
                ],
                "Espacio Total en Root (GB)" => [
                    "estado" => "Espacio total en la raíz",
                    "total" => $info['hdRootTotal'] . " GB",
                ],
                "Espacio Libre en Root (GB)" => [
                    "estado" => "Espacio disponible en la raíz",
                    "total" => $info['hdRootFree'] . " GB",
                ],
                "Porcentaje de Espacio Libre en Root" => [
                    "estado" => "Porcentaje de espacio libre en la raíz",
                    "total" => $info['hdRootFreePct'] . "%",
                ],
                "Fecha de Ultima Salva de la Base de Datos" => [
                    "estado" => "Fecha de Ultima Salva de la Base de Datos",
                    "total" => $info['lastBackupDb'] . "%",
                ],
                // Puedes añadir las secciones para /tmp, /var y /app de forma similar si necesitas cubrir todas las rutas.
            ];

            return $array;
        }

        return $info;
    }
}
