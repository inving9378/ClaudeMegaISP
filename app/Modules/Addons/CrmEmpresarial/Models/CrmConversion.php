<?php

namespace App\Modules\Addons\CrmEmpresarial\Models;

use Illuminate\Database\Eloquent\Model;

/** Item #563: traza prospecto → cliente. Append-only. */
class CrmConversion extends Model
{
    protected $table = 'crm_conversiones';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['snapshot' => 'array', 'convertido_at' => 'datetime'];
}
