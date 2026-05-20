<?php

namespace App\Modules\Core\Configuracion\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefaultValue extends Model
{
    use HasFactory;
    protected $fillable = [
        "user_id",
        "module_id",
        "field",
        "value"
    ];
}
