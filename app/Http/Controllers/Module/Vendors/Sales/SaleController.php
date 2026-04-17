<?php

namespace App\Http\Controllers\Module\Vendors\Sales;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ClientMainInformation;
use App\Models\CrmMainInformation;
use App\Models\CrmLeadInformation;
use App\Services\Vendors\VendorService;
use Carbon\Carbon;

class SaleController extends Controller
{
    use DatatableCoreTrait;

    protected $model = ClientMainInformation::class;

    public function index()
    {
        $sales = ClientMainInformation::all();
        return response()->json($sales);
    }

    // Obtener las ventas por vendedor
    public function salesBySeller($id)
    {
        $sales = (new VendorService())->getSalesBySeller($id);
        return response()->json($sales);
    }

    public function getSalesBySeller(Request $request, $id)
    {
        $filters = $request->filters ?? [];
        $filters[] = [
            'column' => 'seller_id',
            'value' => $id
        ];
        $exists = collect($filters)->firstWhere('column', 'activation_date');
        if (!$exists) {
            $filters[] = [
                'type' => 'date',
                'operator' => '>=',
                'column' => 'activation_date',
                'value' => '2024-06-01'
            ];
        }
        $columns = $request->columns ?? [];
        array_push($columns, ...['router_id', 'bundle_id', 'custom_id', 'voz_id', 'type_billing_id']);
        $mapping = $this->getColumnMapping();
        $query = $this->getGeneralQuery($columns, $mapping);
        $query = $this->applyFilters($query, $filters, $mapping);
        $query = $this->applySearch($query, $request->search ?? null, $columns, $mapping);
        $sortBy = isset($request->sortBy) ? $request->sortBy : $this->model . $columns[0];
        $dir = $request->descending === true ? 'DESC' : 'ASC';
        $query = $this->applySorting($query, $sortBy, $dir, $mapping);
        $client_payments = [];
        foreach ($query->get() as $c) {
            $client = ClientMainInformation::firstWhere('client_id', $c['id']);
            if ($client) {
                $payments_in_three_first_five_months = $client->getPaymentsInThreeFirstFiveMonths();
                if ($payments_in_three_first_five_months) {
                    $completed = $payments_in_three_first_five_months['completed'];
                    if ($completed < 3) {
                        $client_payments[] = [
                            ...$c->toArray(),
                            'completed_payment' => $completed,
                            'pending_payment' => 3 - $completed,
                            'package_price' => $payments_in_three_first_five_months['service'],
                            'payments' => $payments_in_three_first_five_months['payments'],
                        ];
                    }
                }
            }
        }
        $total = count($client_payments);
        $start = ($request->page - 1) * $request->rowsPerPage;
        $limit = $start + $request->rowsPerPage;

        return response()->json([
            'clients' => array_slice($client_payments, $start, $limit),
            'total' => $total
        ]);
    }

    public function getOrderColumnModifiedName($order)
    {
        if ($order == 'client_name_with_fathers_names') {
            return 'name';
        } elseif ($order == 'date_activation') {
            return 'activation_date';
        } else {
            return $order;
        }
    }

    // Obtener las ventas por medio de venta en un rango de fechas
    public function salesByMedium($startDate, $endDate)
    {
        $totalSales = ClientMainInformation::whereBetween('activation_date', [$startDate, $endDate])->count();

        $salesByMedium = ClientMainInformation::join('medium_sales', 'client_main_information.medium_id', '=', 'medium_sales.id')
            ->select('medium_sales.name', DB::raw('count(*) as total'))
            ->whereBetween('client_main_information.activation_date', [$startDate, $endDate])
            ->groupBy('medium_sales.name')
            ->get()
            ->map(function ($sale) use ($totalSales) {
                $sale->percentage = ($sale->total / $totalSales) * 100;
                return $sale;
            });

        return response()->json($salesByMedium);
    }

    // Obtener la comparativa de ventas (mes actual vs mes anterior)
    public function salesByMonth()
    {
        $currentMonthSales = ClientMainInformation::select(DB::raw('activation_date as day'), DB::raw('count(*) as sales'))
            ->whereMonth('activation_date', Carbon::now()->month)
            ->groupBy(DB::raw('activation_date'))
            ->orderBy('day')
            ->get();

        $previousMonthSales = ClientMainInformation::select(DB::raw('activation_date as day'), DB::raw('count(*) as sales'))
            ->whereMonth('activation_date', Carbon::now()->subMonth()->month)
            ->groupBy(DB::raw('activation_date'))
            ->orderBy('day')
            ->get();

        $salesComparison = [
            'current_month' => $currentMonthSales,
            'previous_month' => $previousMonthSales
        ];

        return response()->json($salesComparison);
    }

    // Obtener el numero ventas y prospectos por rango de fechas
    public function salesAndProspectsByDateRange($startDate, $endDate)
    {
        $salesByDateRange = ClientMainInformation::select(DB::raw('DATE(activation_date) as date'), DB::raw('count(*) as sales'))
            ->whereBetween('activation_date', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(activation_date)'))
            ->orderBy('date')
            ->get();

        $prospectsByDateRange = CrmMainInformation::select(DB::raw('DATE(high_date) as date'), DB::raw('count(*) as prospects'))
            ->whereBetween('high_date', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(high_date)'))
            ->orderBy('date')
            ->get();

        $salesAndProspectsByDateRange = [
            'sales' => $salesByDateRange,
            'prospects' => $prospectsByDateRange
        ];

        return response()->json($salesAndProspectsByDateRange);
    }

    // Obtenet el ranking de los vendedores
    public function rankingSales($startDate, $endDate)
    {
        $ranking = ClientMainInformation::join('users', 'users.id', '=', 'client_main_information.seller_id')
            ->select('users.name', DB::raw('count(*) as sales'))
            ->whereBetween('client_main_information.activation_date', [$startDate, $endDate])
            ->groupBy('users.name')
            ->orderBy('sales', 'desc')
            ->get();

        return response()->json($ranking);
    }

    // Obtener el numero total de prospectos
    public function getTotalProspects()
    {
        $total_prospects = CrmLeadInformation::count();
        return response()->json($total_prospects);
    }

    // Obtener el numero total de ventas
    public function getTotalSales()
    {
        $total_sales = ClientMainInformation::count();
        return response()->json($total_sales);
    }

    // Obtener el numero de ventas perdidas o sin seguimiento
    public function getLostSales()
    {
        $total_lost_sales = ClientMainInformation::where('estado', 'Perdido')->count();
        return response()->json($total_lost_sales);
    }

    protected function getBaseColumnsByTable()
    {
        return [
            'clients' => [
                'id' => ['searchable' => true],
                'fecha_corte',
                'fecha_pago'
            ],
            'client_main_information' => [
                'name' => ['searchable' => true],
                'father_last_name' => ['searchable' => true],
                'mother_last_name' => ['searchable' => true],
                'email' => ['searchable' => true],
                'phone' => ['searchable' => true],
                'phone2' => ['searchable' => true],
                'phone3' => ['searchable' => true],
                'nif_pasaport' => ['searchable' => true],
                'street' => ['searchable' => true],
                'external_number' => ['searchable' => true],
                'internal_number' => ['searchable' => true],
                'zip' => ['searchable' => true],
                'discharge_date',
                'estado' => ['searchable' => true],
                'ift' => ['searchable' => true],
                'seller_id',
                'client_id',
                'activation_date'
            ],
            'client_additional_information' => [
                'category',
                'modem_sn',
                'gpon_ont',
                'power_dbm',
                'olt_power_dbm',
                'original_password',
                'box_nomenclator',
                'box_nomenclator_old',
                'user_film',
                'password_film',
                'password_wifi',
                'reinstatement',
                'social_id',
                'comment',
                'installation_on_time',
                'amount_technician_and_why',
                'doubt_signed_contract',
                'technician_attencion',
                'last_time_online',
            ],
            'states' => [
                'state_id' => [
                    'column' => 'name',
                    'searchable' => true
                ]
            ],
            'colonies' => [
                'colony_id' => [
                    'column' => 'name',
                    'searchable' => true
                ]
            ],
            'municipalities' => [
                'municipality_id' => [
                    'column' => 'name',
                    'searchable' => true
                ]
            ],
            'billing_configurations' => [
                'billing_activated',
                'period',
                'billing_date',
                'billing_expiration',
                'grace_period',
                'autopay_invoice',
                'send_financial_notification',
                'type_billing_id'
            ],
            'type_billings' => [
                'type_of_billing_id' => [
                    'column' => 'type',
                ],
            ],
            'method_of_payments' => [
                'payment_method_id' => [
                    'column' => 'type'
                ]
            ],
            'reminders_configurations' => [
                'activate_reminders',
                'type_of_message',
                'reminder_1_days',
                'reminder_2_days',
                'reminder_3_days',
                'reminder_payment_3',
                'reminder_payment_amount',
                'reminder_payment_comment',
            ],
            'billing_addresses' => [
                'billing_name',
                'billing_street',
                'billing_zip_code',
                'billing_city',
            ],
            'balances' => [
                'amount'
            ],
            'partners' => [
                'partner_id' => [
                    'column' => 'name',
                    'searchable' => true
                ]
            ],
            'locations' => [
                'location_id' => [
                    'column' => 'name',
                    'searchable' => true
                ]
            ],
            'network_ips' => [
                'ip_ranges' => [
                    'column' => 'ip',
                    'searchable' => true
                ]
            ],
            'nomenclatures' => [
                'nomenclature_name' => [
                    'column' => 'name',
                    'searchable' => true
                ]
            ],
            'client_internet_services' => [
                'router_id'
            ],
            'routers' => [
                'router' => [
                    'column' => 'title',
                    'searchable' => true
                ]
            ],
            'internets' => [
                'internet_fees' => [
                    'column' => 'title',
                    'searchable' => true
                ]
            ],
            'client_bundle_services' => [
                'bundle_id',
            ],
            'bundles' => [
                'bundle_fees' => [
                    'column' => 'title',
                    'searchable' => true
                ]
            ],
            'client_custom_services' => [
                'custom_id'
            ],
            'client_voz_services' => [
                'voz_id'
            ],
            'voises' => [
                'voz_fees' => [
                    'column' => 'title',
                    'searchable' => true
                ]
            ],
            'customs' => [
                'custom_fees' => [
                    'column' => 'title',
                    'searchable' => true
                ]
            ],
            'client_internet_services' => [
                'service_user_name' => [
                    'column' => 'user',
                    'searchable' => true
                ],
                'mac'
            ],
        ];
    }

    protected function getJoinConfiguration()
    {
        return [
            'clients' => [
                'type' => 'join',
                'on'   => ['clients.id', '=', 'client_main_information.client_id'],
            ],
            'client_additional_information' => [
                'type' => 'join',
                'on'   => ['clients.id', '=', 'client_additional_information.client_id'],
            ],
            'states' => [
                'type' => 'join',
                'on'   => ['states.id', '=', 'client_main_information.state_id'],
            ],
            'colonies' => [
                'type' => 'join',
                'on'   => ['colonies.id', '=', 'client_main_information.colony_id'],
            ],
            'municipalities' => [
                'type' => 'join',
                'on'   => ['municipalities.id', '=', 'client_main_information.municipality_id'],
            ],
            'billing_configurations' => [
                'type' => 'leftJoin',
                'on'   => ['clients.id', '=', 'billing_configurations.client_id'],
            ],
            'type_billings' => [
                'type' => 'leftJoin',
                'on'   => ['billing_configurations.type_billing_id', '=', 'type_billings.id'],
            ],
            'method_of_payments' => [
                'type' => 'leftJoin',
                'on' => ['billing_configurations.payment_method_id', '=', 'method_of_payments.id']
            ],
            'reminders_configurations' => [
                'type' => 'leftJoin',
                'on' => ['clients.id', '=', 'reminders_configurations.client_id']
            ],
            'billing_addresses' => [
                'type' => 'leftJoin',
                'on' => ['clients.id', '=', 'billing_addresses.client_id']
            ],
            'balances' => [
                'type' => 'leftJoin',
                'on' => ['clients.id', '=', 'balances.balanceable_id']
            ],
            'partners' => [
                'type' => 'leftJoin',
                'on' => ['client_main_information.partner_id', '=', 'partners.id']
            ],
            'locations' => [
                'type' => 'leftJoin',
                'on' => ['client_main_information.location_id', '=', 'locations.id']
            ],
            'network_ips' => [
                'type' => 'leftJoin',
                'on' => ['clients.id', '=', 'network_ips.client_id']
            ],
            'nomenclatures' => [
                'type' => 'leftJoin',
                'on' => ['clients.id', '=', 'nomenclatures.client_id']
            ],
            'client_internet_services' => [
                'type' => 'leftJoin',
                'on' => ['clients.id', '=', 'client_internet_services.client_id']
            ],
            'routers' => [
                'type' => 'leftJoin',
                'on' => ['client_internet_services.router_id', '=', 'routers.id']
            ],
            'internets' => [
                'type' => 'leftJoin',
                'on' => ['client_internet_services.internet_id', '=', 'internets.id']
            ],
            'client_bundle_services' => [
                'type' => 'leftJoin',
                'on' => ['clients.id', '=', 'client_bundle_services.client_id']
            ],
            'bundles' => [
                'type' => 'leftJoin',
                'on' => ['client_bundle_services.bundle_id', '=', 'bundles.id']
            ],
            'client_voz_services' => [
                'type' => 'leftJoin',
                'on' => ['clients.id', '=', 'client_voz_services.client_id']
            ],
            'voises' => [
                'type' => 'leftJoin',
                'on' => ['client_voz_services.voz_id', '=', 'voises.id']
            ],
            'client_custom_services' => [
                'type' => 'leftJoin',
                'on' => ['clients.id', '=', 'client_custom_services.client_id']
            ],
            'customs' => [
                'type' => 'leftJoin',
                'on' => ['client_custom_services.custom_id', '=', 'customs.id']
            ],
        ];
    }
}
