<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\Network;
use App\Models\NetworkIp;
use Illuminate\Support\Facades\Log;

class NetworkIpRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = NetworkIp::query();
    }

    public function count()
    {
        return $this->model->count();
    }

    // GETTERS

    public function getClientIdByIp($ip)
    {
        return $this->model->where('ip', $ip)->first()->client_id ?? null;
    }

    public function getNetworkIpByClientId($clientId)
    {
        return $this->model->where('client_id', $clientId)->get();
    }

    public function getNetworkIpById($id)
    {
        return $this->model->findOrFail($id);
    }

    public function getNetworkIpByIp($ip)
    {
        return $this->model->where('ip', $ip)->first() ?? null;
    }

    public function getServiceIdByIp($ip)
    {
        $net = $this->model->where('ip', $ip)->first();
        if ($net) {
            return $net->used_by;
        }
        return null;
    }

    public function getNetworkIpByClientInternetServiceId($id)
    {
        return $this->model->where('used_by', $id)->first();
    }


    public function getIpFilterById($id)
    {
        return $this->model->where('id', $id)->first()->ip ?? '';
    }

    public function getIpFilterUsedByPlan($id, $type = null)
    {
        return $this->model->where('used_by', $id)->where('type_service', $type)->first()->ip ?? '';
    }

    public function getPoolIp($networkId, $routerId, $ipOld = null)
    {
        $network = Network::find($networkId);
        if (!$network) {
            return null;
        }

        // Obtenemos todas las IPs sin ordenar
        $ipsInNetwork = $this->model->whereHas('network', function ($query) use ($routerId) {
            $query->where('router_id', $routerId);
        })
            ->where('network_id', $networkId)
            ->get();

        if ($ipsInNetwork->isEmpty()) {
            return null;
        }

        // Ordenamos en PHP usando ip2long
        $sortedIps = $ipsInNetwork->sortBy(function ($item) {
            return ip2long($item->ip);
        });

        $firstIpInPool = $sortedIps->first()->ip;
        $lastIpInPool = $sortedIps->last()->ip;

        // Obtenemos la primera IP disponible ordenada correctamente
        $availableIps = $this->model->whereHas('network', function ($query) use ($routerId) {
            $query->where('router_id', $routerId);
        })
            ->where('network_id', $networkId)
            ->where('used', ComunConstantsController::IS_NUMERICAL_FALSE)
            ->get()
            ->sortBy(function ($item) {
                return ip2long($item->ip);
            });


        if (!$network->allow_usage_network) {
            $availableIps = $availableIps->filter(function ($item) use ($firstIpInPool, $lastIpInPool) {
                return $item->ip != $firstIpInPool && $item->ip != $lastIpInPool ;
            });
        }

        if ($ipOld) {
            $availableIps = $availableIps->filter(function ($item) use ($ipOld) {
                return $item->ip != $ipOld;
            });
        }

        return $availableIps->first();
    }

    public function getIfIpIsUsedByOtherClient($ip, $clientInternetServiceId, $type)
    {
        return $ip->used == ComunConstantsController::IS_NUMERICAL_TRUE && $ip->used_by != $clientInternetServiceId && $ip->type_service != $type;
    }

    // SETTERS

    public function update(NetworkIp $networkIp, array $array)
    {
        $networkIp->update($array);
    }

    public function removeUsedIp($networkIp)
    {
        $networkIp->update(['used' => ComunConstantsController::IS_NUMERICAL_FALSE, 'used_by' => '--', 'client_id' => null, 'host_category' => 'Ninguno']);
    }
}
