<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use App\Traits\BelongsToClientTenant;
use Illuminate\Database\Eloquent\SoftDeletes;

class FleetClientOperator extends BaseModel
{
    use SoftDeletes;
    use BelongsToClientTenant;

    protected $table = 'fleet_client_operators';

    protected $fillable = [
        'client_id', 'nombre', 'telefono', 'licencia_numero', 'licencia_vigencia', 'notas', 'activo',
    ];

    protected $casts = [
        'licencia_vigencia' => 'date',
        'activo'            => 'boolean',
    ];

    public function assignments()
    {
        return $this->hasMany(FleetAssignment::class, 'client_operator_id');
    }
}
