<?php

namespace App\Modules\Addons\LogsTerceros\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivoLog extends Model
{
    protected $table = 'logs_terceros_archivos';

    protected $fillable = ['grupo_id', 'nombre', 'ruta'];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoLog::class, 'grupo_id');
    }
}
