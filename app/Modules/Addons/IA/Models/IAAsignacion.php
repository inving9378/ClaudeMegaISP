<?php

namespace App\Modules\Addons\IA\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IAAsignacion extends BaseModel
{
    protected $table = 'ia_asignaciones';

    protected $fillable = ['clave', 'ia_proveedor_id', 'modelo', 'created_by', 'updated_by'];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(IAProveedor::class, 'ia_proveedor_id');
    }
}
