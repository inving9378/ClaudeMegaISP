<?php

namespace App\Modules\Core\ModuleManager\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleContractConsumer extends Model
{
    protected $table = 'module_contract_consumers';

    protected $fillable = [
        'contract_id', 'modulo_consumidor', 'tipo_uso', 'estado_migracion', 'notas',
    ];

    public function contract()
    {
        return $this->belongsTo(ModuleContract::class, 'contract_id');
    }
}
