<?php

namespace App\Modules\Addons\LogsTerceros\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoLog extends Model
{
    protected $table = 'logs_terceros_grupos';

    protected $fillable = ['nombre', 'descripcion'];

    public function archivos(): HasMany
    {
        return $this->hasMany(ArchivoLog::class, 'grupo_id')->orderBy('nombre');
    }
}
