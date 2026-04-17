<?php

namespace App\Http\Controllers\Module\OLTs;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\Olt;
use App\Models\OltCard;
use App\Models\OltInterruptionPon;
use App\Models\OltOdb;
use App\Models\OltOnu;
use App\Models\OltSpeedProfile;
use App\Models\OltTypeONU;
use App\Models\OltUnconfiguredOnu;
use App\Models\OltUplinkPort;
use App\Models\OltVlan;
use App\Models\OltZone;
use App\Services\OLTsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mavinoo\Batch\Batch;

class OLTsController extends Controller
{

    use DatatableCoreTrait;

    protected $oltService;
    protected $ttl;
    private $model;

    public function __construct()
    {
        $this->oltService = new OLTsService();
        $this->ttl = config('services.smartolt.ttl');
        $this->model = Olt::class;
    }

    public function dashboard()
    {
        return view('meganet.module.olts.dashboard');
    }

    public function panel()
    {
        return view('meganet.module.olts.panel', [
            'notifications' => $this->userNotification()
        ]);
    }

    public function index(Request $request)
    {
        if ($request->force) {
            Artisan::call('smartolt:sync-inventory', ['--only' => 'olts']);
        }
        $columns =  $request->columns;
        $order = $request->sortBy ?? $columns[0];
        $dir = $request->descending ? 'DESC' : 'ASC';
        $mapping = $this->getColumnMapping();
        $query  = $this->getGeneralQuery($columns, $mapping);
        $query = $this->applySearch($query, $request->search ?? null, $columns, $mapping);
        $query = $this->applySorting($query, $order, $dir, $mapping);
        $objects = $query->paginate(isset($request->rowsPerPage) ? $request->rowsPerPage : 20, ['*'], 'page', isset($request->page) ? $request->page : null);
        return response()->json(
            [
                'objects' => $objects->items(),
                'total' => $objects->total()
            ]
        );
    }

    protected function getBaseColumnsByTable()
    {
        return [
            'olts' => [
                'id' => ['searchable' => true],
                'name' => ['searchable' => true],
                'olt_hardware_version' => ['searchable' => true],
                'ip' => ['searchable' => true],
                'status' => ['searchable' => true],
                'snmp_port' => ['searchable' => true],
                'telnet_port' => ['searchable' => true],
                'env_temp' => ['searchable' => true],
                'uptime' => ['searchable' => false],
                'last_synced_at' => ['searchable' => false],
            ]
        ];
    }

    public function oltList()
    {
        $message = null;
        $rows = Olt::with(['cards', 'vlans'])->get();
        return response()->json([
            'success' => true,
            'rows' => $rows,
            'message' => $message
        ]);
    }

    public function zones(Request $request)
    {
        $message = null;
        if ($request->force) {
            Artisan::call('app:sync-zones');
        }
        $data = OltZone::all();
        return response()->json([
            'success' => true,
            'rows' => $data,
            'message' => $message
        ]);
    }

    public function typeONUs(Request $request)
    {
        $message = null;
        if ($request->force) {
            Artisan::call('app:sync-type-onus');
        }
        $data = OltTypeONU::all();
        return response()->json([
            'success' => true,
            'rows' => $data,
            'message' => $message
        ]);
    }

    public function oltCardsDetails(Request $request, $id)
    {
        $message = null;
        if ($request->force) {
            $olt = Olt::find($id);
            if (isset($olt)) {
                $respose = $olt->syncCards();
                if (!$respose['success']) {
                    $message = $respose['message'];
                }
            }
        }
        $data = OltCard::where('olt_id', $id)->get();
        return response()->json([
            'success' => true,
            'rows' => $data,
            'message' => $message
        ]);
    }

    public function getOltPonPortsDetails(Request $request, $id)
    {
        $message = null;
        $olt = Olt::find($id);
        $cacheKey = 'olt_sync_pons';
        if (isset($olt)) {
            if ($request->force) {
                $respose = $olt->syncPonPorts();
                if (!$respose['success']) {
                    $message = $respose['message'];
                }
            } else {
                if (!Cache::has($cacheKey)) {
                    $lockKey = 'olt_updating_pons';
                    $lock = Cache::lock($lockKey, 10)->get();
                    if ($lock) {
                        try {
                            $respose = $olt->syncPonPorts();
                            if (!$respose['success']) {
                                $message = $respose['message'];
                            }
                            Cache::put($cacheKey, 'fresh', $this->ttl);
                        } finally {
                            optional(Cache::lock($lockKey))->release();
                        }
                    }
                }
            }
        }
        $data = $olt->ponPorts ?? [];
        return response()->json([
            'success' => true,
            'rows' => $data,
            'message' => $message
        ]);
    }

    public function getOutagePons(Request $request, $id = null)
    {
        $rows = [];
        if (isset($id)) {
            $olt = Olt::find($id);
            $rows = $olt->interruptions;
        } else {
            $rows = OltInterruptionPon::orderBy('latest_status_change', 'DESC')->get();
        }
        return response()->json([
            'success' => true,
            'rows' => $rows,
            'message' => null
        ]);
    }

    public function getOltUplinkPortsDetails(Request $request, $id)
    {
        $message = null;
        if ($request->force) {
            $olt = Olt::find($id);
            if (isset($olt)) {
                $respose = $olt->syncUplinkPorts();
                if (!$respose['success']) {
                    $message = $respose['message'];
                }
            }
        }
        $data = OltUplinkPort::where('olt_id', $id)->get();
        return response()->json([
            'success' => true,
            'rows' => $data,
            'message' => $message
        ]);
    }

    public function getVLANs(Request $request, $id)
    {
        $message = null;
        if ($request->force) {
            $olt = Olt::find($id);
            if (isset($olt)) {
                $respose = $olt->syncVLANs();
                if (!$respose['success']) {
                    $message = $respose['message'];
                }
            }
        }
        $data = OltVlan::where('olt_id', $id)->get();
        return response()->json([
            'success' => true,
            'rows' => $data,
            'message' => $message
        ]);
    }

    public function getDetailsByONU($id)
    {
        $onu = OltOnu::find($id);
        if ($onu && $onu->unique_external_id) {
            $response = $this->oltService->syncOnu($onu);
            return response()->json($response);
        }
        return response()->json([
            'success' => false,
            'message' => 'No se permite la operación solicitada. ONU no encontrada'
        ]);
    }

    public function getSignalByOnu($sn)
    {
        $response = $this->oltService->syncSignal($sn);
        return $response;
    }

    public function getUptimeEnvTemp()
    {
        $respose = $this->oltService->getUpTimeEnviromentTemperatureByOlt();
        if ($respose['success']) {
            $now = now();
            $instance = new Olt;
            $data = collect($respose['response'])->map(function ($obj) use ($now) {
                return [
                    'id' => $obj['olt_id'],
                    'uptime' => $obj['uptime'],
                    'env_temp' => $obj['env_temp'],
                    'updated_at' => $now,
                    'last_synced_at' => $now,
                ];
            })->toArray();
            $batch = new Batch(app('db'));
            $chuncks = array_chunk($data, 500);
            DB::transaction(function () use ($chuncks, $batch, $instance) {
                foreach ($chuncks as $ch) {
                    $batch->update($instance, $ch, 'id');
                }
            });
        }
        $data = Olt::all();
        return response()->json([
            'success' => true,
            'rows' => $data
        ]);
    }

    public function getDashboardInterruptions(Request $request)
    {
        if ($request->force) {
            Artisan::call('olt:sync-interruptions-pon');
        } else {
            $cacheKey = 'olt_sync_interruptions';
            $lockKey = 'olt_updating_interruptions_lock';
            $ttl = config('services.smartolt.ttl');
            if (!Cache::has($cacheKey)) {
                $lock = Cache::lock($lockKey, 10)->get();
                if ($lock) {
                    try {
                        Artisan::call('olt:sync-interruptions-pon');
                        Cache::put($cacheKey, 'fresh', $ttl);
                    } finally {
                        optional(Cache::lock($lockKey))->release();
                    }
                }
            }
        }
        $olts = Olt::withCount(['interruptions as total_interruptions'])
            ->withMax('interruptions as last_sync', 'last_synced_at')
            ->get()
            ->map(function ($olt) {
                return [
                    'id' => $olt->id,
                    'name' => $olt->name,
                    'interruption_count' => $olt->total_interruptions,
                    'last_sync' => $olt->last_sync
                        ? Carbon::parse($olt->last_sync)->diffForHumans()
                        : '-',
                    'status' => $olt->total_interruptions > 0 ? 'warning' : 'stable'
                ];
            });
        return response()->json($olts);
    }

    public function getDashboardOnusStatus($id = null)
    {
        $stats = DB::table('olt_onus')
            ->select('status', 'signal', DB::raw('count(*) as count'))
            ->when($id, fn($query) => $query->where('olt_id', $id))
            ->groupBy('status', 'signal')
            ->get();

        $data = [];

        foreach ($stats->groupBy('status') as $group) {
            $s = Str::lower(str_replace(' ', '', $group->first()->status));
            $data[!isset($n) && !empty($s) ? $s : 'na'] = $group->sum('count');
        }

        foreach ($stats->whereIn('signal', ['Warning', 'Critical'])->groupBy('signal') as $group) {
            $data[Str::lower(str_replace(' ', '', $group->first()->signal))] = $group->sum('count');
        }

        $data['waiting'] = OltUnconfiguredOnu::when($id, fn($q) => $q->where('olt_id', $id))->count();

        return response()->json($data);
    }

    public function nomenclatures(Request $request)
    {
        $only = $request->nomenclatures;
        $data = [];
        $sqlClient = "SELECT client_id id, CONCAT(client_id, ' - ', NAME, ' ', COALESCE(father_last_name, ' '), ' ', COALESCE(mother_last_name, ' ')) name, address FROM client_main_information where estado='Activo' order by 1";
        if ($only) {
            if (in_array('type_onus', $only)) {
                $data['type_onus'] = OltTypeONU::all();
            }
            if (in_array('zones', $only)) {
                $data['zones'] = OltZone::orderBy('name', 'ASC')->get();
            }
            if (in_array('clients', $only)) {
                $data['clients'] = DB::select($sqlClient);
            }
            if (in_array('speed_profiles', $only)) {
                $data['speed_profiles'] = OltSpeedProfile::all();
            }
            if (in_array('odbs', $only)) {
                $data['odbs'] = OltOdb::all();
            }
            if (in_array('vlans', $only)) {
                $data['vlans'] = OltVlan::all();
            }
        } else {
            $typeOnus = OltTypeONU::all();
            $zones = OltZone::orderBy('name', 'ASC')->get();
            $clients = DB::select($sqlClient);
            $speedProfiles = OltSpeedProfile::all();
            $odbs = OltOdb::all();
            $data = [
                'type_onus' => $typeOnus,
                'zones' => $zones,
                'clients' => $clients,
                'speed_profiles' => $speedProfiles,
                'odbs' => $odbs
            ];
        }
        return response()->json($data);
    }

    public function storeOnu(Request $request)
    {
        $response = $this->oltService->registerOnu($request->all());
        $onu = null;
        if ($response['success']) {
            $data = null;
            if (isset($request->onu_external_id)) {
                $data = $this->oltService->getOnuDetailsByExternalId($request->onu_external_id);
                if ($data['success']) {
                    $onu = $data['onu_details'];
                }
            } else {
                $data = $this->oltService->getOnuDetailsBySN($request->sn);
                if ($data['success']) {
                    $onu = $data['onus'][0] ?? null;
                }
            }
        }
        $obj = null;
        if ($onu !== null) {
            $onu = $this->oltService->getNormalizedData($onu);
            $obj = OltOnu::create($onu);
        }
        return response()->json([
            ...$response,
            'onu' => $obj
        ]);
    }

    public function resyncOnuConfig($id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->resyncONUConfig($onu->unique_external_id);
                return response()->json($response);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se permite la operación solicitada. La ONU no tiene configurado el ID externo'
                ]);
            }
        }
        return response()->json([
            'success' => false,
            'message' => 'No se permite la operación solicitada. ONU no encontrada'
        ]);
    }

    public function enableDisableOnu(Request $request, $id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            $enable = $request->enable ?? false;
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->enableDisableONU($onu->unique_external_id, $enable);
                if ($response['success']) {
                    $onu->administrative_status = $enable ? 'Enabled' : 'Disabled';
                    $onu->save();
                }
                return response()->json($response);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se permite la operación solicitada. La ONU no tiene configurado el ID externo'
                ]);
            }
        }
        return response()->json([
            'success' => false,
            'message' => 'No se permite la operación solicitada. ONU no encontrada'
        ]);
    }

    public function rebootOnu($id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->rebootONU($onu->unique_external_id);
                return response()->json($response);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se permite la operación solicitada. La ONU no tiene configurado el ID externo'
                ]);
            }
        }
        return response()->json([
            'success' => false,
            'message' => 'No se permite la operación solicitada. ONU no encontrada'
        ]);
    }

    public function moveOnu(Request $request, $id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->moveONU($onu->unique_external_id, $request->all());
                if ($response['success']) {
                    $olt = Olt::find($request->olt_id);
                    $onu->update([
                        ...$request->all(),
                        'olt_name' => $olt->name,
                        'last_synced_at' => now()
                    ]);
                    return [
                        'success' => true,
                        'onu' => $onu
                    ];
                }
                return response()->json($response);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se permite la operación solicitada. La ONU no tiene configurado el ID externo'
                ]);
            }
        }
        return response()->json([
            'success' => false,
            'message' => 'No se permite la operación solicitada. ONU no encontrada'
        ]);
    }

    public function updateOnuLocation(Request $request, $id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->updateONULocation($onu->unique_external_id, $request->all());
                if ($response['success']) {
                    $zone = OltZone::firstWhere('name', $request->zone);
                    $onu->update([
                        ...$request->except(['zone', 'odb']),
                        'odb_name' => $request->odb,
                        'zone_name' => $zone->name,
                        'zone_id' => $zone->id,
                        'last_synced_at' => now()
                    ]);
                    return [
                        'success' => true,
                        'onu' => $onu
                    ];
                }
                return response()->json($response);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se permite la operación solicitada. La ONU no tiene configurado el ID externo'
                ]);
            }
        }
        return response()->json([
            'success' => false,
            'message' => 'No se permite la operación solicitada. ONU no encontrada'
        ]);
    }

    public function nomenclaturesFromOnus(Request $request)
    {
        $label = $request->label;
        $value = $request->value;
        $query = DB::table('olt_onus');
        $from_json = ['upload_speed', 'download_speed', 'vlan'];

        if (in_array($label, $from_json)) {
            if ($label === 'vlan') {
                $rawData = $query->selectRaw("jt.vlan, jt.svlan, count(*) as count")
                    ->crossJoin(DB::raw("JSON_TABLE(service_ports, '$[*]' COLUMNS(
                            vlan  VARCHAR(255) PATH '$.vlan',
                            svlan VARCHAR(255) PATH '$.svlan'
                        )) as jt"))
                    ->whereNotNull('service_ports')
                    ->groupBy('jt.vlan', 'jt.svlan')
                    ->get();
                $vlanTotals = [];

                foreach ($rawData as $row) {
                    foreach (['vlan', 'svlan'] as $field) {
                        $id = $row->$field;
                        if (!empty($id)) {
                            if (!isset($vlanTotals[$id])) {
                                $vlanTotals[$id] = 0;
                            }
                            $vlanTotals[$id] += $row->count;
                        }
                    }
                }
                $vlanIds = array_keys($vlanTotals);

                $data = DB::table('olt_vlans')
                    ->whereIn('vlan', $vlanIds)
                    ->whereNotIn('description', ['Telefonia', 'Administrador'])
                    ->selectRaw("vlan as vlan_id, IF(description IS NULL OR description = '', CAST(vlan AS CHAR), CONCAT(vlan, ' - ', description)) as vlan_label")
                    ->orderBy('vlan', 'ASC')
                    ->distinct()
                    ->get()
                    ->map(function ($item) use ($vlanTotals) {
                        return [
                            'vlan' => $item->vlan_label,
                            'vlan_id' => $item->vlan_id,
                            'count' => $vlanTotals[$item->vlan_id] ?? 0
                        ];
                    });
            } else {
                $rawData = $query->selectRaw("jt.val as val, count(*) as count")
                    ->crossJoin(DB::raw("JSON_TABLE(service_ports, '$[*].{$label}' COLUMNS(val VARCHAR(255) PATH '$')) as jt"))
                    ->whereNotNull('service_ports')
                    ->whereRaw("jt.val IS NOT NULL AND jt.val != ''")
                    ->groupBy('jt.val')
                    ->get();
                $data = $rawData->map(fn($item) => [
                    $label => $item->val,
                    'count' => $item->count
                ]);
            }
        } else {
            $filters = $request->input('filters') ?? null;
            if ($filters) {
                foreach ($filters as $key => $v) {
                    if (in_array($key, ['olt_id', 'board', 'port', 'zone_id', 'odb_name', 'vlan', 'onu_type_id', 'custom_template'])) {
                        $query->whereIn($key, (array)$v);
                    }
                }
            }
            $data = $query->select([$label, $value, DB::raw('count(*) as count')])
                ->whereNotNull($label)
                ->whereRaw("TRIM($label) != ''")
                ->groupBy([$label, $value])
                ->orderBy($label)
                ->get();
        }

        return response()->json($data);
    }
}
