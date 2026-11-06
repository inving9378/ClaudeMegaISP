<?php

namespace App\Modules\Core\ModuleManager\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleContract extends Model
{
    protected $table = 'module_contracts';

    protected $fillable = [
        'modulo_proveedor', 'tipo', 'nombre', 'version',
        'estado', 'dueno_designado', 'contrato', 'fecha_estado',
    ];

    protected $casts = [
        'contrato'     => 'array',
        'fecha_estado' => 'datetime',
    ];

    public function consumers()
    {
        return $this->hasMany(ModuleContractConsumer::class, 'contract_id');
    }
}
