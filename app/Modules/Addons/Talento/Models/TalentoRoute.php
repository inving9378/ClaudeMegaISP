<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoRoute extends BaseModel
{
    protected $table = 'talento_routes';

    protected $fillable = ['colaborador_id', 'date', 'status'];

    protected $casts = ['date' => 'date'];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class);
    }

    public function stops()
    {
        return $this->hasMany(TalentoRouteStop::class, 'route_id')->orderBy('sequence');
    }

    public function deviations()
    {
        return $this->hasMany(TalentoRouteDeviation::class, 'route_id')->orderBy('detected_at');
    }
}
