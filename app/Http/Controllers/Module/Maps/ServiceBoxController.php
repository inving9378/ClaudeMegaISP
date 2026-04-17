<?php

namespace App\Http\Controllers\Module\Maps;

use App\Http\Controllers\Controller;
use App\Http\Traits\Maps\LayerConfig;
use App\Models\ClientMainInformation;
use App\Models\MapDevice;
use App\Models\MapDevicePort;
use App\Models\MapDevicePortConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceBoxController extends Controller
{

    use LayerConfig;

    public function getSelectedClients(Request $request, $id)
    {
        $query = ClientMainInformation::whereRaw(sprintf('id in (SELECT msp.client_id FROM map_devices_ports msp INNER JOIN map_devices ms ON msp.device_id=ms.id AND ms.layer_id=? %s WHERE msp.client_id IS NOT NULL)', isset($request->device_id) ? ('and ms.parent_id=' . $request->device_id) : ''), [$id]);
        $this->setQueryOptions($query, $request);
        return response()->json($query->paginate(isset($request->rowsPerPage) ? $request->rowsPerPage : 20, ['*'], 'page', isset($request->page) ? $request->page : null));
    }

    public function getAvaiablesClients(Request $request)
    {
        $query = ClientMainInformation::whereRaw("id not in (SELECT msp.client_id FROM map_devices_ports msp where msp.client_id is not null and msp.type='in')");
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
            $ports = MapDevicePort::whereIn('client_id', $request->clients)->where('type', 'in')->get();
            $connections = MapDevicePortConnection::whereIn('from_id', $ports->pluck('id'))->where('from_type', MapDevicePort::class)->orWhere(function ($query) use ($ports) {
                $query->whereIn('to_id', $ports->pluck('id'))->where('from_type', MapDevicePort::class);
            })->get();
            MapDevicePortConnection::whereIn('id', $connections->pluck('id'))->delete();
            MapDevice::whereIn('id', $ports->pluck('device_id'))->delete();
        });
        return response()->json($result);
    }

    public function removeClient(Request $request, $id)
    {
        $port = MapDevicePort::find($id);
        $layer = $port->device->layer;
        DB::transaction(function () use ($request, $port) {
            $drop = MapDevicePort::where('client_id', $port->client_id)->where('type', 'drop_out')->first();
            $data = [
                'reason' => $request->reason
            ];
            $connection = MapDevicePortConnection::where('from_id', $port->id)->where('from_type', MapDevicePort::class)->orWhere(function ($query) use ($port) {
                $query->where('to_id', $port->id)->where('from_type', MapDevicePort::class);
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
            MapDevice::find($port->device_id)->delete();
        });
        return $this->layerConfig($layer);
    }

    public function addClients(Request $request, $id)
    {
        $drop = MapDevice::where('layer_id', $id)->where('type', 'drop')->first();
        if (!$drop) {
            $drop = MapDevice::create([
                'name' => 'Salida drop',
                'layer_id' => $id,
                'type' => 'drop'
            ]);
        }
        foreach ($request->clients as $c) {
            $device = MapDevice::create([
                'name' => 'splitters_users',
                'type' => 'client',
                'ports' => 1,
                'orientation' => 'right',
                'layer_id' => $id,
                'parent_id' => $request->parent_id ?? null
            ]);
            MapDevicePort::create([
                'type' => 'in',
                'client_id' => $c,
                'device_id' => $device->id,
            ]);
            $port = MapDevicePort::where('device_id', $drop->id)->where('client_id', $c)->first();
            if (!$port) {
                MapDevicePort::create([
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
        $drop = MapDevicePort::find($id);
        DB::transaction(function () use ($drop) {
            if ($drop) {
                $port = MapDevicePort::where('client_id', $drop->client_id)->where('type', 'in')->first();
                if ($port) {
                    $connection = MapDevicePortConnection::where('from_id', $port->id)->where('from_type', MapDevicePort::class)->orWhere(function ($query) use ($port) {
                        $query->where('to_id', $port->id)->where('from_type', MapDevicePort::class);
                    })->first();
                    if ($connection) {
                        $connection->delete();
                    }
                    MapDevice::where('id', $port->device_id)->delete();
                }
                $drop->delete();
            }
        });
        return $this->layerConfig($drop->device->layer);
    }
}
