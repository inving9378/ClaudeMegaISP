<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedDevice;
use App\Modules\Addons\MapaRed\Models\MapaRedDevicePort;
use App\Modules\Addons\MapaRed\Models\MapaRedDevicePortConnection;
use App\Modules\Addons\MapaRed\Repositories\MapaRedDeviceRepository;
use App\Modules\Addons\MapaRed\Support\LayerConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Espejo de App\Modules\Addons\Mapas\Controllers\Geo\DevicesController, apuntando a
 * los modelos mapared_* (MR-06a-2, item #9990334). Ver docs/mapared-mr06-checklist-paridad-item-942.md
 * sección 1.
 */
class DevicesController extends Controller
{
    use LayerConfig;

    protected $repository;

    public function __construct()
    {
        $this->repository = new MapaRedDeviceRepository();
    }

    public function store(Request $request)
    {
        $object = $this->repository->create($request->all());
        $object->createPorts();
        $object->load('ports');
        return response()->json($object);
    }

    public function update(Request $request, $id)
    {
        $object = $this->repository->find($id);
        $object = $this->repository->update($object, $request->all());
        $object->createPorts();
        $layer = $object->layer ?? $object->device->layer;
        return $this->layerConfig($layer);
    }

    public function destroy($id)
    {
        $object = MapaRedDevice::find($id);
        $layer = $object->layer ?? $object->device->layer;
        DB::transaction(function () use ($id, $object) {
            $connections = MapaRedDevicePortConnection::whereHasMorph('from', [MapaRedDevicePort::class], function ($query) use ($id, $object) {
                if ($object->type === 'rack') {
                    $query->whereIn('device_id', $object->devices->pluck('id'));
                } else {
                    $query->where('device_id', $id);
                }
            })->orWhereHasMorph('to', [MapaRedDevicePort::class], function ($query) use ($id, $object) {
                if ($object->type === 'rack') {
                    $query->whereIn('device_id', $object->devices->pluck('id'));
                } else {
                    $query->where('device_id', $id);
                }
            })->with('from', 'to')->get()->unique('id');
            MapaRedDevicePortConnection::whereIn('id', $connections->pluck('id'))->delete();
            $object->delete();
        });
        return $this->layerConfig($layer);
    }

    public function savePort(Request $request, $id)
    {
        $object = MapaRedDevicePort::find($id);
        $object->fill($request->all());
        $object->save();
        return response()->json($object);
    }

    public function addPorts(Request $request, $id)
    {
        $object = MapaRedDevice::with('ports')->find($id);
        $ports = [];
        $date = now();
        $data = $request->ports;
        $current_data = $object->data;
        $new_ports = $data['wan_ports'] ?? 0;
        $current_ports = $current_data['wan_ports'] ?? 0;
        $start = $current_ports === 0 ? 1 : $current_ports + 1;
        $end = $current_ports === 0 ? $new_ports : $current_ports + $new_ports;
        for ($i = $start; $i <= $end; $i++) {
            $ports[] = [
                'name' => $i <= 9 ? ('0' . $i) : $i,
                'type' => 'in',
                'device_id' => $id,
                'transfer' => null,
                'transfer_type' => null,
                'card' => null,
                'created_at' => $date,
                'updated_at' => $date,
            ];
        }
        $current_data['wan_ports'] = $end;

        $new_ports = $data['console_ports'] ?? 0;
        $current_ports = $current_data['console_ports'] ?? 0;
        $start = $current_ports === 0 ? 1 : $current_ports + 1;
        $end = $current_ports === 0 ? $new_ports : $current_ports + $new_ports;
        for ($i = $start; $i <= $end; $i++) {
            $ports[] = [
                'name' => $i <= 9 ? ('0' . $i) : $i,
                'type' => 'console',
                'device_id' => $id,
                'transfer' => null,
                'transfer_type' => null,
                'card' => null,
                'created_at' => $date,
                'updated_at' => $date,
            ];
        }
        $current_data['console_ports'] = $end;

        $cards = $data['service_cards'] ?? [];
        $current_cards = $current_data['service_cards'];
        $start = count($current_cards);
        for ($i = 0; $i < count($cards); $i++) {
            $start++;
            for ($j = 0; $j < $cards[$i]['ports']; $j++) {
                $ports[] = [
                    'name' => $j <= 9 ? ('0' . $j) : $j,
                    'type' => 'out',
                    'device_id' => $id,
                    'transfer' => null,
                    'transfer_type' => null,
                    'card' => $start,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            $current_cards[] = $cards[$i];
        }
        $current_data['service_cards'] = $current_cards;
        if (count($ports) !== 0) {
            MapaRedDevicePort::insert($ports);
            $object->data = $current_data;
            $object->save();
        }
        $layer = $object->layer ?? $object->device->layer;
        return $this->layerConfig($layer);
    }

    public function changeCardOLTDirection(Request $request, $id)
    {
        $object = MapaRedDevice::find($id);
        $data = $object->data;
        $data['service_cards'][$request->card]['order'] = $request->order;
        $object->data = $data;
        $object->save();
        $layer = $object->layer;
        return $this->layerConfig($layer);
    }
}
