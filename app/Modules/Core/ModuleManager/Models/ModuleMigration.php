<?php

namespace App\Modules\Core\ModuleManager\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleMigration extends Model
{
    public $timestamps = false;

    protected $table = 'module_migrations';

    protected $fillable = ['module_slug', 'migration', 'ran_at'];

    protected $casts = ['ran_at' => 'datetime'];
}
