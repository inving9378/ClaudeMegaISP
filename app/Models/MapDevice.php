<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapDevice extends Model
{
    use HasFactory;

    protected $table = 'map_devices';

    protected $fillable = [
        'name',
        'type',
        'description',
        'position_x',
        'position_y',
        'orientation',
        'layer_id',
        'parent_id',
        'data'
    ];

    protected $casts = ['data' => 'json'];

    protected $appends = ['active'];

    public function layer()
    {
        return $this->belongsTo(MapLayer::class, 'layer_id');
    }

    public function ports()
    {
        return $this->hasMany(MapDevicePort::class, 'device_id');
    }

    public function device()
    {
        return $this->belongsTo(MapDevice::class, 'parent_id');
    }

    public function devices()
    {
        return $this->hasMany(MapDevice::class, 'parent_id');
    }

    public function getActiveAttribute()
    {
        return 'in';
    }

    public function scopeType($query, $val)
    {
        return $query->where('type', $val);
    }

    public function createPorts()
    {
        if ($this->type === 'splitter') {
            $this->createSplitterPorts();
        } else if ($this->type === 'organizer') {
            $this->createOrganizerPorts();
        } else if ($this->type === 'switch' || $this->type === 'router') {
            $this->createSwitchPorts();
        } else if ($this->type === 'olt') {
            $this->createOltPorts();
        }
    }

    public function createSplitterPorts()
    {
        $ports = [];
        $date = now();
        $index = 1;
        if (count($this->ports) === 0) {
            $ports[] = [
                'name' => 'IN',
                'type' => 'in',
                'device_id' => $this->id,
                'created_at' => $date,
                'updated_at' => $date
            ];
        } else {
            $index = count($this->ports);
        }
        for ($i = $index; $i <= $this->data['ports']; $i++) {
            $ports[] = [
                'name' => $i <= 9 ? ('0' . $i) : $i,
                'type' => 'out',
                'device_id' => $this->id,
                'created_at' => $date,
                'updated_at' => $date,
            ];
        }
        if (count($ports) !== 0) {
            MapDevicePort::insert($ports);
        }
    }

    public function createOrganizerPorts()
    {
        if (count($this->ports) == 0) {
            $ports = [];
            $date = now();
            $index = 1;
            $rows = $this->data['rows'];
            $columns = $this->data['columns'];
            for ($i = $index; $i <= $rows * $columns; $i++) {
                $ports[] = [
                    'name' => $i <= 9 ? ('0' . $i) : $i,
                    'type' => 'in',
                    'device_id' => $this->id,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            $index = 1;
            for ($i = $index; $i <= $rows * $columns; $i++) {
                $ports[] = [
                    'name' => $i <= 9 ? ('0' . $i) : $i,
                    'type' => 'out',
                    'device_id' => $this->id,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            if (count($ports) !== 0) {
                MapDevicePort::insert($ports);
            }
        }
    }

    public function createSwitchPorts()
    {
        if (count($this->ports) == 0) {
            $ports = [];
            $date = now();
            for ($i = 1; $i <= $this->data['ports_eth']; $i++) {
                $ports[] = [
                    'name' => $i <= 9 ? ('0' . $i) : $i,
                    'type' => 'in',
                    'device_id' => $this->id,
                    'transfer' => null,
                    'transfer_type' => null,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            $console_ports = $this->data['console_ports'] ?? 0;
            for ($i = 1; $i <= $console_ports; $i++) {
                $ports[] = [
                    'name' => $i <= 9 ? ('0' . $i) : $i,
                    'type' => 'console',
                    'device_id' => $this->id,
                    'transfer' => null,
                    'transfer_type' => null,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            $ports_1_gb = $this->data['ports_1_gb'] ?? 0;
            for ($i = 1; $i <= $ports_1_gb; $i++) {
                $ports[] = [
                    'name' => $i <= 9 ? ('0' . $i) : $i,
                    'type' => 'in',
                    'device_id' => $this->id,
                    'transfer' => 1,
                    'transfer_type' => 'GB',
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            $ports_10_gb = $this->data['ports_10_gb'] ?? 0;
            for ($i = 1; $i <= $ports_10_gb; $i++) {
                $ports[] = [
                    'name' => $i <= 9 ? ('0' . $i) : $i,
                    'type' => 'in',
                    'device_id' => $this->id,
                    'transfer' => 10,
                    'transfer_type' => 'GB',
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            $ports_100_gb = $this->data['ports_100_gb'] ?? 0;
            for ($i = 1; $i <= $ports_100_gb; $i++) {
                $ports[] = [
                    'name' => $i <= 9 ? ('0' . $i) : $i,
                    'type' => 'in',
                    'device_id' => $this->id,
                    'transfer' => 100,
                    'transfer_type' => 'GB',
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            if (count($ports) !== 0) {
                MapDevicePort::insert($ports);
            }
        }
    }

    public function createOltPorts()
    {
        if (count($this->ports) == 0) {
            $ports = [];
            $date = now();
            $data =  $this->data;
            $wan_ports = $data['wan_ports'] ?? 0;
            for ($i = 1; $i <= $wan_ports; $i++) {
                $ports[] = [
                    'name' => $i <= 9 ? ('0' . $i) : $i,
                    'type' => 'in',
                    'device_id' => $this->id,
                    'transfer' => null,
                    'transfer_type' => null,
                    'card' => null,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            $console_ports = $data['console_ports'] ?? 0;
            for ($i = 1; $i <= $console_ports; $i++) {
                $ports[] = [
                    'name' => $i <= 9 ? ('0' . $i) : $i,
                    'type' => 'console',
                    'device_id' => $this->id,
                    'transfer' => null,
                    'transfer_type' => null,
                    'card' => null,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
            $cards = $data['service_cards'] ?? [];
            for ($i = 0; $i < count($cards); $i++) {
                for ($j = 0; $j < $this->data['service_cards'][$i]['ports']; $j++) {
                    $ports[] = [
                        'name' => $j <= 9 ? ('0' . $j) : $j,
                        'type' => 'out',
                        'device_id' => $this->id,
                        'transfer' => null,
                        'transfer_type' => null,
                        'card' => $i + 1,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ];
                }
            }
            if (count($ports) !== 0) {
                MapDevicePort::insert($ports);
            }
        }
    }
}
