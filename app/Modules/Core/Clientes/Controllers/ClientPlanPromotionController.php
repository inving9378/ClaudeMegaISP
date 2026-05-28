<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\ClientPlanPromotion;
use App\Models\OltOnu;
use App\Services\OLTsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientPlanPromotionController extends Controller
{
    use DatatableCoreTrait;

    private $model;

    public function __construct()
    {
        $this->model = ClientPlanPromotion::class;
    }

    public function index(Request $request, $id)
    {
        $columns = array_merge(['id'], $request->columns);
        $order = $request->sortBy ?? $columns[0] ?? null;
        $dir = $request->descending ? 'DESC' : 'ASC';
        $mapping = $this->getColumnMapping();
        $query  = $this->getGeneralQuery($columns, $mapping);
        $query->where('client_id', $id);
        $query = $this->applySearch($query, $request->search ?? null, $columns, $mapping);
        $query = $this->applySorting($query, $order, $dir, $mapping);
        $objects = $query->paginate(isset($request->rowsPerPage) ? $request->rowsPerPage : 20, ['*'], 'page', isset($request->page) ? $request->page : null);
        $onu = OltOnu::firstWhere('client_id', $id);
        $profile = null;
        if ($onu && isset($onu->service_ports) && !empty($onu->service_ports)) {
            $profile = $onu->service_ports[0];
        }
        return response()->json(
            [
                'objects' => $objects->items(),
                'total' => $objects->total(),
                'profile' => $profile
            ]
        );
    }

    public function store(Request $request)
    {
        $data = $request->only((new ClientPlanPromotion())->getFillable());
        $onu = OltOnu::firstWhere('client_id', $data['client_id']);
        if ($onu) {
            if (isset($onu->unique_external_id)) {
                $service = new OLTsService();
                $portData = $onu->service_ports;
                if (isset($portData) && !empty($portData)) {
                    $portData  = $portData[0];
                    $upload_speed = $portData['upload_speed'];
                    $download_speed = $portData['download_speed'];
                    $response = $service->updateSpeedProfile($onu->unique_external_id, [
                        'upload_speed_profile_name' => $data['current_upload_profile'],
                        'download_speed_profile_name' => $data['current_download_profile']
                    ]);
                    if ($response['success']) {
                        $service->syncOnu($onu);
                        $data['end_at'] = Carbon::createFromFormat('Y-m-d', Str::substr($data['end_at'], 0, 10));
                        $data['before_download_profile'] = $download_speed;
                        $data['before_upload_profile'] = $upload_speed;
                        ClientPlanPromotion::create($data);
                        return [
                            'success' => true,
                            'message' => 'Promoción agregada correctamente'
                        ];
                    }
                    return response()->json($response);
                } else {
                    return [
                        'success' => false,
                        'message' => 'No se permite la operación solicitada. La ONU de este cliente no tiene configurado los puertos de servicio'
                    ];
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se permite la operación solicitada. La ONU de este cliente no tiene configurado el ID externo'
                ]);
            }
        }
        return response()->json([
            'success' => false,
            'message' => 'No se permite la operación solicitada. ONU del cliente no encontrada'
        ]);
    }

    public function bulkStore(Request $request)
    {
        $clients = $request->input('clients', []);
        if (empty($clients)) {
            return response()->json([
                'success' => false,
                'message' => 'No se permite la operación solicitada. No existen clientes'
            ]);
        }
        $data = $request->only((new ClientPlanPromotion())->getFillable());
        $onus = OltOnu::whereIn('client_id', collect($clients)->pluck('id'))->get();
        $promotions = [];
        $externals_id = [];
        $end_at = Carbon::createFromFormat('Y-m-d', Str::substr($data['end_at'], 0, 10));
        $now = now();
        foreach ($onus as $o) {
            $portData = $o->service_ports;
            if (isset($portData) && !empty($portData)) {
                $portData  = $portData[0];
                $upload_speed = $portData['upload_speed'];
                $download_speed = $portData['download_speed'];
                $promotions[] = [
                    'client_id' => $o->client_id,
                    'before_download_profile' => $download_speed,
                    'current_download_profile' => $data['current_download_profile'],
                    'before_upload_profile' => $upload_speed,
                    'current_upload_profile' => $data['current_upload_profile'],
                    'end_at' => $end_at,
                    'created_at' => $now
                ];
                $externals_id[] = $o->unique_external_id;
            }
        }
        if (empty($promotions)) {
            return response()->json([
                'success' => false,
                'message' => 'No se permite la operación solicitada. Clientes sin perfiles configurados con anterioridad'
            ]);
        }
        $service = new OLTsService();
        $response = $service->updateBulkSpeedProfile([
            'onus_external_ids' => implode(',', $externals_id),
            'upload_speed_profile_name' => $data['current_upload_profile'],
            'download_speed_profile_name' => $data['current_download_profile']
        ]);
        if ($response['success']) {
            ClientPlanPromotion::insert($promotions);
            $response = $service->syncSpeedProfilesFromOnus($onus);
            $msg = '';
            if ($response['success'] === count($onus)) {
                $msg = 'Clientes agregados a la promoción correctamente';
            } else if ($response['errors'] === count($onus)) {
                $msg = 'Se han agregado los clientes a la promoción pero no se han podido sincronizar los perfiles';
            } else {
                $msg = 'Se han agregado los clientes a la promoción pero algunos perfiles no se pudieron sincronizar';
            }
            return [
                'success' => true,
                'message' => $msg
            ];
        }
        return response()->json($response);
    }

    public function cancel($id)
    {
        $object = ClientPlanPromotion::find($id);
        if ($object) {
            $onu = OltOnu::firstWhere('client_id', $object->client_id);
            if ($onu) {
                if (isset($onu->unique_external_id)) {
                    $service = new OLTsService();
                    $response = $service->updateSpeedProfile($onu->unique_external_id, [
                        'upload_speed_profile_name' => $object->before_upload_profile,
                        'download_speed_profile_name' => $object->before_download_profile
                    ]);
                    if ($response['success']) {
                        $service->syncOnu($onu);
                        $object->status = 'canceled';
                        $object->end_at = now();
                        $object->save();
                        return [
                            'success' => true,
                            'message' => 'Promoción cancelada correctamente'
                        ];
                    }
                    return response()->json($response);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se permite la operación solicitada. La ONU de este cliente no tiene configurado el ID externo'
                    ]);
                }
            }
            return response()->json([
                'success' => false,
                'message' => 'No se permite la operación solicitada. ONU del cliente no encontrada'
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => 'No se permite la operación solicitada. Promoción no encontrada'
        ]);
    }

    public function update(Request $request, $id)
    {
        $object = ClientPlanPromotion::find($id);
        if ($object) {
            $data = $request->only((new ClientPlanPromotion())->getFillable());
            $onu = OltOnu::firstWhere('client_id', $object->client_id);
            if ($onu) {
                if (isset($onu->unique_external_id)) {
                    $service = new OLTsService();
                    $portData = $onu->service_ports;
                    if (isset($portData) && !empty($portData)) {
                        $portData  = $portData[0];
                        $data['end_at'] = Carbon::createFromFormat('Y-m-d', Str::substr($data['end_at'], 0, 10));
                        if ($portData['upload_speed'] !== $data['current_upload_profile']  || $portData['download_speed'] !== $data['current_download_profile']) {
                            unset($portData['upload_speed'], $portData['download_speed']);
                            $portData['upload_speed_profile_name'] = $data['current_upload_profile'];
                            $portData['download_speed_profile_name'] = $data['current_download_profile'];
                            $response = $service->updateServicePort($onu->unique_external_id, $portData);
                            if ($response['success']) {
                                $service->syncOnu($onu);
                                if ($object) {
                                    $object->update($data);
                                }
                                return [
                                    'success' => true,
                                    'message' => 'Promoción modificada correctamente'
                                ];
                            }
                            return response()->json($response);
                        } else {
                            if ($object) {
                                $object->update($data);
                            }
                            return [
                                'success' => true,
                                'message' => 'Promoción modificada correctamente'
                            ];
                        }
                    } else {
                        return [
                            'success' => false,
                            'message' => 'No se permite la operación solicitada. La ONU de este cliente no tiene configurado los puertos de servicio'
                        ];
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se permite la operación solicitada. La ONU de este cliente no tiene configurado el ID externo'
                    ]);
                }
            }
            return response()->json([
                'success' => false,
                'message' => 'No se permite la operación solicitada. ONU del cliente no encontrada'
            ]);
        }
        return response()->json([
            'success' => false,
            'message' => 'No se permite la operación solicitada. Promoción no encontrada'
        ]);
    }

    protected function getBaseColumnsByTable()
    {
        return [
            'client_plan_promotions' => [
                'id' => ['searchable' => false],
                'before_download_profile' => ['searchable' => true],
                'current_download_profile' => ['searchable' => true],
                'before_upload_profile' => ['searchable' => true],
                'current_upload_profile' => ['searchable' => true],
                'created_at' => ['searchable' => false],
                'end_at' => ['searchable' => false],
                'status' => ['searchable' => false]
            ]
        ];
    }
}
