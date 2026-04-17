<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class HistoryGeneralConfigurationRule extends BaseModel
{
    use HasFactory;
    protected $table = 'history_general_configuration_rule';

    protected $fillable = ['rule_id', 'data', 'created_at', 'updated_at'];

    protected $casts = [
        'data' => 'json'
    ];
}
