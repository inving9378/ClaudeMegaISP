<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class TalentoColaborador extends BaseModel
{
    use SoftDeletes;

    protected $table = 'talento_colaboradores';

    protected $fillable = [
        'user_id', 'type', 'department', 'supervisor_id',
        'hire_date', 'status', 'base_salary', 'notes',
    ];

    protected $casts = [
        'hire_date'   => 'date',
        'base_salary' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(TalentoColaborador::class, 'supervisor_id');
    }

    public function subordinados()
    {
        return $this->hasMany(TalentoColaborador::class, 'supervisor_id');
    }

    public function devices()
    {
        return $this->hasMany(TalentoDevice::class, 'user_id', 'user_id');
    }
}
