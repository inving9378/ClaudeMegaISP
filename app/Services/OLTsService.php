<?php

namespace App\Services;

use App\Models\Olt;
use App\Models\OltBilling;
use App\Models\OltOdb;
use App\Models\OltOnu;
use App\Models\OltSpeedProfile;
use App\Models\OltTypeONU;
use App\Models\OltUnconfiguredOnu;
use App\Models\OltZone;
use App\Traits\CleanSignals;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OLTsService
{
    use CleanSignals;

    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $domain = config('services.smartolt.domain');
        $this->baseUrl = "https://{$domain}/api";
        $this->apiKey = config('services.smartolt.token');
    }

    public function makeRequest($method, $endpoint, $params = null)
    {
        try {
            $client = app('SmartOlt');
            $options = [];
            if ($params) {
                $options['form_params'] = $params;
            }
            $response = $client->request($method, $endpoint, $options);
            $data = json_decode($response->getBody(), true);
            return [
                'success' => true,
                ...$data
            ];
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $responseBody = (string) $e->getResponse()->getBody();
                $decodedError = json_decode($responseBody, true);
                return [
                    'success' => false,
                    ...$decodedError,
                    'status_code' => $e->getResponse()->getStatusCode()
                ];
            }
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function makeRequestAsync($requests)
    {
        $client = app('SmartOlt');
        $promises = [];
        foreach ($requests as $key => $value) {
            $options = [];
            if ($value['params']) {
                $options['form_params'] = $value['params'];
            }
            $promises[$key] = $client->requestAsync($value['method'], $value['url'], $options);
        }
        try {
            $results = Utils::settle($promises)->wait();
            return $this->processParallelData($results, array_keys($requests));
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function processParallelData(array $results, $keys): array
    {
        $data = [
            'success' => true
        ];
        foreach ($keys as $key) {
            $data[$key] = [
                'success' => false,
                'response' => null
            ];
        }
        foreach ($results as $key => $result) {
            if ($result['state'] === 'fulfilled') {
                $response = $result['value'];
                $body = json_decode($response->getBody()->getContents(), true);
                $data[$key] = [
                    'success' => $response->getStatusCode() === 200,
                    'response' => $body['response']
                ];
            } else {
                $reason = $result['reason'];
                $response = $reason->getResponse();
                $body = json_decode($response->getBody()->getContents(), true);
                $data[$key]['response'] = $body['error'];
                $data[$key]['success'] = false;
                $data['success'] = false;
            }
        }
        return $data;
    }

    public function getAllOnuDataParallel($id)
    {
        $client = app('SmartOlt');
        $promises = [
            'onus' => $client->getAsync('onu/get_all_onus_details?olt_id=' . $id),
            'signals' => $client->getAsync('onu/get_onus_signals?olt_id=' . $id),
            'statuses' => $client->getAsync('onu/get_onus_statuses?olt_id=' . $id),
        ];
        try {
            $results = Utils::settle($promises)->wait();
            return $this->processParallelResponses($results);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getSignalAndStatus($id)
    {
        $client = app('SmartOlt');
        $promises = [
            'signals' => $client->getAsync('onu/get_onu_signal/' . $id),
            'status' => $client->getAsync('onu/get_onu_status/' . $id),
        ];
        try {
            $results = Utils::settle($promises)->wait();
            $data = [
                'signals' => ['success' => false, 'data' => []],
                'status' => ['success' => false, 'data' => []],
                'success' => true
            ];
            foreach ($results as $key => $result) {
                if ($result['state'] === 'fulfilled') {
                    $response = $result['value'];
                    $body = json_decode($response->getBody()->getContents(), true);
                    $data[$key] = [
                        'success' => $response->getStatusCode() === 200,
                        'data' => $body
                    ];
                } else {
                    $data[$key]['error'] = $result['message']->getMessage();
                    $data[$key]['success'] = false;
                    $data['success'] = false;
                }
            }
            return $data;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function processParallelResponses(array $results): array
    {
        $data = [
            'onus' => ['success' => false, 'rows' => []],
            'signals' => ['success' => false, 'rows' => []],
            'statuses' => ['success' => false, 'rows' => []],
            'success' => true
        ];
        foreach ($results as $key => $result) {
            if ($result['state'] === 'fulfilled') {
                $response = $result['value'];
                $body = json_decode($response->getBody()->getContents(), true);
                $data[$key] = [
                    'success' => $response->getStatusCode() === 200,
                    'rows' => $key === 'onus' ? $body['onus'] : $body['response']
                ];
            } else {
                $data[$key]['error'] = $result['message']->getMessage();
                $data[$key]['success'] = false;
                $data['success'] = false;
            }
        }
        return $data;
    }

    public function syncOlts()
    {
        $response = $this->getOlts();
        if ($response['success']) {
            $objects = collect($response['data']);
            DB::transaction(function () use ($objects) {
                $now = now();
                $data = $objects->map(function ($obj) use ($now) {
                    return [
                        'id' => $obj['id'],
                        'name' => $obj['name'],
                        'olt_hardware_version' => $obj['olt_hardware_version'] ?? null,
                        'ip' => $obj['ip'] ?? null,
                        'snmp_port' => $obj['snmp_port'] ?? null,
                        'telnet_port' => $obj['telnet_port'] ?? null,
                        'env_temp' => $obj['env_temp'] ?? null,
                        'uptime' => $obj['uptime'] ?? null,
                        'status' => $obj['status'] ?? 'active',
                        'updated_at' => $now,
                        'created_at' => $now,
                        'last_synced_at' => $now,
                    ];
                })->toArray();
                Olt::upsert($data, ['id'], [
                    'name',
                    'olt_hardware_version',
                    'ip',
                    'snmp_port',
                    'telnet_port',
                    'env_temp',
                    'uptime',
                    'status',
                    'updated_at',
                    'last_synced_at'
                ]);
                Olt::whereNotIn('id', $objects->pluck('id'))
                    ->delete();
                return [
                    'success' => true
                ];
            });
        }
        return $response;
    }

    public function syncBillings()
    {
        $response = $this->billings();
        if ($response['success']) {
            $objects = collect($response['response']['olts']);
            DB::transaction(function () use ($objects) {
                $now = now();
                $data = $objects->map(function ($obj) use ($now) {
                    return [
                        'id' => $obj['olt_id'],
                        'name' => $obj['olt_name'],
                        'status' => $obj['olt_subscription_status'],
                        'end_subscription' => $obj['olt_subscription_end_date'],
                        'updated_at' => $now,
                        'created_at' => $now,
                        'last_synced_at' => $now,
                    ];
                })->toArray();
                OltBilling::upsert($data, ['id'], [
                    'name',
                    'status',
                    'end_subscription',
                    'updated_at',
                    'last_synced_at'
                ]);
                OltBilling::whereNotIn('id', $objects->pluck('olt_id'))
                    ->delete();
                return [
                    'success' => true
                ];
            });
        }
        return $response;
    }

    public function syncTemperatures()
    {
        $response = $this->getUpTimeEnviromentTemperatureByOlt();
        if (!$response['success']) {
            return $response;
        }
        $objects = collect($response['response']);
        if ($objects->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No existen datos para sincronizar'
            ];
        }
        try {
            DB::transaction(function () use ($objects) {
                $now = now();
                $chunkSize = 1000;
                $ids = $objects->pluck('olt_id')->toArray();
                $exists = Olt::whereIn('id', $ids)->get()->keyBy('id');
                $dataByOltId = $objects->filter(function ($obj) use ($exists) {
                    return $exists->has($obj['olt_id']);
                })->map(function ($obj) use ($now) {
                    $id = $obj['olt_id'];
                    $data =  [
                        'id' => $id,
                        'uptime' => $obj['uptime'],
                        'env_temp' => $obj['env_temp'],
                        'updated_at' => $now,
                        'last_synced_at' => $now,
                        'name' => $obj['olt_name']
                    ];
                    return $data;
                })->values()->toArray();
                foreach (array_chunk($dataByOltId, $chunkSize) as $chunk) {
                    Olt::upsert($chunk, ['smart_olt'], [
                        'name',
                        'uptime',
                        'env_temp',
                        'updated_at',
                        'last_synced_at'
                    ]);
                }

                Olt::whereNotIn('id', $ids)->delete();
            });
            return ['success' => true];
        } catch (\Throwable $th) {
            return ['success' => false, 'message' => $th->getMessage()];
        }
    }

    public function syncOnusStatus()
    {
        $response = $this->getONUsStatus();
        if (!$response['success']) {
            return $response;
        }
        $objects = collect($response['response']);
        if ($objects->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No existen datos para sincronizar'
            ];
        }
        try {
            DB::transaction(function () use ($objects) {
                $now = now();
                $chunkSize = 1000;

                $oltIds = $objects->pluck('olt_id')->unique()->filter()->toArray();
                $olts = Olt::whereIn('id', $oltIds)->get()->keyBy('id');

                $dataToUpdate = $objects->filter(function ($obj) use ($olts) {
                    return $olts->has($obj['olt_id']);
                })->map(function ($obj) use ($now) {
                    return [
                        'sn'             => $obj['sn'],
                        'olt_id'         => $obj['olt_id'],
                        'unique_external_id' => $obj['unique_external_id'],
                        'board' => $obj['board'],
                        'port' => $obj['port'],
                        'onu' => $obj['onu'],
                        'zone_id' => $obj['zone_id'],
                        'status'         => $obj['status'],
                        'updated_at'     => $now,
                        'last_synced_at' => $now
                    ];
                })->values()->toArray();

                foreach (array_chunk($dataToUpdate, $chunkSize) as $chunk) {
                    OltOnu::upsert($chunk, ['sn', 'olt_id'], [
                        'status',
                        'unique_external_id',
                        'board',
                        'port',
                        'onu',
                        'zone_id',
                        'updated_at',
                        'last_synced_at'
                    ]);
                }

                OltOnu::whereIn('olt_id', $oltIds)
                    ->whereNotIn('sn', $objects->pluck('sn'))
                    ->delete();
            });
            return ['success' => true];
        } catch (\Throwable $th) {
            return ['success' => false, 'message' => $th->getMessage()];
        }
        return ['success' => true];
    }

    public function syncOnusSignals()
    {
        $response = $this->getONUsSignals();
        if (!$response['success']) {
            return $response;
        }
        $objects = collect($response['response']);
        if ($objects->isEmpty()) {
            return [
                'success' => false,
                'messag' => 'No existen datos para sincronizar'
            ];
        }
        try {
            DB::transaction(function () use ($objects) {
                $now = now();
                $chunkSize = 1000;

                $oltIds = $objects->pluck('olt_id')->unique()->filter()->toArray();
                $olts = Olt::whereIn('id', $oltIds)->get()->keyBy('id');

                $dataToUpdate = $objects->filter(function ($obj) use ($olts) {
                    return $olts->has($obj['olt_id']);
                })->map(function ($obj) use ($now, $olts) {
                    return [
                        'sn' => $obj['sn'],
                        'olt_id' => $obj['olt_id'],
                        'unique_external_id' => $obj['unique_external_id'],
                        'board' => $obj['board'],
                        'port' => $obj['port'],
                        'onu' => $obj['onu'],
                        'zone_id' => $obj['zone_id'],
                        'signal' => $obj['signal'],
                        'signal_1310' => $this->cleanSignal($obj['signal_1310']),
                        'signal_1490' => $this->cleanSignal($obj['signal_1490']),
                        'updated_at' => $now,
                        'last_synced_at' => $now
                    ];
                })->values()->toArray();

                foreach (array_chunk($dataToUpdate, $chunkSize) as $chunk) {
                    OltOnu::upsert($chunk, ['sn', 'olt_id'], [
                        'signal',
                        'signal_1490',
                        'signal_1310',
                        'unique_external_id',
                        'board',
                        'port',
                        'onu',
                        'zone_id',
                        'updated_at',
                        'last_synced_at'
                    ]);
                }

                OltOnu::whereIn('olt_id', $oltIds)
                    ->whereNotIn('sn', $objects->pluck('sn'))
                    ->delete();
            });
            return ['success' => true];
        } catch (\Throwable $th) {
            return ['success' => false, 'message' => $th->getMessage()];
        }
        return ['success' => true];
    }

    public function syncUnconfiguredOnus($id = null)
    {
        $response = $this->getUnconfiguredONUs($id);
        if (!$response['success']) {
            return $response;
        }
        $objects = collect($response['response']);
        if ($objects->isEmpty()) {
            OltUnconfiguredOnu::truncate();
            return [
                'success' => false,
                'message' => 'No existen datos para sincronizar'
            ];
        }
        try {
            DB::transaction(function () use ($objects) {
                $now = now();
                $chunkSize = 1000;

                $oltIds = $objects->pluck('olt_id')->unique()->filter()->toArray();
                $olts = Olt::whereIn('id', $oltIds)->get()->keyBy('id');

                $sns = $objects->pluck('sn')->toArray();

                $dataToUpdate = $objects->filter(function ($obj) use ($olts) {
                    return $olts->has($obj['olt_id']);
                })->map(function ($obj) use ($now, $olts) {
                    return [
                        'olt_id' => $olts[$obj['olt_id']]->id,
                        'board' => $obj['board'],
                        'port' => $obj['port'],
                        'pon_type' => $obj['pon_type'],
                        'pon_description' => $obj['pon_description'],
                        'sn' => $obj['sn'],
                        'onu_type_name' => $obj['onu_type_name'],
                        'updated_at' => $now,
                        'last_synced_at' => $now
                    ];
                })->values()->toArray();

                foreach (array_chunk($dataToUpdate, $chunkSize) as $chunk) {
                    OltUnconfiguredOnu::upsert(
                        $chunk,
                        ['sn'],
                        [
                            'pon_type',
                            'sn',
                            'onu_type_name',
                            'pon_description',
                            'updated_at',
                            'last_synced_at'
                        ]
                    );
                }

                OltUnconfiguredOnu::whereNotIn('sn', $sns)->delete();
            });
            return ['success' => true];
        } catch (\Throwable $th) {
            return ['success' => false, 'message' => $th->getMessage()];
        }
        return ['success' => true];
    }

    public function syncSpeedProfiles()
    {
        $response = $this->getSpeedProfiles();
        if ($response['success']) {
            $objects = collect($response['response']);
            DB::transaction(function () use ($objects) {
                $now = now();
                $data = $objects->map(function ($obj) use ($now) {
                    return [
                        'id' => $obj['id'],
                        'name' => $obj['name'],
                        'speed' => $obj['speed'],
                        'direction' => $obj['direction'],
                        'type' => $obj['type'],
                        'updated_at' => $now,
                        'created_at' => $now,
                        'last_synced_at' => $now,
                    ];
                })->toArray();
                OltSpeedProfile::upsert($data, ['id'], [
                    'name',
                    'speed',
                    'direction',
                    'type',
                    'updated_at',
                    'last_synced_at'
                ]);
                OltSpeedProfile::whereNotIn('id', $objects->pluck('id'))
                    ->delete();
                return [
                    'success' => true
                ];
            });
        }
        return $response;
    }

    public function syncZones()
    {
        $response = $this->getZones();
        if ($response['success']) {
            $objects = collect($response['response']);
            DB::transaction(function () use ($objects) {
                $now = now();
                $data = $objects->map(function ($obj) use ($now) {
                    return [
                        'id' => $obj['id'],
                        'name' => $obj['name'],
                        'updated_at' => $now,
                        'created_at' => $now,
                        'last_synced_at' => $now,
                    ];
                })->toArray();
                OltZone::upsert($data, ['id'], [
                    'name',
                    'updated_at',
                    'last_synced_at'
                ]);
                OltZone::whereNotIn('id', $objects->pluck('id'))
                    ->delete();
                return [
                    'success' => true
                ];
            });
        }
        return $response;
    }

    public function syncTypeOnus()
    {
        $response = $this->getTypeONUs();
        if ($response['success']) {
            $objects = collect($response['response']);
            DB::transaction(function () use ($objects) {
                $now = now();
                $data = $objects->map(function ($obj) use ($now) {
                    return [
                        'id' => $obj['id'],
                        'name' => $obj['name'],
                        'pon_type' => $obj['pon_type'],
                        'capability' => $obj['capability'],
                        'ethernet_ports' => $obj['ethernet_ports'],
                        'wifi_ports' => $obj['wifi_ports'],
                        'voip_ports' => $obj['voip_ports'],
                        'catv' => $obj['catv'],
                        'allow_custom_profiles' => $obj['allow_custom_profiles'],
                        'updated_at' => $now,
                        'created_at' => $now,
                        'last_synced_at' => $now,
                    ];
                })->toArray();
                OltTypeONU::upsert($data, ['id'], [
                    'name',
                    'updated_at',
                    'last_synced_at'
                ]);
                OltTypeONU::whereNotIn('id', $objects->pluck('id'))
                    ->delete();
            });
        }
        return $response;
    }

    public function syncODBs()
    {
        $response = $this->getODBs();
        if ($response['success']) {
            $objects = collect($response['response']);
            DB::transaction(function () use ($objects) {
                $now = now();
                $data = $objects->map(function ($obj) use ($now) {
                    return [
                        'id' => $obj['id'],
                        'name' => $obj['name'],
                        'latitude' => $obj['latitude'],
                        'longitude' => $obj['longitude'],
                        'zone_id' => $obj['zone_id'],
                        'zone_name' => $obj['zone_name'],
                        'updated_at' => $now,
                        'created_at' => $now,
                        'last_synced_at' => $now,
                    ];
                })->toArray();
                OltOdb::upsert($data, ['id'], [
                    'name',
                    'latitude',
                    'longitude',
                    'zone_id',
                    'zone_name',
                    'updated_at',
                    'last_synced_at'
                ]);
                OltOdb::whereNotIn('id', $objects->pluck('id'))
                    ->delete();
                return [
                    'success' => true
                ];
            });
        }
        return $response;
    }

    public function syncOnu($onu)
    {
        $response = $this->getOnuDetailsByExternalId($onu->unique_external_id);
        if ($response['success']) {
            $data = $this->getNormalizedData($response['onu_details']);
            $onu->update($data);
            $onu->refresh();
            return [
                'success' => true,
                'onu' => $onu
            ];
        }
        return $response;
    }

    public function syncSignal($id)
    {
        $onu = OltOnu::firstWhere('unique_external_id', $id);
        if ($onu) {
            $response = $this->getSignalByExternalId($onu->unique_external_id);
            if ($response['success']) {
                $onu->signal = $response['onu_signal'] !== '-' ? $response['onu_signal'] : 'Unknow';
                $onu->signal_1310 = $this->cleanSignal($response['onu_signal_1310']);
                $onu->signal_1490 = $this->cleanSignal($response['onu_signal_1490']);
                $onu->last_synced_at = now();
                $onu->save();
                return [
                    'success' => true,
                    'onu' => $onu
                ];
            }
            return $response;
        }

        return [
            'onu' => null,
            'success' => false
        ];
    }
    public function syncSignalsAndStatus($onu)
    {
        if ($onu->unique_external_id) {
            $response = $this->getSignalAndStatus($onu->unique_external_id);
            if ($response['success']) {
                $signals = $response['signals'];
                $status = $response['status'];
                if ($signals['success']) {
                    $onu->signal_1310 = $this->cleanSignal($signals['data']['onu_signal_1310']);
                    $onu->signal_1490 = $this->cleanSignal($signals['data']['onu_signal_1490']);
                    $onu->signal = $signals['data']['onu_signal'];
                }
                if ($status['success']) {
                    $onu->status = $status['data']['onu_status'];
                    $onu->last_status_change = !empty($status['data']['last_status_change']) ? $status['data']['last_status_change'] : null;
                }
                $onu->last_synced_at = now();
                $onu->save();
                return [
                    'success' => true,
                    'onu' => $onu
                ];
            }
            return $response;
        }

        return [
            'onu' => null,
            'success' => false
        ];
    }

    public function getNormalizedData($obj, $now = null, $freshSignal = null, $freshStatus = null)
    {
        $now = $now ?? now();
        $data = [
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
            'wan_mode' => $obj['wan_mode'],
            'vlan' => $obj['vlan'] ?? null,
            'odb_name' => $obj['odb_name'],
            'longitude' => $obj['longitude'],
            'latitude' => $obj['latitude'],
            'contact' => $obj['contact'],
            'voip_service' => $obj['voip_service'],
            'signal' => $freshSignal['signal'] ?? $obj['signal'],
            'signal_1310' => $this->cleanSignal($freshSignal['signal_1310'] ?? $obj['signal_1310']),
            'signal_1490' => $this->cleanSignal($freshSignal['signal_1490'] ?? $obj['signal_1490']),
            'authorization_date' => $obj['authorization_date'],
            'service_ports' => $obj['service_ports'],
            'ethernet_ports' => $obj['ethernet_ports'],
            'wifi_ports' => $obj['wifi_ports'],
            'voip_ports' => $obj['voip_ports'],
            'updated_at' => $now,
            'last_synced_at' => $now,
        ];
        return $data;
    }

    public function getOlts()
    {
        $response = $this->makeRequest('GET', 'system/get_olts');
        if ($response['success']) {
            $data = $response['response'];
            $timeEnvTemp = $this->getUpTimeEnviromentTemperatureByOlt();
            if ($timeEnvTemp['success']) {
                $merged = collect($data)->map(function ($item) use ($timeEnvTemp) {
                    $match = collect($timeEnvTemp['response'])->firstWhere('olt_id', $item['id']);
                    return $match ? array_merge($item, $match) : $item;
                })->toArray();
                $data = $merged;
            }
            return [
                'success' => true,
                'data' => $data
            ];
        }
        return $response;
    }

    public function getZones()
    {
        return $this->makeRequest('get', 'system/get_zones');
    }

    public function getSpeedProfiles()
    {
        return $this->makeRequest('get', 'system/get_speed_profiles');
    }

    public function getODBs()
    {
        return $this->makeRequest('get', 'system/get_odbs');
    }

    public function getTypeONUs()
    {
        return $this->makeRequest('get', 'system/get_onu_types');
    }

    public function getUpTimeEnviromentTemperatureByOlt()
    {
        return $this->makeRequest('get', 'olt/get_olts_uptime_and_env_temperature');
    }

    public function getCardsByOlt($id)
    {
        return $this->makeRequest('get', 'system/get_olt_cards_details/' . $id);
    }

    public function getPonPortsByOlt($id)
    {
        return $this->makeRequest('get', 'system/get_olt_pon_ports_details/' . $id);
    }

    public function getOutagePONsByOlt($id)
    {
        return $this->makeRequest('get', 'system/get_outage_pons/' . $id);
    }

    public function getUplinkPortsByOlt($id)
    {
        return $this->makeRequest('get', 'system/get_olt_uplink_ports_details/' . $id);
    }

    public function getVLANsByOlt($id)
    {
        return $this->makeRequest('get', 'olt/get_vlans/' . $id);
    }

    public function getUnconfiguredONUs($id = null)
    {
        if (isset($id)) {
            return $this->makeRequest('get', 'onu/unconfigured_onus_for_olt/' . $id);
        }
        return $this->makeRequest('get', 'onu/unconfigured_onus');
    }

    public function getONUsByOlt($id)
    {
        return $this->makeRequest('get', 'onu/get_all_onus_details?olt_id=' . $id);
    }

    public function getONUsSignals($id = null)
    {
        if (isset($id)) {
            return $this->makeRequest('get', 'onu/get_onus_signals?olt_id=' . $id);
        }
        return $this->makeRequest('get', 'onu/get_onus_signals');
    }

    public function getONUsStatus($id = null)
    {
        if (isset($id)) {
            return $this->makeRequest('get', 'onu/get_onus_statuses?olt_id=' . $id);
        }
        return $this->makeRequest('get', 'onu/get_onus_statuses');
    }

    public function getSignalByExternalId($id)
    {
        return $this->makeRequest('get', 'onu/get_onu_signal/' . $id);
    }

    public function getStatusByExternalId($id)
    {
        return $this->makeRequest('get', 'onu/get_onu_status/' . $id);
    }

    public function registerOnu($onu)
    {
        return $this->makeRequest('post', 'onu/authorize_onu', $onu);
    }

    public function getOnuDetailsBySN($sn)
    {
        return $this->makeRequest('get', 'onu/get_onus_details_by_sn/' . $sn);
    }

    public function getOnuDetailsByExternalId($id)
    {
        return $this->makeRequest('get', 'onu/get_onu_details/' . $id);
    }

    public function getGraphRequest($method, $endpoint)
    {
        try {
            $client = app('SmartOlt');
            $response = $client->request($method, $endpoint);
            return [
                'success' => true,
                'image' => 'data:image/png;base64,' . base64_encode($response->getBody()->getContents())
            ];
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $responseBody = (string) $e->getResponse()->getBody();
                $decodedError = json_decode($responseBody, true);
                return [
                    'success' => false,
                    ...$decodedError,
                    'status_code' => $e->getResponse()->getStatusCode()
                ];
            }
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getTrafficGraph($id)
    {
        return $this->getGraphRequest('get', 'onu/get_onu_traffic_graph/' . $id . '/hourly');
    }

    public function getSignalGraph($id)
    {
        return $this->getGraphRequest('get', 'onu/get_onu_signal_graph/' . $id . '/hourly');
    }

    public function getImageONU($id)
    {
        return $this->getGraphRequest('get', 'system/get_onu_type_image/' . $id);
    }

    public function getMgmTIp($id)
    {
        return $this->makeRequest('get', 'onu/get_mgmt_ip_address/' . $id);
    }

    public function getIpAddress($id)
    {
        return $this->makeRequest('get', 'onu/get_ip_address/' . $id);
    }

    public function resyncONUConfig($id)
    {
        return $this->makeRequest('post', 'onu/resync_config/' . $id);
    }

    public function rebootONU($id)
    {
        return $this->makeRequest('post', 'onu/reboot/' . $id);
    }

    public function moveONU($onuExternalId, $data)
    {
        return $this->makeRequest('post', 'onu/move/' . $onuExternalId, $data);
    }

    public function updateONULocation($onuExternalId, $data)
    {
        return $this->makeRequest('post', 'onu/update_location_details/' . $onuExternalId, $data);
    }

    public function updateExternalId($onuExternalId, $data)
    {
        return $this->makeRequest('post', 'onu/update_unique_external_id/' . $onuExternalId, $data);
    }

    public function changeAttachedVlans($onuExternalId, $data)
    {
        return $this->makeRequest('post', 'onu/update_attached_vlans/' . $onuExternalId, $data);
    }

    public function updateServicePort($onuExternalId, $data)
    {
        return $this->makeRequest('post', 'onu/update_service_port/' . $onuExternalId, $data);
    }

    public function configureEhernetPort($onuExternalId, $data, $type = 'lan')
    {
        return $this->makeRequest('post', sprintf('onu/set_ethernet_port_%s/%s', Str::lower($type), $onuExternalId), $data);
    }

    public function configureWifiPort($onuExternalId, $data, $type = 'lan')
    {
        return $this->makeRequest('post', sprintf('onu/set_wifi_port_%s/%s', Str::lower($type), $onuExternalId), $data);
    }

    public function setOnuMgmtIp($onuExternalId, $data, $type = 'inactive')
    {
        return $this->makeRequest('post', sprintf('onu/set_onu_mgmt_ip_%s/%s', Str::lower($type), $onuExternalId), $data);
    }

    public function setWanMode($onuExternalId, $mode = 'setup_via_onu_webpage', $data)
    {
        return $this->makeRequest('post', sprintf('onu/set_onu_wan_mode_%s/%s', Str::lower($mode), $onuExternalId), $data);
    }

    public function setOnuVoipPort($id, $status, $data)
    {
        return $this->makeRequest('post', sprintf('onu/%s_onu_voip_port/%s', $status, $id), $data);
    }

    public function updateChannel($id, $data)
    {
        return $this->makeRequest('post', 'onu/update_pon_channel/' . $id, $data);
    }

    // public function updateMode($id, $data)
    // {
    //     return $this->makeRequest('post', 'onu/update_onu_mode/' . $id, $data);
    // }

    public function updateMgmtAndVoIp($onuExternalId, $data)
    {
        $tr069 = $data['tr069'];
        $voIp = $data['voIp'];
        $requests = [
            'tr069' => [
                'method' => 'post',
                'url' => sprintf('onu/%s_tr069/%s', $tr069['status'], $onuExternalId),
                'params' => $tr069['attr_to_server']
            ],
            'voIp' => [
                'method' => 'post',
                'url' => sprintf('onu/set_onu_voip_%s/%s', $voIp['status'], $onuExternalId),
                'params' => $voIp['attr_to_server']
            ]
        ];
        return $this->makeRequestAsync($requests);
    }

    public function updateMode($onuExternalId, $data)
    {
        $vlan = $data['vlan'];
        $wanMode = $data['wanMode'] ?? null;
        $onuMode = $data['onuMode'];
        $requests = [
            'vlan' => [
                'method' => 'post',
                'url' => sprintf('onu/update_main_vlan/%s', $onuExternalId),
                'params' => $vlan['attr_to_server']
            ],
            'onuMode' => [
                'method' => 'post',
                'url' => 'onu/update_onu_mode/' . $onuExternalId,
                'params' => $onuMode['attr_to_server']
            ],
        ];
        if ($wanMode) {
            $requests['wanMode'] = [
                'method' => 'post',
                'url' => sprintf('onu/set_onu_wan_mode_%s/%s', Str::lower($wanMode['mode']), $onuExternalId),
                'params' => $wanMode['attr_to_server']
            ];
        }
        return $this->makeRequestAsync($requests);
    }

    public function changeOnuType($onuExternalId, $data)
    {
        $type = $data['type'];
        $profile = $data['profile'];
        $requests = [
            'type' => [
                'method' => 'post',
                'url' => 'onu/change_onu_type/' . $onuExternalId,
                'params' => $type['attr_to_server']
            ],
            'profile' => [
                'method' => 'post',
                'url' => 'onu/change_custom_profile/' . $onuExternalId,
                'params' => $profile['attr_to_server']
            ],
        ];
        return $this->makeRequestAsync($requests);
    }

    public function shutdownEhernetPort($onuExternalId, $data)
    {
        return $this->makeRequest('post', 'onu/shutdown_ethernet_port/' . $onuExternalId, $data);
    }

    public function shutdownWifiPort($onuExternalId, $data)
    {
        return $this->makeRequest('post', 'onu/shutdown_wifi_port/' . $onuExternalId, $data);
    }

    public function enableDisableONU($id, $enable)
    {
        return $this->makeRequest('post', 'onu/' . ($enable ? 'enable' : 'disable') . '/' . $id);
    }

    public function removeONU($id)
    {
        return $this->makeRequest('post', 'onu/delete/' . $id);
    }

    public function addZone($data)
    {
        return $this->makeRequest('post', 'system/add_zone', $data);
    }

    public function addOdb($data)
    {
        return $this->makeRequest('post', 'system/add_odb', $data);
    }

    public function addTypeOnu($data)
    {
        return $this->makeRequest('post', 'system/add_onu_type', $data);
    }

    public function addVlan($id, $data)
    {
        return $this->makeRequest('post', 'olt/add_vlan/' . $id, $data);
    }

    public function getFullStatus($onuExternalId)
    {
        return $this->makeRequest('get', 'onu/get_onu_full_status_info/' . $onuExternalId);
    }

    public function getRunningConfig($onuExternalId)
    {
        return $this->makeRequest('get', 'onu/get_running_config/' . $onuExternalId);
    }

    public function changeWebUserPass($onuExternalId)
    {
        return $this->makeRequest('post', 'onu/change_web_user_pass/' . $onuExternalId);
    }

    public function setCATV($onuExternalId, $catv = 'enable')
    {
        return $this->makeRequest('post', sprintf('onu/%s_catv/%s', $catv, $onuExternalId));
    }

    public function billings()
    {
        return $this->makeRequest('get', sprintf('system/get_billing_details'));
    }
}
