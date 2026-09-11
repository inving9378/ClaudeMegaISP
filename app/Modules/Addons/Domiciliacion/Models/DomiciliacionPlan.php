<?php

namespace App\Modules\Addons\Domiciliacion\Models;

use Illuminate\Database\Eloquent\Model;

class DomiciliacionPlan extends Model
{
    protected $table = 'domiciliacion_plans';

    protected $fillable = ['amount', 'openpay_plan_id', 'name'];

    protected $casts = ['amount' => 'decimal:2'];
}
