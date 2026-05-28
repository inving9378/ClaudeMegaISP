<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class DataPlanPromotion extends BaseModel
{
    use HasFactory;

    protected $table = 'data_plan_promotion';

    protected $fillable = ['name', 'upload', 'download', 'duration', 'defined_by_user', 'type_duration'];

    protected $appends = ['caduced'];

    protected $casts = ['defined_by_user' => 'boolean'];

    protected static function booted()
    {
        static::created(function ($obj) {
            Promotion::create([
                'promotionable_type' => $obj::class,
                'promotionable_id' => $obj->id,
                'code' => 'data_plan_promotion'
            ]);
        });

        static::deleted(function ($obj) {
            $p = Promotion::where('promotionable_type', $obj::class)->where('promotionable_id', $obj->id)->first();
            if ($p) {
                $p->delete();
            }
        });
    }

    public function getCaducedAttribute()
    {
        if ($this->defined_by_user) {
            return 'Definido por el usuario';
        }
        return sprintf('%d %s', $this->duration, $this->type_duration === 'day' ? 'Días' : 'Meses');
    }
}
