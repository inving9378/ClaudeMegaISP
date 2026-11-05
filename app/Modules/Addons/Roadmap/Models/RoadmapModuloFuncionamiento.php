<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

/** #1145 — versión (append-only) de «cómo debe funcionar» un módulo del roadmap. */
class RoadmapModuloFuncionamiento extends Model
{
    public $timestamps = false;

    protected $table = 'roadmap_modulo_funcionamiento';

    protected $fillable = ['modulo', 'texto', 'autor_id', 'autor_nombre'];

    protected $casts = ['created_at' => 'datetime'];
}
