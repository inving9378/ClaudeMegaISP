<?php

namespace App\Modules\Addons\MapaRed\Models;

use App\Models\ClientMainInformation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Espejo de App\Models\MapDevicePort, apuntando a mapared_devices_ports (MR-06a-1, item #9990333).
 */
class MapaRedDevicePort extends Model
{
    use HasFactory;

    protected $table = 'mapared_devices_ports';

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
        'data',
    ];

    protected $casts = [
        'connected' => 'boolean',
        'data' => 'json',
    ];

    public function device()
    {
        return $this->belongsTo(MapaRedDevice::class, 'device_id');
    }

    public function client()
    {
        return $this->belongsTo(ClientMainInformation::class, 'client_id');
    }

    public function connections_from()
    {
        return $this->morphMany(MapaRedDevicePortConnection::class, 'from');
    }

    public function connections_to()
    {
        return $this->morphMany(MapaRedDevicePortConnection::class, 'to');
    }
}
