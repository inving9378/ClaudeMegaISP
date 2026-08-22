<?php

namespace App\Modules\Addons\Ipv6\Models;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;

// Model plano (NO BaseModel): clientes_ipv6_config no tiene created_by/updated_by.
class ClienteIpv6Config extends Model
{
    protected $table = 'clientes_ipv6_config';

    protected $fillable = [
        'client_id',
        'ipv6_habilitado',
        'cpe_compatible',
        'cpe_modelo',
        'cpe_verificado_en',
        'politica_entrante',
    ];

    protected $casts = [
        'ipv6_habilitado'   => 'boolean',
        'cpe_verificado_en' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
