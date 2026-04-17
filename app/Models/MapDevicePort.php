<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapDevicePort extends Model
{
    use HasFactory;

    protected $table = 'map_devices_ports';

    protected $fillable = [
        'name',
        'type',
        'orientation',
        'device_id',
        'client_id',
        'connected',
        'note',
        'transfer',
        'transfer_type',
        'card',
        'zone',
        'data'
    ];

    protected $appends = ['selected', 'model_type', 'element_id', 'device_type', 'dbs'];

    protected $casts = [
        'connected' => 'boolean',
        'data' => 'json'
    ];

    protected $with = ['client'];

    public function device()
    {
        return $this->belongsTo(MapDevice::class, 'device_id');
    }

    public function client()
    {
        return $this->belongsTo(ClientMainInformation::class, 'client_id');
    }

    public function connections_from()
    {
        return $this->morphMany(MapDevicePortConnection::class, 'from');
    }

    public function connections_to()
    {
        return $this->morphMany(MapDevicePortConnection::class, 'to');
    }

    public function getSelectedAttribute()
    {
        return false;
    }

    public function getNameAttribute($val)
    {
        if (isset($val)) {
            return $val;
        }
        if ($this->client) {
            return $this->client->client_name_with_fathers_names;
        }
        return null;
    }

    public function getDbsattribute($val)
    {
        if ($this->client) {
            $pwr = ClientAdditionalInformation::firstWhere('client_id', $this->client->client_id);
            return $pwr ? $pwr->power_dbm : null;
        }
        return null;
    }

    public function getModelTypeAttribute()
    {
        return $this::class;
    }

    public function getDeviceTypeAttribute()
    {
        return $this->device->type;
    }

    public function getElementIdAttribute()
    {
        return sprintf('%s-port-%s-%d', $this->device->type, $this->type, $this->id);
    }
}
