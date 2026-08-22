<?php

namespace App\Modules\Addons\Ipv6\Models;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

// Model plano (NO BaseModel): clientes_ipv6_excepciones no tiene created_by/updated_by.
class ClienteIpv6Excepcion extends Model
{
    protected $table = 'clientes_ipv6_excepciones';

    protected $fillable = [
        'client_id',
        'descripcion',
        'protocolo',
        'puertos',
        'direccion_destino',
        'creado_por',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected $casts = [
        'vigente_desde' => 'datetime',
        'vigente_hasta' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
