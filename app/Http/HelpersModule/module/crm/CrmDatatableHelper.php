<?php


namespace App\Http\HelpersModule\module\crm;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Http\Traits\DatatableCoreTrait;
use App\Modules\Core\CRM\Models\Crm;
use App\Models\Module;

class CrmDatatableHelper extends HelperDatatable
{
    use DatatableCoreTrait;

    private $model;
    private $columns;
    public function __construct()
    {
        $this->model = Crm::class;
        $moduleName = 'Crm';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
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

        $filters = $this->getFiltersFromRequest($request);
        if (count($filters) > 0) {
            $filters = [
                [
                    'column' => 'owner_id',
                    'value' => $filters['owner_id'][0]
                ]
            ];
        }
        $filters[] = [
            'type' => 'date',
            'operator' => '>=',
            'column' => 'created_at',
            'value' => '2024-06-01'
        ];
        $columns =  $request->data['columns'];

        $limit = $request->limits ?? 50;
        $start = $request->start ?? 0;
        $order = isset($request->order) ? $request->order : $columns[0];
        $dir = $request->dir === true ? 'DESC' : 'ASC';

        $mapping = $this->getColumnMapping();
        $query  = $this->getGeneralQuery($columns, $mapping);
        $query = $this->applyFilters($query, $filters, $mapping);
        $query = $this->applySearch($query, $request->data['search'] ?? null, $columns, $mapping);
        $countQuery = clone $query;
        $query = $this->applySorting($query, $order, $dir, $mapping);

        if ($this->isQueryEmpty($countQuery)) {
            $total = 0;
        } else {
            $total = $countQuery->count();
        }

        if ($total === 0) {
            $arrayData = collect([]);
        } else {
            $arrayData = $query->skip($start)->limit($limit)->get();
        }
        $param_resource = collect([
            'array' => $arrayData,
            'totalData' => $total,
            'totalFiltered' => $total,
            'request' => $request
        ]);
        return $this->transform($param_resource);
    }

    public function count($filters = null, $search = null)
    {
        if (!empty($filters)) {
            $query = $this->queryCustomFilter($this->model, $filters, $search);
            $count = $query->count();
            return $count;
        }
        return $this->model::count();
    }

    public function queryCustomFilter($model, $filters, $search)
    {
        $query = $model::query();
        if (isset($filters) && isset($filters['owner_id'])) {
            $query->where('created_at', '>=', '2024-06-01');
        }
        foreach ($filters as $field => $values) {
            if ($field == 'owner_id') {
                $query->whereHas('crm_lead_information', function ($query) use ($values) {
                    $query->whereIn('owner_id', $values);
                });
            }
        }

        if ($search != null || $search != '') {
        }

        return $query;
    }

    public function ordering_query($start, $limit, $order, $dir, $filters)
    {
        $query = $this->model::select($this->fieldsDatatable())
            ->leftJoin('crm_main_information', 'crms.id', '=', 'crm_main_information.crm_id')
            ->leftJoin('crm_lead_information', 'crms.id', '=', 'crm_lead_information.crm_id')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);

        if (!empty($filters)) {
            if (isset($filters['owner_id'])) {
                $query->where('crms.created_at', '>=', '2024-06-01');
            }
            $query = $query->filters($this->columns, null, $filters);
        }

        return $query->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $filters = null, $columns = null)
    {
        $query = $this->model::filters($this->columns, $search, $filters)
            ->select($this->fieldsDatatable())
            ->leftJoin('crm_main_information', 'crms.id', '=', 'crm_main_information.crm_id')
            ->leftJoin('crm_lead_information', 'crms.id', '=', 'crm_lead_information.crm_id')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);
        if (isset($filters) && isset($filters['owner_id'])) {
            $query->where('crms.created_at', '>=', '2024-06-01');
        }
        return $query->get();
    }

    public function filtering_query($search, $columns = null, $filters = null)
    {
        $query = $this->model::filters($this->columns, $search, $filters)
            ->select($this->fieldsDatatable())
            ->leftJoin('crm_main_information', 'crms.id', '=', 'crm_main_information.crm_id')
            ->leftJoin('crm_lead_information', 'crms.id', '=', 'crm_lead_information.crm_id');
        return $query->count();
    }

    public function transform($request)
    {
        $data = array();
        $plazos = in_array('plazo_contrato', (array) $this->columns, true) && ! empty($request['array'])
            ? $this->plazosDeContrato(collect($request['array'])->pluck('id')->all())
            : [];
        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    if ($val == 'owner_id') {
                        $value->owner_id = $value->seller_name;
                    }
                    if ($val == 'plazo_contrato') {
                        $value->plazo_contrato = $plazos[$value->id] ?? '';
                    }

                    $nestedData[$val] = view('meganet.shared.table.column', [
                        'dir' => '/crm/editar/' . $value->id,
                        'value' => $value,
                        'column' => $val,
                    ])->toHtml();
                }

                $nestedData['action'] = view('meganet.shared.table.actions', [
                    'id' => $id,
                    'module' => 'crm',
                    'group' => 'crm',
                    'submodule' => 'crm'
                ])->toHtml();
                $data[] = $nestedData;
            }
        }

        return [
            "draw" => intval($request['request']->input('draw')),
            "recordsTotal" => intval($request['totalData']),
            "recordsFiltered" => intval($request['totalFiltered']),
            "data" => $data
        ];
    }

    /**
     * FR-03 (#537): «18 Meses» o «Especial (10 meses)» por prospecto, en una sola consulta.
     * @return array<int,string> crm_id → texto
     */
    private function plazosDeContrato(array $crmIds): array
    {
        $nombres = \Illuminate\Support\Facades\DB::table('duration_contracts')->pluck('name', 'id')->all();
        $especial = \App\Support\PlazoContrato::idEspecial();
        $r = [];
        foreach (\Illuminate\Support\Facades\DB::table('crm_main_information')->whereIn('crm_id', $crmIds)
            ->get(['crm_id', 'duration_contract_id', 'plazo_especial_meses']) as $m) {
            if (! $m->duration_contract_id) {
                continue;
            }
            $nombre = $nombres[$m->duration_contract_id] ?? '';
            $r[$m->crm_id] = ($especial && (int) $m->duration_contract_id === $especial && $m->plazo_especial_meses)
                ? "{$nombre} ({$m->plazo_especial_meses} meses)"
                : $nombre;
        }

        return $r;
    }

    protected function fieldsDatatable()
    {
        return [
            'crms.id',

            'crm_main_information.name',
            'crm_main_information.father_last_name',
            'crm_main_information.mother_last_name',
            'crm_main_information.email',
            'crm_main_information.email_is_required',
            'crm_main_information.phone',
            'crm_main_information.phone2',
            'crm_main_information.nif_pasaport',
            'crm_main_information.partner_id',
            'crm_main_information.location_id',
            'crm_main_information.high_date',
            'crm_main_information.street',
            'crm_main_information.external_number',
            'crm_main_information.internal_number',
            'crm_main_information.zip',
            'crm_main_information.colony_id',
            'crm_main_information.municipality_id',
            'crm_main_information.state_id',

            'crm_lead_information.score',
            'crm_lead_information.last_contacted',
            'crm_lead_information.instalation_date',
            'crm_lead_information.crm_techical_user_id',
            'crm_lead_information.crm_status',
            'crm_lead_information.owner_id',
            'crm_lead_information.state_id',
            'crm_lead_information.municipality_id',
            'crm_lead_information.colony_id',
            'crm_lead_information.source',
        ];
    }

    public function transformFilter($object)
    {
        if (is_null($object) || (is_array($object) && in_array(null, $object, true))) {
            return [];
        }
        return collect($object)->map(function ($item, $key) {
            $result = [];
            foreach ($item as $type => $values) {
                $result[$type] = $values;
            }
            return $result;
        })->toArray();
    }

    protected function getBaseColumnsByTable()
    {
        return [
            'crms' => [
                'id' => ['searchable' => true],
                'created_at' => ['searchable' => true],
            ],
            'crm_main_information' => [
                'name' => ['searchable' => true],
                'father_last_name' => ['searchable' => true],
                'mother_last_name' => ['searchable' => true],
                'email' => ['searchable' => true],
                'phone' => ['searchable' => true],
                'phone2' => ['searchable' => true],
                'nif_pasaport' => ['searchable' => true],
                'high_date' => ['searchable' => true],
                'street' => ['searchable' => true],
                'external_number' => ['searchable' => true],
                'internal_number' => ['searchable' => true],
                'zip' => ['searchable' => true],
                // FR-03 (#537): plazo de contrato (solo lectura); transform() lo vuelve texto.
                'plazo_contrato' => ['column' => 'duration_contract_id', 'searchable' => false],
            ],
            'states' => [
                'state_str' => [
                    'column' => 'name',
                    'searchable' => true
                ]
            ],
            'colonies' => [
                'colony_str' => [
                    'column' => 'name',
                    'searchable' => true
                ]
            ],
            'municipalities' => [
                'municipality_str' => [
                    'column' => 'name',
                    'searchable' => true
                ]
            ],
            'crm_lead_information' => [
                'score' => ['searchable' => true],
                'last_contacted' => ['searchable' => true],
                'crm_status' => ['searchable' => true],
                'owner_id'
            ],
            'users' => [
                'owner_str' => [
                    'column' => 'name',
                    'searchable' => true
                ]
            ]
        ];
    }

    protected function getJoinConfiguration()
    {
        return [
            'crm_main_information' => [
                'type' => 'join',
                'on'   => ['crm_main_information.crm_id', '=', 'crms.id'],
            ],
            'states' => [
                'type' => 'join',
                'on'   => ['states.id', '=', 'crm_main_information.state_id'],
            ],
            'colonies' => [
                'type' => 'join',
                'on'   => ['colonies.id', '=', 'crm_main_information.colony_id'],
            ],
            'municipalities' => [
                'type' => 'join',
                'on'   => ['municipalities.id', '=', 'crm_main_information.municipality_id'],
            ],
            'crm_lead_information' => [
                'type' => 'join',
                'on'   => ['crm_lead_information.crm_id', '=', 'crms.id'],
            ],
            'users' => [
                'type' => 'join',
                'on'   => ['users.id', '=', 'crm_lead_information.owner_id'],
            ],
        ];
    }
}
