<?php

namespace App\Modules\Addons\GestionRed\Controllers\OLTs;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\ClientAdditionalInformation;
use App\Models\Olt;
use App\Models\OltOnu;
use App\Models\OltUnconfiguredOnu;
use App\Services\OLTsService;
use App\Services\OltDriver\OltDriverManager;
use Illuminate\Http\Request;

class OLTsOnuController extends Controller
{
    use DatatableCoreTrait;

    protected $oltService;
    protected $ttl;
    private $model;
    private OltDriverManager $driverManager;

    public function __construct(OltDriverManager $driverManager)
    {
        $this->oltService    = new OLTsService();
        $this->ttl           = config('services.smartolt.ttl');
        $this->model         = OltOnu::class;
        $this->driverManager = $driverManager;
    }

    public function index(Request $request)
    {
        $params = $request->params;
        if ($request->force) {
            if (isset($params['olt_id'])) {
                $olts = Olt::whereIn('id', is_array($params['olt_id']) ? $params['olt_id'] : [$params['olt_id']])->get();
            } else {
                $olts = Olt::all();
            }
            foreach ($olts as $o) {
                $o->syncOnus();
            }
        }
        $columns =  $request->columns;
        $order = $request->sortBy ?? 'sn';
        $dir = $request->descending ? 'DESC' : 'ASC';
        $mapping = $this->getColumnMapping();
        $query  = OltOnu::query();
        if (isset($params)) {
            $from_json = ['upload_speed', 'download_speed', 'tag_transform_mode', 'vlan'];
            foreach ($params as $key => $value) {
                if (in_array($key, $from_json)) {
                    $values = is_array($value) ? $value : [$value];
                    $query->where(function ($q) use ($key, $values) {
                        foreach ($values as $v) {
                            if ($key === 'vlan') {
                                $q->orWhereJsonContains('service_ports', ['vlan' => $v])
                                    ->orWhereJsonContains('service_ports', ['svlan' => $v]);
                            } else {
                                $q->orWhereJsonContains('service_ports', [$key => $v]);
                            }
                        }
                    });
                } else {
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    } else {
                        if ($value === 'Disabled' && $key === 'status') {
                            $key = 'administrative_status';
                        }
                        $query->where($key, $value);
                    }
                }
            }
        }
        $query = $this->applySearch($query, $request->search ?? null, $columns, $mapping);
        $query->orderBy($order, $dir);
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
            'olt_onus' => [
                'name' => ['searchable' => true],
                'sn' => ['searchable' => true],
                'unique_external_id' => ['searchable' => true],
                'administrative_status' => ['searchable' => true],
                'address' => ['searchable' => true],
                'onu' => ['searchable' => true],
                'authorization_date' => ['searchable' => true],
                'last_synced_at' => ['searchable' => true],
            ]
        ];
    }

    public function store(Request $request)
    {
        if (! auth()->user()->can('onu_add')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $data = $request->except('client_id');
        $response = $this->oltService->registerOnu($data);
        $onu = null;
        $obj = null;
        if ($response['success']) {
            $data = null;
            if (isset($request->onu_external_id)) {
                $data = $this->oltService->getOnuDetailsByExternalId($request->onu_external_id);
                if ($data['success']) {
                    $onu = $data['onu_details'];
                    $onu = $this->oltService->getNormalizedData($onu);
                    $obj = OltOnu::create($onu);
                }
            }
            $modem = ClientAdditionalInformation::firstWhere('client_id', $request->client_id);
            if ($modem) {
                $modem->gpon_ont = $request->onu_external_id;
                $modem->save();
            }
        }
        return response()->json([
            ...$response,
            'onu' => $obj
        ]);
    }

    public function remove($id)
    {
        if (! auth()->user()->can('onu_remove')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->removeONU($onu->unique_external_id);
                if ($response['success']) {
                    $modem = ClientAdditionalInformation::firstWhere('gpon_ont', $onu->unique_external_id);
                    if ($modem) {
                        $modem->gpon_ont = null;
                        $modem->save();
                    }
                    $onu->delete();
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

    public function getByClient($id)
    {
        $modem = ClientAdditionalInformation::firstWhere('client_id', $id);
        if ($modem && $modem->gpon_ont !== null) {
            $onu = OltOnu::firstWhere('unique_external_id', $modem->gpon_ont);
            return response()->json([
                'success' => true,
                'onu' => $onu
            ]);
        }
        return response()->json([
            'success' => true,
            'onu' => null
        ]);
    }

    public function getTrafficGraph($id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->getTrafficGraph($onu->unique_external_id);
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

    public function getSignalGrap($id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->getSignalGraph($onu->unique_external_id);
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

    public function getImageONU($id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->onu_type_id)) {
                $response = $this->oltService->getImageONU($onu->onu_type_id);
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

    public function getMgmTIp($id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            $response = $this->oltService->getMgmTIp($onu->unique_external_id);
            if ($response['success']) {
                $onu->mgmt_ip_address = $response['ip_address'];
                $onu->save();
            } else {
                return response()->json($response);
            }
        }
        return response()->json([
            'success' => true,
            'onu' => $onu
        ]);
    }

    public function getIpAddress($id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            $response = $this->oltService->getIpAddress($onu->unique_external_id);
            if ($response['success']) {
                $onu->ip_address = $response['ip_address'];
                $onu->save();
            } else {
                return response()->json($response);
            }
        }
        return response()->json([
            'success' => true,
            'onu' => $onu
        ]);
    }

    public function getSignalAndStatus($id)
    {
        $onu = OltOnu::find($id);
        if (! $onu) {
            return response()->json(['success' => false, 'message' => 'Onu no encontrada']);
        }

        $olt = Olt::find($onu->olt_id);
        if ($olt && $olt->driver === Olt::DRIVER_HUAWEI) {
            if (! $onu->unique_external_id) {
                return response()->json(['success' => false, 'message' => 'ONU sin ID externo configurado']);
            }

            // getOnuDetails returns both signal and status in one Telnet session
            $driver = $this->driverManager->driverFor($olt);
            $result = $driver->getOnuDetails($onu->unique_external_id);

            if ($result['syncing'] ?? false) {
                $onu->refresh();
                return response()->json(['success' => true, 'syncing' => true, 'onu' => $onu]);
            }

            if ($result['success'] ?? false) {
                $d = $result['onu_details'];
                $onu->fill([
                    'signal'         => $d['signal']      ?? $onu->signal,
                    'signal_1310'    => $d['signal_1310'] ?? $onu->signal_1310,
                    'signal_1490'    => $d['signal_1490'] ?? $onu->signal_1490,
                    'status'         => $d['status']      ?? $onu->status,
                    'last_synced_at' => now(),
                ])->save();
                $onu->refresh();
                return response()->json(['success' => true, 'onu' => $onu]);
            }

            return response()->json($result);
        }

        $response = $this->oltService->syncSignalsAndStatus($onu);
        return $response;
    }

    public function updateServicePort(Request $request, $id)
    {
        if (! auth()->user()->can('onu_edit')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->updateServicePort($onu->unique_external_id, $request->all());
                if ($response['success']) {
                    $data = $this->oltService->syncOnu($onu);
                    return [
                        'success' => true,
                        'onu' => $data['onu']
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

    public function configureEhernetPort(Request $request, $id)
    {
        if (! auth()->user()->can('onu_edit')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $request->status === 'Enabled' ? $this->oltService->configureEhernetPort($onu->unique_external_id, $request->attr_to_server, $request->mode) : $this->oltService->shutdownEhernetPort($onu->unique_external_id, $request->attr_to_server);
                if ($response['success']) {
                    $data = $this->oltService->syncOnu($onu);
                    return [
                        'success' => true,
                        'onu' => $data['onu']
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

    public function configureWifiPort(Request $request, $id)
    {
        if (! auth()->user()->can('onu_edit')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $request->status === 'Enabled' ? $this->oltService->configureWifiPort($onu->unique_external_id, $request->attr_to_server, $request->mode) : $this->oltService->shutdownWifiPort($onu->unique_external_id, $request->attr_to_server);
                if ($response['success']) {
                    $data = $this->oltService->syncOnu($onu);
                    return [
                        'success' => true,
                        'onu' => $data['onu']
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

    public function changeAttachedVlans(Request $request, $id)
    {
        if (! auth()->user()->can('onu_edit')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->changeAttachedVlans($onu->unique_external_id, [
                    'add_vlans' => implode(',', $request->add_vlans),
                    'remove_vlans' => implode(',', $request->remove_vlans)
                ]);
                if ($response['success']) {
                    $data = $this->oltService->syncOnu($onu);
                    return [
                        'success' => true,
                        'onu' => $data['onu']
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

    public function setOnuVoipPort(Request $request, $id)
    {
        if (! auth()->user()->can('onu_edit')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->setOnuVoipPort($onu->unique_external_id, $request->status, $request->attr_to_server ?? null);
                if ($response['success']) {
                    $data = $this->oltService->syncOnu($onu);
                    return [
                        'success' => true,
                        'onu' => $data['onu']
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

    public function updateChannel(Request $request, $id)
    {
        if (! auth()->user()->can('onu_edit')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->updateChannel($onu->unique_external_id, $request->all());
                if ($response['success']) {
                    $data = $this->oltService->syncOnu($onu);
                    return [
                        'success' => true,
                        'onu' => $data['onu']
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

    public function updateMgmtAndVoIp(Request $request, $id)
    {
        if (! auth()->user()->can('onu_edit')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $mgmtIp = $request['mgmtIp'];
                $response = $this->oltService->setOnuMgmtIp($onu->unique_external_id, $mgmtIp['attr_to_server'], $mgmtIp['mode']);
                if ($response['success']) {
                    $response = $this->oltService->updateMgmtAndVoIp($onu->unique_external_id, $request->all());
                    $tr069 = $response['tr069'] ?? null;
                    $voIp = $response['voIp'] ?? null;
                    $messages = [];
                    if ($response['success'] || $tr069['success'] || $voIp['success']) {
                        $data = $this->oltService->syncOnu($onu);
                        if ($tr069 && !$tr069['success']) {
                            $messages[] = 'TR069: ' . $tr069['response'];
                        }
                        if ($voIp && !$voIp['success']) {
                            $messages[] = 'VoIP: ' . $voIp['response'];
                        }
                        return [
                            ...$response,
                            'error' => empty($messages) ? null : 'Se procesó la solicitud con los siguientes errores: ' . implode($messages),
                            'onu' => $data['onu']
                        ];
                    }
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

    public function updateMode(Request $request, $id)
    {
        if (! auth()->user()->can('onu_edit')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->updateMode($onu->unique_external_id, $request->all());
                $vlan = $response['vlan'] ?? null;
                $wanMode = $response['wanMode'] ?? null;
                $onuMode = $response['onuMode'] ?? null;
                $messages = [];
                if ($response['success'] || $vlan['success'] || $wanMode['success'] || $onuMode['success']) {
                    $data = $this->oltService->syncOnu($onu);
                    if ($vlan && !$vlan['success']) {
                        $messages[] = 'VLAN principal: ' . $vlan['response'];
                    }
                    if ($wanMode && !$wanMode['success']) {
                        $messages[] = 'Modo WAN: ' . $wanMode['response'];
                    }
                    if ($onuMode && !$onuMode['success']) {
                        $messages[] = 'Modo ONU: ' . $onuMode['response'];
                    }
                    return [
                        ...$response,
                        'error' => empty($messages) ? null : 'Se procesó la solicitud con los siguientes errores: ' . implode($messages),
                        'onu' => $data['onu']
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

    public function changeOnuType(Request $request, $id)
    {
        if (! auth()->user()->can('onu_edit')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->changeOnuType($onu->unique_external_id, $request->all());
                $type = $response['type'] ?? null;
                $profile = $response['profile'] ?? null;
                $messages = [];
                if ($response['success'] || $type['success'] || $profile['success']) {
                    $data = $this->oltService->syncOnu($onu);
                    if ($type && !$type['success']) {
                        $messages[] = 'Tipo ONU: ' . $type['response'];
                    }
                    if ($profile && !$profile['success']) {
                        $messages[] = 'Perfil personalizado: ' . $profile['response'];
                    }
                    return [
                        ...$response,
                        'error' => empty($messages) ? null : 'Se procesó la solicitud con los siguientes errores: ' . implode($messages),
                        'onu' => $data['onu']
                    ];
                }
                return response()->json($response);
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

    public function sync($id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->syncOnu($onu);
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

    public function updateExternalId(Request $request, $id)
    {
        if (! auth()->user()->can('onu_edit')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $external_id = $onu->unique_external_id;
                $response = $this->oltService->updateExternalId($external_id, ['onu_external_id' => $request->onu_external_id ?? null]);
                if ($response['success']) {
                    $onu->update([
                        'unique_external_id' => $request->onu_external_id ?? null,
                        'last_synced_at' => now()
                    ]);
                    $modem = ClientAdditionalInformation::firstWhere('gpon_ont', $external_id);
                    if ($modem) {
                        $modem->gpon_ont = $request->onu_external_id;
                        $modem->save();
                    }
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

    public function getFullStatus($id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->getFullStatus($onu->unique_external_id);
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

    public function getRunningConfig($id)
    {
        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->getRunningConfig($onu->unique_external_id);
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

    public function changeWebUserPass($id)
    {
        if (! auth()->user()->can('onu_edit')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $response = $this->oltService->changeWebUserPass($onu->unique_external_id);
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

    public function setCATV(Request $request, $id)
    {
        if (! auth()->user()->can('onu_edit')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $onu = OltOnu::find($id);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $status = $request->status;
                $response = $this->oltService->setCATV($onu->unique_external_id, $status);
                if ($response['success']) {
                    $onu->catv = $status === 'enable' ? 'Enabled' : 'Disabled';
                    $onu->save();
                }
                return response()->json([
                    ...$response,
                    'onu' => $onu
                ]);
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

    public function getUnconfigured(Request $request, $id = null)
    {
        $message = null;
        $olt     = $id ? Olt::find($id) : null;

        if ($request->force) {
            if ($olt && $olt->driver === Olt::DRIVER_HUAWEI) {
                $driver = $this->driverManager->driverFor($olt);
                $result = $driver->getUnconfiguredOnus((string) $id);
                if ($result['success'] ?? false) {
                    $this->persistUnconfigured($result['response'] ?? [], $olt);
                } else {
                    $message = $result['message'] ?? 'Error al obtener ONUs no configuradas';
                }
            } else {
                $respose = $this->oltService->syncUnconfiguredOnus($id);
                if (! $respose['success']) {
                    $message = $respose['message'];
                }
            }
        }

        $rows = $olt ? $olt->unconfiguredOnus : OltUnconfiguredOnu::all();

        return response()->json(['success' => true, 'rows' => $rows, 'message' => $message]);
    }

    private function persistUnconfigured(array $found, Olt $olt): void
    {
        if (empty($found)) {
            return;
        }

        $now  = now();
        $rows = array_map(fn($e) => [
            'sn'             => $e['sn'],
            'olt_id'         => $olt->id,
            'port'           => $e['port']         ?? null,
            'onu_type_name'  => $e['equipment_id'] ?? null,
            'pon_description'=> $e['vender_id']    ?? null,
            'last_synced_at' => $now,
        ], $found);

        foreach (array_chunk($rows, 200) as $chunk) {
            OltUnconfiguredOnu::upsert($chunk, ['sn', 'olt_id'], [
                'port', 'onu_type_name', 'pon_description', 'last_synced_at',
            ]);
        }
    }

    public function getSavedUnconfigured(Request $request)
    {
        $rows = OltOnu::whereNull('board')->orWhereNull('port')->get();
        return response()->json([
            'success' => true,
            'rows' => $rows,
            'message' => null
        ]);
    }
}
