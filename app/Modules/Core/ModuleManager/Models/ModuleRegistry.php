<?php

namespace App\Modules\Core\ModuleManager\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleRegistry extends Model
{
    protected $table = 'module_registry';

    protected $fillable = [
        'slug',
        'name',
        'version',
        'type',
        'active',
        'installed_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'installed_at' => 'datetime',
    ];
}
