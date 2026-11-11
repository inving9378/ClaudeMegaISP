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
        'potencia_inicial_dbm',
        'potencia_inicial_at',
        'zone',
        'data',
    ];

    /**
     * Mismos campos calculados que App\Models\MapDevicePort: la pantalla de conexiones
     * (useMapConnections.js) distingue los puertos por element_id/model_type y pinta la
     * selección con selected. Sin ellos, en el site no se podía conectar un puerto con
     * otro (el segundo clic se tomaba como el mismo puerto) ni dibujar las conexiones.
     * `dbs` y el nombre del cliente no se copian: el Mapa de Red ya muestra la potencia
     * por su cuenta (ClientComponent → potencia).
     */
    protected $appends = ['selected', 'model_type', 'element_id', 'device_type'];

    /**
     * El equipo se carga para calcular lo de arriba, pero no se manda en el JSON: la pantalla
     * solo necesita su tipo (device_type) y con la ficha completa la respuesta de un site
     * casi se duplicaba de tamaño.
     */
    protected $hidden = ['device'];

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

    public function getSelectedAttribute()
    {
        return false;
    }

    public function getModelTypeAttribute()
    {
        return $this::class;
    }

    public function getDeviceTypeAttribute()
    {
        return $this->device?->type;
    }

    public function getElementIdAttribute()
    {
        return sprintf('%s-port-%s-%d', $this->device?->type, $this->type, $this->id);
    }
}
