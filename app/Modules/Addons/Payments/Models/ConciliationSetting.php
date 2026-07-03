<?php

namespace App\Modules\Addons\Payments\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fila de un interruptor editable de la automatización de conciliación.
 * La lógica de lectura/escritura con fallback a config() vive en
 * {@see \App\Modules\Addons\Payments\Support\ConciliationSettings}.
 */
class ConciliationSetting extends Model
{
    protected $table = 'conciliation_settings';

    protected $fillable = ['key', 'enabled', 'updated_by'];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
