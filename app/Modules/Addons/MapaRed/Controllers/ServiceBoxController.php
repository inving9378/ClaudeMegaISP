<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientMainInformation;
use App\Modules\Addons\MapaRed\Models\MapaRedDevice;
use App\Modules\Addons\MapaRed\Models\MapaRedDevicePort;
use App\Modules\Addons\MapaRed\Models\MapaRedDevicePortConnection;
use App\Modules\Addons\MapaRed\Support\LayerConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Espejo de App\Modules\Addons\Mapas\Controllers\Geo\ServiceBoxController, apuntando a
 * los modelos mapared_* (MR-06a-5, item #9990337). Ver docs/mapared-mr06-checklist-paridad-item-942.md
 * sección 1. EXCLUYE savePort (sin caller en frontend, confirmado MR-01c) — no se porta como
 * acción activa.
 */
class ServiceBoxController extends Controller
{
    use LayerConfig;

    public function getSelectedClients(Request $request, $id)
    {
        $query = ClientMainInformation::whereRaw(sprintf('id in (SELECT msp.client_id FROM mapared_devices_ports msp INNER JOIN mapared_devices ms ON msp.device_id=ms.id AND ms.layer_id=? %s WHERE msp.client_id IS NOT NULL)', isset($request->device_id) ? ('and ms.parent_id=' . $request->device_id) : ''), [$id]);
        $this->setQueryOptions($query, $request);
        return response()->json($query->paginate(isset($request->rowsPerPage) ? $request->rowsPerPage : 20, ['*'], 'page', isset($request->page) ? $request->page : null));
    }

    public function getAvaiablesClients(Request $request)
    {
        $query = ClientMainInformation::whereRaw("id not in (SELECT msp.client_id FROM mapared_devices_ports msp where msp.client_id is not null and msp.type='in')");
        $this->setQueryOptions($query, $request);
        return response()->json($query->paginate(isset($request->rowsPerPage) ? $request->rowsPerPage : 20, ['*'], 'page', isset($request->page) ? $request->page : null));
    }

    public function setQueryOptions($query, $request)
    {
        if (isset($request->search)) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw("CONCAT_WS(' ', name, father_last_name, mother_last_name) like ?", [$search])->orWhere('estado', 'like', $search)->orWhere('client_id', 'like', $search);
            });
        }
        if (isset($request->sortBy)) {
            $sortBy = $request->sortBy;
            $direction = $request->descending ? 'DESC' : 'ASC';
            if ($sortBy == 'client_name_with_fathers_names') {
                $query->orderBy('name', $direction)->orderBy('father_last_name', $direction)->orderBy('mother_last_name', $direction);
            } else {
                $query->orderBy($sortBy, $direction);
            }
        }
    }

    public function removeClients(Request $request)
    {
        $result = DB::transaction(function () use ($request) {
            $ports = MapaRedDevicePort::whereIn('client_id', $request->clients)->where('type', 'in')->get();
            $connections = MapaRedDevicePortConnection::whereIn('from_id', $ports->pluck('id'))->where('from_type', MapaRedDevicePort::class)->orWhere(function ($query) use ($ports) {
                $query->whereIn('to_id', $ports->pluck('id'))->where('from_type', MapaRedDevicePort::class);
            })->get();
            MapaRedDevicePortConnection::whereIn('id', $connections->pluck('id'))->delete();
            MapaRedDevice::whereIn('id', $ports->pluck('device_id'))->delete();
        });
        return response()->json($result);
    }

    public function removeClient(Request $request, $id)
    {
        $port = MapaRedDevicePort::find($id);
        $layer = $port->device->layer;
        DB::transaction(function () use ($request, $port) {
            $drop = MapaRedDevicePort::where('client_id', $port->client_id)->where('type', 'drop_out')->first();
            $data = [
                'reason' => $request->reason
            ];
            $connection = MapaRedDevicePortConnection::where('from_id', $port->id)->where('from_type', MapaRedDevicePort::class)->orWhere(function ($query) use ($port) {
                $query->where('to_id', $port->id)->where('from_type', MapaRedDevicePort::class);
            })->first();
            if ($connection) {
                $connection->delete();
            }
            if ($request->reason === 'Cambio de domicilio') {
                $client = $port->client;
                $data['old_address'] = $client->address;
                $client->zip = $request->zip;
                $client->street = $request->street;
                $client->internal_number = $request->internal_number;
                $client->external_number = $request->external_number;
                $client->state_id = $request->state_id;
                $client->municipality_id = $request->municipality_id;
                $client->colony_id = $request->colony_id;
                $client->save();
                $data['new_address'] = $client->address;
            }
            if ($request->fiber_quit) {
                $drop->delete();
            } else {
                $drop->data = $data;
                $drop->save();
            }
            MapaRedDevice::find($port->device_id)->delete();
        });
        return $this->layerConfig($layer);
    }

    public function addClients(Request $request, $id)
    {
        $drop = MapaRedDevice::where('layer_id', $id)->where('type', 'drop')->first();
        if (!$drop) {
            $drop = MapaRedDevice::create([
                'name' => 'Salida drop',
                'layer_id' => $id,
                'type' => 'drop'
            ]);
        }
        foreach ($request->clients as $c) {
            $device = MapaRedDevice::create([
                'name' => 'splitters_users',
                'type' => 'client',
                'ports' => 1,
                'orientation' => 'right',
                'layer_id' => $id,
                'parent_id' => $request->parent_id ?? null
            ]);
            MapaRedDevicePort::create([
                'type' => 'in',
                'client_id' => $c,
                'device_id' => $device->id,
            ]);
            $port = MapaRedDevicePort::where('device_id', $drop->id)->where('client_id', $c)->first();
            if (!$port) {
                MapaRedDevicePort::create([
                    'type' => 'drop_out',
                    'client_id' => $c,
                    'device_id' => $drop->id,
                ]);
            }
        }
        return response()->json(true);
    }

    public function removeClientFromDrop($id)
    {
        $drop = MapaRedDevicePort::find($id);
        DB::transaction(function () use ($drop) {
            if ($drop) {
                $port = MapaRedDevicePort::where('client_id', $drop->client_id)->where('type', 'in')->first();
                if ($port) {
                    $connection = MapaRedDevicePortConnection::where('from_id', $port->id)->where('from_type', MapaRedDevicePort::class)->orWhere(function ($query) use ($port) {
                        $query->where('to_id', $port->id)->where('from_type', MapaRedDevicePort::class);
                    })->first();
                    if ($connection) {
                        $connection->delete();
                    }
                    MapaRedDevice::where('id', $port->device_id)->delete();
                }
                $drop->delete();
            }
        });
        return $this->layerConfig($drop->device->layer);
    }
}
