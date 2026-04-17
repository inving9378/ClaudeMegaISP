<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class GeneralConfigurationRule extends Model
{
    use HasFactory;
    protected $table = 'general_configuration_rule';

    protected $fillable = ['installation_cost',  'iva', 'created_at', 'updated_at'];

    public static function boot()
    {
        parent::boot();
        static::saved(function ($obj) {
            HistoryGeneralConfigurationRule::create([
                'rule_id' => $obj->id,
                'data' => $obj,
                'created_at' => $obj->updated_at,
                'updated_at' => $obj->updated_at
            ]);
        });
    }
}
