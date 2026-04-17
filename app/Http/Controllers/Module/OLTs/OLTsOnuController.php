<?php

namespace App\Http\Controllers\Module\OLTs;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\ClientAdditionalInformation;
use App\Models\Olt;
use App\Models\OltOnu;
use App\Models\OltUnconfiguredOnu;
use App\Services\OLTsService;
use Illuminate\Http\Request;

class OLTsOnuController extends Controller
{
    use DatatableCoreTrait;

    protected $oltService;
    protected $ttl;
    private $model;

    public function __construct()
    {
        $this->oltService = new OLTsService();
        $this->ttl = config('services.smartolt.ttl');
        $this->model = OltOnu::class;
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
        if ($onu) {
            $response = $this->oltService->syncSignalsAndStatus($onu);
            return $response;
        }
        return response()->json([
            'success' => false,
            'message' => 'Onu no encontrada'
        ]);
    }

    public function updateServicePort(Request $request, $id)
    {
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
        $rows = [];
        if ($request->force) {
            $respose = $this->oltService->syncUnconfiguredOnus($id);
            if (!$respose['success']) {
                $message = $respose['message'];
            }
        }
        if (isset($id)) {
            $olt = Olt::find($id);
            $rows = $olt->unconfiguredOnus;
        } else {
            $rows = OltUnconfiguredOnu::all();
        }
        return response()->json([
            'success' => true,
            'rows' => $rows,
            'message' => $message
        ]);
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
