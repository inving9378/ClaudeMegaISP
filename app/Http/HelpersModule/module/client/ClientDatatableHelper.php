<?php


namespace App\Http\HelpersModule\module\client;

use App\Models\Client;
use App\Models\ClientCustomService;
use App\Models\ClientInternetService;
use App\Models\Module;
use App\Services\AppLayoutConfigurationService;
use App\Services\FormatDateService;
use App\Services\NetworkIpService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ClientDatatableHelper
{
    private $model;
    private $columns;
    private $filterName;
    private $columnsDatatableToFilter;
    private $sortedColumnsDatatable;


    public function __construct()
    {
        $this->model = Client::class;
    }


    public function count($filters = null)
    {
        if (!empty($filters)) {
            $query = $this->queryCustomFilter($this->model, $filters);
            $count = $query->count();
            return $count;
        }

        $count = DB::table('clients')->whereNull('deleted_at')->count();
        return $count;
    }


    public function ordering_query($start, $limit, $order, $dir, $filters = null, $columns = [])
    {
        $arrayColumnsWithJoins = $this->getColumnsWithJoinsByColumnsSelected($columns);
        $columnsForSelect = array_column($arrayColumnsWithJoins, 'column');

        $this->columns = array_keys($arrayColumnsWithJoins);

        // Procesar joins
        $joins = [];
        foreach ($arrayColumnsWithJoins as $item) {
            if (!empty($item['join'])) {
                $joinDefinitions = is_array($item['join']) ? $item['join'] : [$item['join']];

                foreach ($joinDefinitions as $joinDef) {
                    if (!isset($joins[$joinDef])) {
                        $parts = array_map('trim', explode(',', str_replace("'", "", $joinDef)));
                        $joins[$joinDef] = [
                            'table' => $parts[0],
                            'first' => $parts[1],
                            'operator' => $parts[2],
                            'second' => $parts[3]
                        ];
                    }
                }
            }
        }

        // Construir consulta sin cargar relaciones automáticas
        $query = $this->model::select($columnsForSelect)
            ->without(['client_main_information', 'client_additional_information', 'billing_configuration', 'balance']);

        // Aplicar joins manuales
        foreach ($joins as $join) {
            $query->leftJoin($join['table'], $join['first'], $join['operator'], $join['second']);
        }

        if (in_array('last_payment', $columns)) {
            $query->leftJoin(
                DB::raw('(SELECT paymentable_id, MAX(date) as last_payment FROM payments GROUP BY paymentable_id) as latest_payments'),
                'clients.id',
                '=',
                'latest_payments.paymentable_id'
            );
        }

        $query->whereNull('clients.deleted_at');
        // Aplicar filtros
        if (!empty($filters)) {
            $query = $query->filters($this->filterName, null, $filters);
        }
        // Ordenación
        if ($order === 'ip_ranges') {
            $query->orderByRaw("INET6_ATON(network_ips.ip) $dir");
        } else {
            $qualifiedOrderColumn = $this->qualifyOrderColumn($order, $arrayColumnsWithJoins);
            $query->orderBy($qualifiedOrderColumn, $dir);
        }

        $query->offset($start)->limit($limit);
        $result = $query->get();
        return $result;
    }

    public function getColumnsWithJoinsByColumnsSelected($columns = [])
    {
        $allColumns = [
            // Campos de la tabla clients
            'id' => [
                'column' => 'clients.id as id',
                'join' => ''
            ],
            'fecha_corte' => [
                'column' => 'clients.fecha_corte',
                'join' => ''
            ],
            'fecha_pago' => [
                'column' => 'clients.fecha_pago',
                'join' => ''
            ],
            'fecha_fin_periodo_gracia' => [
                'column' => 'clients.fecha_fin_periodo_gracia',
                'join' => ''
            ],

            // Campos de client_main_information
            'name' => [
                'column' => 'client_main_information.name',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'father_last_name' => [
                'column' => 'client_main_information.father_last_name',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'mother_last_name' => [
                'column' => 'client_main_information.mother_last_name',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'phone' => [
                'column' => 'client_main_information.phone',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'phone2' => [
                'column' => 'client_main_information.phone2',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'email' => [
                'column' => 'client_main_information.email',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'street' => [
                'column' => 'client_main_information.street',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'address' => [
                'column' => 'client_main_information.address',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'zip' => [
                'column' => 'client_main_information.zip',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'external_number' => [
                'column' => 'client_main_information.external_number',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'internal_number' => [
                'column' => 'client_main_information.internal_number',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'nif_pasaport' => [
                'column' => 'client_main_information.nif_pasaport',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'seller_id' => [
                'column' => 'client_main_information.seller_id',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'password' => [
                'column' => 'client_main_information.password',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'estado' => [
                'column' => 'client_main_information.estado',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'client_main_information_estado' => [
                'column' => 'client_main_information.estado as client_main_information_estado',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'created_at' => [
                'column' => 'client_main_information.created_at',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],
            'updated_at' => [
                'column' => 'client_main_information.updated_at',
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ],

            // Campos de client_additional_information
            'category' => [
                'column' => 'client_additional_information.category',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'modem_sn' => [
                'column' => 'client_additional_information.modem_sn',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'gpon_ont' => [
                'column' => 'client_additional_information.gpon_ont',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'power_dbm' => [
                'column' => 'client_additional_information.power_dbm',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'olt_power_dbm' => [
                'column' => 'client_additional_information.olt_power_dbm',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'original_password' => [
                'column' => 'client_additional_information.original_password',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'box_nomenclator' => [
                'column' => 'client_additional_information.box_nomenclator',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'box_nomenclator_old' => [
                'column' => 'client_additional_information.box_nomenclator_old',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'user_film' => [
                'column' => 'client_additional_information.user_film',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'password_film' => [
                'column' => 'client_additional_information.password_film',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'password_wifi' => [
                'column' => 'client_additional_information.password_wifi',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'reinstatement' => [
                'column' => 'client_additional_information.reinstatement',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'social_id' => [
                'column' => 'client_additional_information.social_id',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'comment' => [
                'column' => 'client_additional_information.comment',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'installation_on_time' => [
                'column' => 'client_additional_information.installation_on_time',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'amount_technician_and_why' => [
                'column' => 'client_additional_information.amount_technician_and_why',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'doubt_signed_contract' => [
                'column' => 'client_additional_information.doubt_signed_contract',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'technician_attencion' => [
                'column' => 'client_additional_information.technician_attencion',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],
            'last_time_online' => [
                'column' => 'client_additional_information.last_time_online',
                'join' => "'client_additional_information', 'clients.id', '=', 'client_additional_information.client_id'"
            ],

            // Campos de billing_configurations
            'billing_activated' => [
                'column' => 'billing_configurations.billing_activated',
                'join' => "'billing_configurations', 'clients.id', '=', 'billing_configurations.client_id'"
            ],
            'period' => [
                'column' => 'billing_configurations.period',
                'join' => "'billing_configurations', 'clients.id', '=', 'billing_configurations.client_id'"
            ],
            'billing_date' => [
                'column' => 'billing_configurations.billing_date',
                'join' => "'billing_configurations', 'clients.id', '=', 'billing_configurations.client_id'"
            ],
            'billing_expiration' => [
                'column' => 'billing_configurations.billing_expiration',
                'join' => "'billing_configurations', 'clients.id', '=', 'billing_configurations.client_id'"
            ],
            'grace_period' => [
                'column' => 'billing_configurations.grace_period',
                'join' => "'billing_configurations', 'clients.id', '=', 'billing_configurations.client_id'"
            ],
            'autopay_invoice' => [
                'column' => 'billing_configurations.autopay_invoice',
                'join' => "'billing_configurations', 'clients.id', '=', 'billing_configurations.client_id'"
            ],
            'send_financial_notification' => [
                'column' => 'billing_configurations.send_financial_notification',
                'join' => "'billing_configurations', 'clients.id', '=', 'billing_configurations.client_id'"
            ],

            // Campos de type_billings
            'type_of_billing_id' => [
                'column' => 'type_billings.type as type_of_billing_id',
                'join' => [
                    "'billing_configurations', 'clients.id', '=', 'billing_configurations.client_id'",
                    "'type_billings', 'billing_configurations.type_billing_id', '=', 'type_billings.id'"
                ]
            ],
            'type_billing_id' => [
                'column' => 'type_billings.type as type_billing_id',
                'join' => "'type_billings', 'billing_configurations.type_billing_id', '=', 'type_billings.id'"
            ],

            // Campos de method_of_payments
            'payment_method_id' => [
                'column' => 'method_of_payments.type as payment_method_id',
                'join' => "'method_of_payments', 'billing_configurations.payment_method_id', '=', 'method_of_payments.id'"
            ],

            // Campos de reminders_configurations
            'activate_reminders' => [
                'column' => 'reminders_configurations.activate_reminders',
                'join' => "'reminders_configurations', 'clients.id', '=', 'reminders_configurations.client_id'"
            ],
            'type_of_message' => [
                'column' => 'reminders_configurations.type_of_message',
                'join' => "'reminders_configurations', 'clients.id', '=', 'reminders_configurations.client_id'"
            ],
            'reminder_1_days' => [
                'column' => 'reminders_configurations.reminder_1_days',
                'join' => "'reminders_configurations', 'clients.id', '=', 'reminders_configurations.client_id'"
            ],
            'reminder_2_days' => [
                'column' => 'reminders_configurations.reminder_2_days',
                'join' => "'reminders_configurations', 'clients.id', '=', 'reminders_configurations.client_id'"
            ],
            'reminder_3_days' => [
                'column' => 'reminders_configurations.reminder_3_days',
                'join' => "'reminders_configurations', 'clients.id', '=', 'reminders_configurations.client_id'"
            ],
            'reminder_payment_3' => [
                'column' => 'reminders_configurations.reminder_payment_3',
                'join' => "'reminders_configurations', 'clients.id', '=', 'reminders_configurations.client_id'"
            ],
            'reminder_payment_amount' => [
                'column' => 'reminders_configurations.reminder_payment_amount',
                'join' => "'reminders_configurations', 'clients.id', '=', 'reminders_configurations.client_id'"
            ],
            'reminder_payment_comment' => [
                'column' => 'reminders_configurations.reminder_payment_comment',
                'join' => "'reminders_configurations', 'clients.id', '=', 'reminders_configurations.client_id'"
            ],

            // Campos de billing_addresses
            'billing_name' => [
                'column' => 'billing_addresses.billing_name',
                'join' => "'billing_addresses', 'clients.id', '=', 'billing_addresses.client_id'"
            ],
            'billing_street' => [
                'column' => 'billing_addresses.billing_street',
                'join' => "'billing_addresses', 'clients.id', '=', 'billing_addresses.client_id'"
            ],
            'billing_zip_code' => [
                'column' => 'billing_addresses.billing_zip_code',
                'join' => "'billing_addresses', 'clients.id', '=', 'billing_addresses.client_id'"
            ],
            'billing_city' => [
                'column' => 'billing_addresses.billing_city',
                'join' => "'billing_addresses', 'clients.id', '=', 'billing_addresses.client_id'"
            ],

            // Campos de balances
            'amount' => [
                'column' => 'balances.amount',
                'join' => "'balances', 'clients.id', '=', 'balances.balanceable_id'"
            ],

            // Campos de locations, partners, states, municipalities, colonies
            'state_id' => [
                'column' => 'states.name as state_id',
                'join' => "'states', 'client_main_information.state_id', '=', 'states.id'"
            ],
            'municipality_id' => [
                'column' => 'municipalities.name as municipality_id',
                'join' => "'municipalities', 'client_main_information.municipality_id', '=', 'municipalities.id'"
            ],
            'colony_id' => [
                'column' => 'colonies.name as colony_id',
                'join' => "'colonies', 'client_main_information.colony_id', '=', 'colonies.id'"
            ],
            'partner_id' => [
                'column' => 'partners.name as partner_id',
                'join' => "'partners', 'client_main_information.partner_id', '=', 'partners.id'"
            ],
            'location_id' => [
                'column' => 'locations.name as location_id',
                'join' => "'locations', 'client_main_information.location_id', '=', 'locations.id'"
            ],

            // Campos de network_ips
            'ip_ranges' => [
                'column' => 'network_ips.ip as ip_ranges',
                'join' => "'network_ips', 'clients.id', '=', 'network_ips.client_id'"
            ],
            // Campos de nomenclatures
            'nomenclature_name' => [
                'column' => 'nomenclatures.name as nomenclature_name',
                'join' => "'nomenclatures', 'clients.id', '=', 'nomenclatures.client_id'"
            ],

            // Campos de servicios (internet, bundles, voz, customs)
            'router' => [
                'column' => 'routers.title as router',
                'join' => [
                    "'client_internet_services', 'clients.id', '=', 'client_internet_services.client_id'",
                    "'routers', 'client_internet_services.router_id', '=', 'routers.id'"
                ]
            ],
            'internet_fees' => [
                'column' => 'internets.title as internet_fees',
                'join' =>  [
                    "'client_internet_services', 'clients.id', '=', 'client_internet_services.client_id'",
                    "'internets', 'client_internet_services.internet_id', '=', 'internets.id'"
                ]
            ],
            'bundle_fees' => [
                'column' => 'bundles.title as bundle_fees',
                'join' => [
                    "'client_bundle_services', 'clients.id', '=', 'client_bundle_services.client_id'",
                    "'bundles', 'client_bundle_services.bundle_id', '=', 'bundles.id'"
                ]
            ],
            'voz_fees' => [
                'column' => 'voises.title as voz_fees',
                'join' => [
                    "'client_voz_services', 'clients.id', '=', 'client_voz_services.client_id'",
                    "'voises', 'client_voz_services.voz_id', '=', 'voises.id'"
                ]
            ],
            'custom_fees' => [
                'column' => 'customs.title as custom_fees',
                'join' => [
                    "'client_custom_services', 'clients.id', '=', 'client_custom_services.client_id'",
                    "'customs', 'client_custom_services.custom_id', '=', 'customs.id'"
                ]
            ],
            'service_user_name' => [
                'column' => 'client_internet_services.user as service_user_name',
                'join' => "'client_internet_services', 'clients.id', '=', 'client_internet_services.client_id'"
            ],
            'mac' => [
                'column' => 'client_internet_services.mac as mac',
                'join' => "'client_internet_services', 'clients.id', '=', 'client_internet_services.client_id'"
            ],

            // Campos calculados
            'full_name' => [
                'column' => DB::raw("CONCAT(client_main_information.name, ' ', client_main_information.father_last_name, ' ', client_main_information.mother_last_name) as full_name"),
                'join' => "'client_main_information', 'clients.id', '=', 'client_main_information.client_id'"
            ]
        ];

        if (empty($columns)) {
            return $allColumns;
        }

        return array_intersect_key($allColumns, array_flip($columns));
    }







    public function searching_query($start, $limit, $order, $dir, $search, $filters = null, $columns = null)
    {
        $arrayColumnsWithJoins = $this->getColumnsWithJoinsByColumnsSelected($columns);
        $columnsForSelect = array_column($arrayColumnsWithJoins, 'column');
        $this->columns = array_keys($arrayColumnsWithJoins);
        // Procesar joins
        $joins = [];
        foreach ($arrayColumnsWithJoins as $item) {
            if (!empty($item['join'])) {
                $joinDefinitions = is_array($item['join']) ? $item['join'] : [$item['join']];

                foreach ($joinDefinitions as $joinDef) {
                    if (!isset($joins[$joinDef])) {
                        $parts = array_map('trim', explode(',', str_replace("'", "", $joinDef)));
                        $joins[$joinDef] = [
                            'table' => $parts[0],
                            'first' => $parts[1],
                            'operator' => $parts[2],
                            'second' => $parts[3]
                        ];
                    }
                }
            }
        }

        // Construir consulta sin cargar relaciones automáticas
        $query = $this->model::select($columnsForSelect)
            ->without(['client_main_information', 'client_additional_information', 'billing_configuration', 'balance']);

        // Aplicar joins manuales
        foreach ($joins as $join) {
            $query->leftJoin($join['table'], $join['first'], $join['operator'], $join['second']);
        }

        if (in_array('last_payment', $columns)) {
            $query->leftJoin(
                DB::raw('(SELECT paymentable_id, MAX(date) as last_payment FROM payments GROUP BY paymentable_id) as latest_payments'),
                'clients.id',
                '=',
                'latest_payments.paymentable_id'
            );
        }

        $query->whereNull('clients.deleted_at');
        $query = $query->filters($this->filterName, $search, $filters);
        // Ordenación
        if ($order === 'ip_ranges') {
            $query->orderByRaw("INET6_ATON(network_ips.ip) $dir");
        } else {
            $qualifiedOrderColumn = $this->qualifyOrderColumn($order, $arrayColumnsWithJoins);
            $query->orderBy($qualifiedOrderColumn, $dir);
        }

        $query->offset($start)->limit($limit);
        $result = $query->get();
        return $result;
    }

    public function filtering_query($search, $columns = null, $filters = null)
    {
        $moduleName = 'Client';
        $module = Module::with('columnsDatatable')->where('name', $moduleName)->first();
        $columnsDatatable = $module->columnsDatatable
            ->pluck('filter_name', 'name')
            ->toArray();
        $this->columnsDatatableToFilter = $columnsDatatable;

        $columnsDatatableOrder = $module->columnsDatatable
            ->pluck('name', 'order')
            ->toArray();
        ksort($columnsDatatableOrder);

        $this->sortedColumnsDatatable = array_replace(array_flip($columnsDatatableOrder), $columnsDatatable);

        $this->filterName = collect(array_values($columnsDatatable))->whereNotNull()->values()->toArray();
        $this->columns = array_keys($this->sortedColumnsDatatable);
        if (!empty($columns)) {
            $this->filterName = [];
            foreach ($columns as $column) {
                if (isset($this->columnsDatatableToFilter[$column])) {
                    $this->filterName[] = $this->columnsDatatableToFilter[$column];
                }
            }
        }

        return $this->model::filters($this->filterName, $search, $filters)
            ->leftJoin('nomenclatures', 'clients.id', '=', 'nomenclatures.client_id')
            ->leftJoin('client_main_information', 'clients.id', '=', 'client_main_information.client_id')
            ->leftJoin('client_additional_information', 'clients.id', '=', 'client_additional_information.client_id')
            ->leftJoin('billing_configurations', 'clients.id', '=', 'billing_configurations.client_id')
            ->leftJoin('type_billings', 'billing_configurations.type_billing_id', '=', 'type_billings.id')
            ->leftJoin('reminders_configurations', 'clients.id', '=', 'reminders_configurations.client_id')
            ->leftJoin('billing_addresses', 'clients.id', '=', 'billing_addresses.client_id')
            ->leftJoin('balances', 'clients.id', '=', 'balances.balanceable_id')
            ->leftJoin('method_of_payments', 'billing_configurations.payment_method_id', '=', 'method_of_payments.id')
            ->leftJoin('locations', 'client_main_information.location_id', '=', 'locations.id')

            ->leftJoin('partners', 'client_main_information.partner_id', '=', 'partners.id')
            ->leftJoin('states', 'client_main_information.state_id', '=', 'states.id')
            ->leftJoin('municipalities', 'client_main_information.municipality_id', '=', 'municipalities.id')
            ->leftJoin('colonies', 'client_main_information.colony_id', '=', 'colonies.id')
            ->leftJoin('network_ips', 'clients.id', '=', 'network_ips.client_id')
            ->leftJoin('networks', 'network_ips.network_id', '=', 'networks.id')
            ->leftJoin('users', 'client_main_information.seller_id', '=', 'users.id')
            ->leftJoin(
                DB::raw('(SELECT paymentable_id, MAX(date) as last_payment FROM payments GROUP BY paymentable_id) as latest_payments'),
                'clients.id',
                '=',
                'latest_payments.paymentable_id'
            )
            ->leftJoin('client_internet_services', 'clients.id', '=', 'client_internet_services.client_id')
            ->leftJoin('internets', 'client_internet_services.internet_id', '=', 'internets.id')
            ->leftJoin('routers', 'client_internet_services.router_id', '=', 'routers.id')
            ->leftJoin('client_bundle_services', 'clients.id', '=', 'client_bundle_services.client_id')
            ->leftJoin('bundles', 'client_bundle_services.bundle_id', '=', 'bundles.id')
            ->leftJoin('client_custom_services', 'clients.id', '=', 'client_custom_services.client_id')
            ->leftJoin('customs', 'client_custom_services.custom_id', '=', 'customs.id')
            ->leftJoin('client_voz_services', 'clients.id', '=', 'client_voz_services.client_id')
            ->leftJoin('voises', 'client_voz_services.voz_id', '=', 'voises.id')
            ->count();
    }

    protected function qualifyOrderColumn($column, $columnsWithJoins)
    {
        // Si la columna ya está calificada
        if (strpos($column, '.') !== false) {
            return $column;
        }

        // Caso 1: Es una columna con Expression (como full_name)
        if (isset($columnsWithJoins[$column])) {
            $colData = $columnsWithJoins[$column];
            if ($colData['column'] instanceof \Illuminate\Database\Query\Expression) {
                return $column; // Devolver el alias
            }
        }

        // Caso 2: Buscar en todas las columnas
        foreach ($columnsWithJoins as $colData) {
            $columnValue = $colData['column'];

            if (is_string($columnValue)) {
                // Verificar si termina con " as {column}"
                if (str_ends_with($columnValue, " as $column")) {
                    return $column;
                }
                // Verificar si termina con ".{column}"
                if (str_ends_with($columnValue, ".$column")) {
                    return $columnValue;
                }
            }
        }

        // Caso 3: Columna de la tabla principal por defecto
        return 'clients.' . $column;
    }

    protected function setupFilters($columns)
    {
        if (!empty($columns)) {
            $this->filterName = [];
            foreach ($columns as $column) {
                if (isset($this->columnsDatatableToFilter[$column])) {
                    $this->filterName[] = $this->columnsDatatableToFilter[$column];
                }
            }
        }
    }



    public function transform($request)
    {
        $data = array();

        $colorDatatable = $request['request']->color_active;
        $isUpdatedColor = $request['request']->is_update_color;

        if ($isUpdatedColor) {
            $appLayoutConfigurationService = new AppLayoutConfigurationService();
            $appLayoutConfigurationService->createOrUpdateClientDatatableColor($colorDatatable);
        }

        if ($colorDatatable == true) {
            $dirColumn = 'meganet.shared.table.column_client';
        } else {
            $dirColumn = 'meganet.shared.table.column';
        }

        $user = auth()->user();
        $userPermissions = $user->getCachedPermissions();
        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                $estado = $value->client_main_information ? $value->client_main_information->estado : 'Inactivo';
                $this->columns = array_diff($this->columns, ['user', 'password']);
                foreach ($this->columns as $val) {
                    if ($val == 'seller_id') {
                        $value->seller_id = $value->seller_name;
                    }
                    if ($val == 'price') {
                        $value->price = $value->price_all_services;
                    }

                    if ($val == 'fecha_corte') {
                        $value->fecha_corte = (new FormatDateService($value->fecha_corte_client))->formatDate();
                    }

                    if ($val == 'fecha_pago') {
                        $value->fecha_pago = (new FormatDateService($value->fecha_pago))->formatDate();
                    }

                    if ($val == 'fecha_fin_periodo_gracia') {
                        $value->fecha_fin_periodo_gracia = (new FormatDateService($value->fecha_fin_periodo_gracia))->formatDate();
                    }

                    if ($val == 'last_payment') {
                        $value->last_payment = (new FormatDateService($value->last_payment))->formatDate();
                    }

                    $nestedData[$val] = view($dirColumn, [
                        'dir' => '/cliente/editar/' . $value->id,
                        'value' => $value,
                        'column' => $val,
                        'estado' => $estado
                    ])->toHtml();
                }

                $nestedData['action'] = view('meganet.shared.table.module.client.actions', [
                    'id' => $id,
                    'module' => 'cliente',
                    'group' => 'client',
                    'submodule' => 'client',
                    'userPermissions' => $userPermissions
                ])->toHtml();
                $data[] = $nestedData;
            }
        }

        return [
            "draw" => intval($request['request']->input('draw')),
            "recordsTotal" => intval($request['totalData']),
            "recordsFiltered" => intval($request['totalFiltered']),
            "data" => $data,
            'color_datatable' => $colorDatatable
        ];
    }

    public function getNetworksIpIdForClient($clientId)
    {
        $ips = [];
        $servicesInternet = ClientInternetService::where('client_id', $clientId)->get();
        foreach ($servicesInternet as $service) {
            $networkIpService = new NetworkIpService($service);
            $ip = $networkIpService->getClientIp();
            $ips[] = $ip;
        }

        $customServices = ClientCustomService::whereHas('network_ip')->where('client_id', $clientId)->get();
        foreach ($customServices as $service) {
            $networkIpService = new NetworkIpService($service);
            $ip = $networkIpService->getClientIp();
            $ips[] = $ip;
        }
        $ips = array_unique($ips);
        $ips = implode(', ', $ips);
        return $ips;
    }

    public function columnsSelected()
    {
        return [
            'clients.id as id',
            'clients.fecha_corte',
            'clients.fecha_pago',
            'clients.fecha_fin_periodo_gracia',
            'client_main_information.address',
            'client_main_information.estado as client_main_information_estado',
            'client_main_information.password',
            'client_main_information.name',
            'client_main_information.father_last_name',
            'client_main_information.mother_last_name',
            'client_main_information.estado',
            'client_main_information.phone',
            'client_main_information.phone2',
            'client_main_information.email',
            'client_main_information.street',
            'client_main_information.zip',
            'client_main_information.external_number',
            'client_main_information.internal_number',
            'client_main_information.created_at',
            'client_main_information.updated_at',
            'client_main_information.nif_pasaport',
            'client_main_information.seller_id',

            'type_billings.type as type_of_billing_id',

            'client_additional_information.category',
            'client_additional_information.modem_sn',
            'client_additional_information.gpon_ont',
            'client_additional_information.power_dbm',
            'client_additional_information.olt_power_dbm',
            'client_additional_information.original_password',
            'client_additional_information.box_nomenclator',
            'client_additional_information.box_nomenclator_old',
            'client_additional_information.user_film',
            'client_additional_information.password_film',
            'client_additional_information.password_wifi',
            'client_additional_information.reinstatement',
            'client_additional_information.social_id',
            'client_additional_information.comment',
            'client_additional_information.installation_on_time',
            'client_additional_information.amount_technician_and_why',
            'client_additional_information.doubt_signed_contract',
            'client_additional_information.technician_attencion',
            'client_additional_information.last_time_online',

            'billing_configurations.billing_activated',
            'billing_configurations.period',
            'billing_configurations.billing_date',
            'billing_configurations.billing_expiration',
            'billing_configurations.grace_period',
            'billing_configurations.autopay_invoice',
            'billing_configurations.send_financial_notification',
            'method_of_payments.type as payment_method_id',

            'type_billings.type as type_billing_id',

            'reminders_configurations.activate_reminders',
            'reminders_configurations.type_of_message',
            'reminders_configurations.reminder_1_days',
            'reminders_configurations.reminder_2_days',
            'reminders_configurations.reminder_3_days',
            'reminders_configurations.reminder_payment_3',
            'reminders_configurations.reminder_payment_amount',
            'reminders_configurations.reminder_payment_comment',

            'billing_addresses.billing_name',
            'billing_addresses.billing_street',
            'billing_addresses.billing_zip_code',
            'billing_addresses.billing_city',

            'balances.amount',

            'states.name as state_id',
            'municipalities.name as municipality_id',
            'colonies.name as colony_id',
            'partners.name as partner_id',
            'locations.name as location_id',

            'network_ips.ip as ip_ranges',

            'last_payment',

            'nomenclatures.name as nomenclature_name',

            'routers.title as router',
            'internets.title as internet_fees',
            'bundles.title as bundle_fees',
            'voises.title as voz_fees',
            'customs.title as custom_fees',
            'client_internet_services.user as service_user_name',
            'client_internet_services.mac as mac',
            DB::raw("CONCAT(client_main_information.name, ' ', client_main_information.father_last_name, ' ', client_main_information.mother_last_name) as full_name")
        ];
    }

    public function queryCustomFilter($model, $filters)
    {
        $flitrosAplicados = [
            'bundle_id' => 'bundle_service',
            'internet_id' => 'internet_service',
            'custom_id' => 'custom_service',
            'voz_id' => 'voz_service',
            'client_main_information.estado' => 'client_main_information',
            'fecha_pago_today' => [],
            'fecha_corte_today' => [],
        ];


        // Inicializa la consulta como una instancia del modelo
        $query = $model::query();

        foreach ($filters as $key => $values) {
            foreach ($values as $keyV => $val) {
                $relation = $flitrosAplicados[$keyV] ?? null;
                if ($val == 'null') {
                    continue; // Si el valor es 'null', pasa al siguiente filtro
                }
                if ($keyV === 'fecha_corte') {
                    if (is_array($val) && count($val) == 2) {
                        $query->whereDate('clients.fecha_corte', '>=', Carbon::parse($val[0])->subDay()->format('Y-m-d'))
                            ->whereDate('clients.fecha_corte', '<', Carbon::parse($val[1])->format('Y-m-d'));
                    }
                } elseif ($keyV === 'fecha_pago_today') {
                    $query->whereDate('clients.fecha_pago', Carbon::parse($val)->format('Y-m-d'));
                } elseif ($keyV === 'fecha_corte_today') {
                    $query->whereDate('clients.fecha_corte', Carbon::parse($val)->format('Y-m-d'));
                } elseif ($relation) {
                    if ($keyV === 'client_main_information.estado') {
                        $query = $query->whereHas($relation, function ($query) use ($keyV, $val) {
                            $query->whereIn($keyV, $val);
                        })->with($relation);
                    } elseif ($keyV === 'internet_id') {
                        $query = $query->whereHas('internet_service', function ($query) use ($keyV, $val) {
                            $query->where($keyV, $val)
                                ->whereNull('client_bundle_service_id');
                        })->with('internet_service');
                    } elseif ($keyV === 'custom_id') {
                        $query = $query->whereHas('custom_service', function ($query) use ($keyV, $val) {
                            $query->where($keyV, $val)
                                ->whereNull('client_bundle_service_id');
                        })->with('custom_service');
                    } elseif ($keyV === 'voz_id') {
                        $query = $query->whereHas('voz_service', function ($query) use ($keyV, $val) {
                            $query->where($keyV, $val)
                                ->whereNull('client_bundle_service_id');
                        })->with('voz_service');
                    } else {
                        $query = $query->whereHas($relation, function ($query) use ($keyV, $val) {
                            $query->where($keyV, $val);
                        })->with($relation);
                    }
                } else {
                    $query = $query->where($keyV, $val);
                }
            }
        }
        return $query;
    }


    public function transformFilter($object)
    {
        return collect($object)->map(function ($item, $key) {
            $result = [];
            $fromString = false;
            if (!is_array($item)) {
                $item = json_decode($item, true);
                $fromString = true;
            }

            foreach ($item as $type => $values) {
                if ($fromString) {
                    $result[array_keys($values)[0]] = array_values($values)[0];
                    continue;
                }
                $result[$type] = $values;
            }
            return $result;
        })->toArray();
    }


    public function fetch_datatable_data($request)
    {
        if (empty($request->data['columns'])) {
            return [
                "draw" => 0,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => []
            ];
        }

        $columns = $request->data['columns'];
        $columns = $this->getColumns($columns);
        $filters = $this->getFiltersFromRequest($request);
        $idModule = $this->getIdModuleFromRequest($request);
        $totalData = $this->countTotalData($idModule, $filters, $request, $columns);

        $totalFiltered = $totalData;

        if ($request->limit == 0) {
            set_time_limit(0);
            ini_set('memory_limit', '8912M');
        }
        // Definir límites y paginación
        $limit = $request->limits == 0 ? $totalFiltered : $request->limits;
        $start = $request->start ?? 0;
        $order = isset($request->order) ? $request->order : $request->data['columns'][0]['data'];
        $dir = $request->dir == false ? 'DESC' : 'ASC';

        // Obtener los datos según el estado de búsqueda
        $array = $this->hasSearchTerm($request)
            ? $this->searching_query($start, $limit, $order, $dir, $request->data['search'], $idModule ?? $filters, $columns)
            : $this->ordering_query($start, $limit, $order, $dir, $idModule ?? $filters, $columns);

        // Transformar y devolver los datos
        $param_resource = collect([
            'array' => $array,
            'totalData' => $totalData,
            'totalFiltered' => $totalFiltered,
            'request' => $request
        ]);

        return $this->transform($param_resource);
    }

    public function getColumns($columns)
    {
        $cols = [];
        foreach ($columns as $col) {
            $cols[] = $col['data'];
        }

        return $cols;
    }

    private function getFiltersFromRequest($request)
    {
        $filters = [];

        if ($this->hasFilters($request)) {
            if (is_array($request->data['filters'])) {
                $filters = $request->data['filters'];
            } else {
                $filters = $this->transformFilter($request->data['filters']);
            }
        }

        if ($this->hasAdditionalFilters($request)) {
            $filters[] = $request->data['additionalFilter'];
        }

        if (isset($request->persistentFilter) && !empty($request->persistentFilter)) {
            $array = $this->formatPersistentFilter($request->persistentFilter);

            $filters[$array['key']] = $array['value'];
        }
        return $filters;
    }

    private function formatPersistentFilter($persistentFilter)
    {
        $key = key($persistentFilter); // obtiene la clave del array (e.g., "owner_id")
        $value = $persistentFilter[$key]; // obtiene el valor asociado a esa clave

        // Si el valor no es un array, lo transformamos en uno
        if (!is_array($value)) {
            $value = [$value];
        }

        return [
            'key' => $key,
            'value' => $value
        ];
    }

    private function countTotalData($idModule, $filters, $request, $columns = null)
    {

        if (!empty($filters) && empty($request->data['search'])) {
            return $this->count($filters);
        }

        if (!empty($filters) && !empty($request->data['search'])) {
            return $this->filtering_query($request->data['search'], $columns, $filters);
        }

        if ($this->hasSearchTerm($request)) {
            return $this->filtering_query($request->data['search'], $columns);
        }

        return $this->count();
    }

    private function getIdModuleFromRequest($request)
    {
        return $this->hasIdModule($request) ? $request->data['idModule'] : null;
    }

    private function hasIdModule($request)
    {
        return isset($request->data['idModule']);
    }

    private function hasFilters($request)
    {
        return !empty($request->data['filters']);
    }

    private function hasAdditionalFilters($request)
    {
        return !empty($request->data['additionalFilter']);
    }

    private function hasSearchTerm($request)
    {
        return !empty($request->data['search']);
    }


    private function exportData($request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '8912M');
        // Obtenemos y procesamos columnas seleccionadas
        $columns = $this->getColumns($request->data['columns']);

        $filters = $this->getFiltersFromRequest($request);
        $idModule = $this->getIdModuleFromRequest($request);

        $totalData = $this->countTotalData($idModule, $filters, $request, $columns);
        $limit = $request->limits == 0 ? $totalData : $request->limits;
        $start = $request->start ?? 0;

        $order = $request->order ?? $request->data['columns'][0]['data'];
        $dir = $request->dir == false ? 'DESC' : 'ASC';

        $queryResult = $this->ordering_query($start, $limit, $order, $dir, $idModule ?? $filters);

        // Transformamos los datos en el formato requerido y evitamos bucles anidados
        $data = $queryResult->map(function ($item) use ($columns) {
            return collect($columns)->mapWithKeys(function ($col) use ($item) {
                return [$col => $item->$col ?? null];
            })->all();
        });

        return ['data' => $data];
    }
}
