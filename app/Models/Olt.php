<?php

namespace App\Models;

use App\Services\OLTsService;
use App\Traits\CleanSignals;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Olt extends Model
{
    use HasFactory, CleanSignals;

    public $incrementing = false;

    protected $fillable = [
        'name',
        'olt_hardware_version',
        'ip',
        'snmp_port',
        'telnet_port',
        'env_temp',
        'uptime',
        'status',
        'last_synced_at'
    ];

    protected $appends = ['env_temp_cls', 'active', 'last_synced_at_humans'];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function cards()
    {
        return $this->hasMany(OltCard::class, 'olt_id', 'id');
    }

    public function ponPorts()
    {
        return $this->hasMany(OltPonPort::class, 'olt_id', 'id');
    }

    public function uplinkPorts()
    {
        return $this->hasMany(OltUplinkPort::class, 'olt_id', 'id');
    }

    public function vlans()
    {
        return $this->hasMany(OltVlan::class, 'olt_id', 'id');
    }

    public function onus()
    {
        return $this->hasMany(OltOnu::class, 'olt_id', 'id');
    }

    public function interruptions()
    {
        return $this->hasMany(OltInterruptionPon::class, 'olt_id', 'id');
    }

    public function unconfiguredOnus()
    {
        return $this->hasMany(OltUnconfiguredOnu::class, 'olt_id', 'id');
    }

    public function syncCards()
    {
        $service = new OLTsService();
        $response = $service->getCardsByOlt($this->id);
        if ($response['success']) {
            $objects = $response['response'];
            if (empty($objects)) return $response;
            $objects = collect($objects);
            return DB::transaction(function () use ($objects) {
                $now = now();
                $data = $objects->map(function ($obj) use ($now) {
                    return [
                        'olt_id' => $this->id,
                        'slot' => $obj['slot'],
                        'type' => $obj['type'],
                        'real_type' => $obj['real_type'],
                        'ports' => $obj['ports'],
                        'software_version' => $obj['software_version'],
                        'role' => $obj['role'],
                        'status' => $obj['status'],
                        'info_updated' => $obj['info_updated'],
                        'updated_at' => $now,
                        'last_synced_at' => $now,
                    ];
                })->toArray();
                OltCard::upsert(
                    $data,
                    ['olt_id', 'slot'],
                    [
                        'type',
                        'real_type',
                        'ports',
                        'software_version',
                        'role',
                        'status',
                        'info_updated',
                        'updated_at',
                        'last_synced_at'
                    ]
                );
                OltCard::where('olt_id', $this->id)
                    ->whereNotIn('slot', $objects->pluck('slot'))
                    ->delete();
                return [
                    'success' => true
                ];
            });
        } else {
            return $response;
        }
    }

    public function syncPonPorts()
    {
        $service = new OLTsService();
        $response = $service->getPonPortsByOlt($this->id);
        if ($response['success']) {
            $objects = $response['response'];
            if (empty($objects)) return $response;
            return DB::transaction(function () use ($objects) {
                $now = now();
                $data = collect($objects)->map(function ($obj) use ($now) {
                    return [
                        'olt_id' => $this->id,
                        'board' => $obj['board'],
                        'pon_port' => $obj['pon_port'],
                        'pon_type' => $obj['pon_type'],
                        'admin_status' => $obj['admin_status'],
                        'operational_status' => $obj['operational_status'],
                        'onus_count' => $obj['onus_count'],
                        'online_onus_count' => $obj['online_onus_count'],
                        'average_signal' => $obj['average_signal'],
                        'min_range' => $obj['min_range'],
                        'max_range' => $obj['max_range'],
                        'tx_power' => $obj['tx_power'],
                        'description' => $obj['description'],
                        'updated_at' => $now,
                        'last_synced_at' => $now,
                    ];
                })->toArray();
                OltPonPort::upsert(
                    $data,
                    ['olt_id', 'board', 'pon_port'],
                    [
                        'pon_type',
                        'admin_status',
                        'operational_status',
                        'onus_count',
                        'online_onus_count',
                        'average_signal',
                        'min_range',
                        'max_range',
                        'tx_power',
                        'description',
                        'updated_at',
                        'last_synced_at'
                    ]
                );
                $this->cleanupOldPorts($objects);
                return [
                    'success' => true
                ];
            });
        } else {
            return $response;
        }
    }

    protected function cleanupOldPorts($apiResponse)
    {
        $query = OltPonPort::where('olt_id', $this->id);
        foreach ($apiResponse as $obj) {
            $query->whereNot(function ($q) use ($obj) {
                $q->where('board', $obj['board'])
                    ->where('pon_port', $obj['pon_port']);
            });
        }
        $query->delete();
    }

    public function syncUplinkPorts()
    {
        $service = new OLTsService();
        $response = $service->getUplinkPortsByOlt($this->id);
        if ($response['success']) {
            $objects = $response['response'];
            if (empty($objects)) return $response;
            $objects = collect($objects);
            return DB::transaction(function () use ($objects) {
                $now = now();
                $data = $objects->map(function ($obj) use ($now) {
                    return [
                        'olt_id' => $this->id,
                        'name' => $obj['name'],
                        'type' => $obj['type'],
                        'mode' => $obj['mode'],
                        'admin_status' => $obj['admin_status'],
                        'status' => $obj['status'],
                        'vlan_tag' => $obj['vlan_tag'],
                        'negotiation_auto' => $obj['negotiation_auto'],
                        'mtu' => $obj['mtu'],
                        'wavelength' => $obj['wavelength'],
                        'temperature' => $obj['temperature'],
                        'pvid' => $obj['pvid'],
                        'description' => $obj['description'],
                        'updated_at' => $now,
                        'last_synced_at' => $now,
                    ];
                })->toArray();
                OltUplinkPort::upsert(
                    $data,
                    ['olt_id', 'name'],
                    [
                        'type',
                        'mode',
                        'admin_status',
                        'status',
                        'vlan_tag',
                        'negotiation_auto',
                        'mtu',
                        'wavelength',
                        'temperature',
                        'pvid',
                        'description',
                        'updated_at',
                        'last_synced_at'
                    ]
                );
                OltUplinkPort::where('olt_id', $this->id)
                    ->whereNotIn('name', $objects->pluck('name'))
                    ->delete();
                return [
                    'success' => true
                ];
            });
        } else {
            return $response;
        }
    }

    public function syncVLANs()
    {
        $service = new OLTsService();
        $response = $service->getVLANsByOlt($this->id);
        if ($response['success']) {
            $objects = $response['response'];
            if (empty($objects)) return $response;
            $objects = collect($objects);
            return DB::transaction(function () use ($objects) {
                $now = now();
                $data = $objects->map(function ($obj) use ($now) {
                    return [
                        'id'    => $obj['id'],
                        'vlan'           => $obj['vlan'],
                        'scope'          => $obj['scope'],
                        'description'    => $obj['description'],
                        'olt_id'         => $this->id,
                        'updated_at' => $now,
                        'last_synced_at' => $now,
                    ];
                })->toArray();
                OltVlan::upsert($data, ['id'], ['vlan', 'scope', 'description', 'updated_at', 'last_synced_at']);
                OltVlan::where('olt_id', $this->id)
                    ->whereNotIn('id', $objects->pluck('id'))
                    ->delete();
                return [
                    'success' => true
                ];
            });
        } else {
            return $response;
        }
    }

    public function syncOnus()
    {
        $service = new OLTsService();
        $response = $service->getAllOnuDataParallel($this->id);
        if ($response['success']) {
            $onusResponse = $response['onus'];
            $signalsResponse = $response['signals'];
            $statusesResponse = $response['statuses'];
            if ($onusResponse['success'] && $signalsResponse['success'] && $statusesResponse['success']) {
                $onus = $onusResponse['rows'];
                $signals = $signalsResponse['rows'];
                $statuses = $statusesResponse['rows'];
                if (empty($onus)) return $response;
                $onus = collect($onus);
                $signalsIndexed  = collect($signals)->keyBy('sn');
                $statusesIndexed = collect($statuses)->keyBy('sn');
                return DB::transaction(function () use ($onus, $signalsIndexed, $statusesIndexed) {
                    $now = now();
                    $data = $onus->map(function ($obj) use ($now, $signalsIndexed, $statusesIndexed) {
                        $id = $obj['sn'];
                        $freshSignal = $signalsIndexed->get($id);
                        $freshStatus = $statusesIndexed->get($id);
                        return [
                            'sn' => $obj['sn'],
                            'unique_external_id' => $obj['unique_external_id'] ?? $obj['sn'],
                            'board' => trim($obj['board']) === '' ? null : $obj['board'],
                            'port' => trim($obj['port']) === '' ? null : $obj['port'],
                            'administrative_status' => $obj['administrative_status'],
                            'address' => $obj['address'],
                            'mode' => $obj['mode'],
                            'name' => $obj['name'],
                            'onu' => $obj['onu'],
                            'pon_type' => $obj['pon_type'],
                            'onu_type_id' => $obj['onu_type_id'],
                            'onu_type_name' => $obj['onu_type_name'],
                            'status' => $freshStatus['status'] ?? $obj['status'],
                            'zone_id' => $obj['zone_id'],
                            'zone_name' => $obj['zone_name'],
                            'olt_id' => $obj['olt_id'],
                            'olt_name' => $obj['olt_name'],
                            'tr069' => $obj['tr069'],
                            'tr069_profile' => $obj['tr069_profile'],
                            'catv' => $obj['catv'],
                            'wan_mode' => $obj['wan_mode'],
                            'vlan' => $obj['vlan'] ?? null,
                            'mgmt_ip_address' => $obj['mgmt_ip_address'] ?? null,
                            'mgmt_ip_mode' => $obj['mgmt_ip_mode'] ?? null,
                            'mgmt_ip_service_port' => $obj['mgmt_ip_service_port'] ?? null,
                            'mgmt_ip_vlan' => $obj['mgmt_ip_vlan'] ?? null,
                            'mgmt_ip_subnet_mask' => $obj['mgmt_ip_subnet_mask'] ?? null,
                            'mgmt_ip_default_gateway' => $obj['mgmt_ip_default_gateway'] ?? null,
                            'mgmt_ip_dns1' => $obj['mgmt_ip_dns1'] ?? null,
                            'mgmt_ip_dns2' => $obj['mgmt_ip_dns2'] ?? null,
                            'mgmt_ip_cvlan' => $obj['mgmt_ip_cvlan'] ?? null,
                            'mgmt_ip_svlan' => $obj['mgmt_ip_svlan'] ?? null,
                            'mgmt_ip_tag_transform_mode' => $obj['mgmt_ip_tag_transform_mode'] ?? null,
                            'custom_template_name' => $obj['custom_template_name'] ?? null,
                            'ip_address' => $obj['ip_address'],
                            'subnet_mask' => $obj['subnet_mask'],
                            'default_gateway' => $obj['default_gateway'],
                            'dns1' => $obj['dns1'],
                            'dns2' => $obj['dns2'],
                            'username' => $obj['username'],
                            'password' => $obj['password'],

                            'odb_name' => $obj['odb_name'],
                            'longitude' => $obj['longitude'],
                            'latitude' => $obj['latitude'],
                            'contact' => $obj['contact'],
                            'voip_service' => $obj['voip_service'],
                            'signal' => $freshSignal['signal'] ?? $obj['signal'],
                            'signal_1310' => $this->cleanSignal($freshSignal['signal_1310'] ?? $obj['signal_1310']),
                            'signal_1490' => $this->cleanSignal($freshSignal['signal_1490'] ?? $obj['signal_1490']),
                            'authorization_date' => $obj['authorization_date'],
                            'service_ports' => json_encode($obj['service_ports']),
                            'ethernet_ports' => json_encode($obj['ethernet_ports']),
                            'wifi_ports' => json_encode($obj['wifi_ports']),
                            'voip_ports' => json_encode($obj['voip_ports']),
                            'updated_at' => $now,
                            'last_synced_at' => $now,
                        ];
                    })->toArray();

                    foreach (array_chunk($data, 100) as $objs) {
                        OltOnu::upsert($objs, ['sn', 'olt_id'], [
                            'unique_external_id',
                            'board',
                            'port',
                            'administrative_status',
                            'address',
                            'mode',
                            'name',
                            'onu',
                            'onu_type_id',
                            'onu_type_name',
                            'pon_type',
                            'signal',
                            'status',
                            'zone_name',
                            'updated_at',
                            'last_synced_at',
                            'tr069',
                            'tr069_profile',
                            'catv',
                            'custom_template_name',
                            'zone_id',
                            'mgmt_ip_address',
                            'mgmt_ip_mode',
                            'mgmt_ip_service_port',
                            'mgmt_ip_vlan',
                            'mgmt_ip_subnet_mask',
                            'mgmt_ip_default_gateway',
                            'mgmt_ip_dns1',
                            'mgmt_ip_dns2',
                            'mgmt_ip_cvlan',
                            'mgmt_ip_svlan',
                            'mgmt_ip_tag_transform_mode',
                            'wan_mode',
                            'vlan',
                            'odb_name',
                            'longitude',
                            'latitude',
                            'olt_name',
                            'contact',
                            'signal_1310',
                            'signal_1490',
                            'authorization_date',
                            'service_ports',
                            'ethernet_ports',
                            'wifi_ports',
                            'voip_ports',
                            'voip_service'
                        ]);
                    }
                    OltOnu::where('olt_id', $this->id)
                        ->whereNotIn('sn', $onus->pluck('sn'))
                        ->delete();
                    return [
                        'success' => true
                    ];
                });
            } else {
                return $response;
            }
        }
        return $response;
    }

    public function syncInterruptionsPons()
    {
        $service = new OLTsService();
        $response = $service->getOutagePONsByOlt($this->id);
        if ($response['success']) {
            $objects = $response['response'];
            if (empty($objects)) return $response;
            return DB::transaction(function () use ($objects) {
                $now = now();
                $data = collect($objects)->map(function ($obj) use ($now) {
                    return [
                        'olt_id' => $this->id,
                        'board' => $obj['board'],
                        'port' => $obj['port'],
                        'cause' => $obj['cause'],
                        'power_count' => $obj['power_count'],
                        'total_onus' => $obj['total_onus'],
                        'los_count' => $obj['los_count'],
                        'latest_status_change' => $obj['latest_status_change'],
                        'pon_description' => $obj['pon_description'],
                        'updated_at' => $now,
                        'last_synced_at' => $now,
                    ];
                })->toArray();
                OltInterruptionPon::upsert(
                    $data,
                    ['olt_id', 'board', 'port'],
                    [
                        'cause',
                        'power_count',
                        'total_onus',
                        'los_count',
                        'latest_status_change',
                        'pon_description',
                        'updated_at',
                        'last_synced_at'
                    ]
                );
                $this->cleanupInterruptions($objects);
                return [
                    'success' => true
                ];
            });
        } else {
            return $response;
        }
    }

    protected function cleanupInterruptions($apiResponse)
    {
        $query = OltInterruptionPon::where('olt_id', $this->id);
        foreach ($apiResponse as $obj) {
            $query->whereNot(function ($q) use ($obj) {
                $q->where('board', $obj['board'])
                    ->where('port', $obj['port']);
            });
        }
        $query->delete();
    }

    public function syncUnconfiguredOnus()
    {
        $service = new OLTsService();
        $response = $service->getUnconfiguredONUs($this->id);
        if ($response['success']) {
            $objects = $response['response'];
            if (empty($objects)) return $response;
            return DB::transaction(function () use ($objects) {
                $now = now();
                $data = collect($objects)->map(function ($obj) use ($now) {
                    return [
                        'olt_id' => $this->id,
                        'board' => $obj['board'],
                        'port' => $obj['port'],
                        'pon_type' => $obj['pon_type'],
                        'sn' => $obj['sn'],
                        'onu_type_name' => $obj['onu_type_name'],
                        'updated_at' => $now,
                        'last_synced_at' => $now,
                    ];
                })->toArray();
                OltUnconfiguredOnu::upsert(
                    $data,
                    ['olt_id', 'board', 'port'],
                    [
                        'pon_type',
                        'sn',
                        'onu_type_name',
                        'updated_at',
                        'last_synced_at'
                    ]
                );
                $this->cleanupUnconfigured($objects);
                return [
                    'success' => true
                ];
            });
        } else {
            return $response;
        }
    }

    protected function cleanupUnconfigured($apiResponse)
    {
        $query = OltUnconfiguredOnu::where('olt_id', $this->id);
        foreach ($apiResponse as $obj) {
            $query->whereNot(function ($q) use ($obj) {
                $q->where('board', $obj['board'])
                    ->where('port', $obj['port']);
            });
        }
        $query->delete();
    }

    public function getEnvTempClsAttribute()
    {
        $value = (float) filter_var($this->env_temp, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        if ($value == 0) return 'secondary';
        if ($value <= 40) return 'positive';
        if ($value <= 65) return 'warning';
        return 'danger';
    }

    public function getActiveAttribute()
    {
        return $this->uptime && $this->uptime !== "" && $this->uptime !== "0" && $this->uptime !== "00:00:00";
    }

    public function getLastSyncedAtHumansAttribute()
    {
        return $this->last_synced_at->diffForHumans();
    }
}
