<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoRoleDepartment extends BaseModel
{
    protected $table = 'talento_role_departments';

    protected $fillable = ['role_name', 'department'];
}
