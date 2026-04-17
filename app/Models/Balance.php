<?php

namespace App\Models;

use App\Http\Traits\Models\Balance\ScopeBalance\ScopeBalance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Balance extends BaseModel
{
    use ScopeBalance;
    use HasFactory;
    protected $guarded = [];

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly(['amount'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // TODO agregar observer que si el cliente al que se le cambia el balance es recurrente y no tiene periodo de gracia activo, crearselo
    // TODO verificar que en todos los lugares siempre antes de crear el periodo de gracia no exista uno
    // TODO llamar la logica del periodo de gracia desde el servicio, aqui solo se pregunta se el cliente es recurrente y su saldo paso de >=0 a < que 0.

    public function moreThanAsAmount($cost){
       return $this->amount > $cost;
    }

    public function getAmount(){
        return $this->amount;
    }

    public function scopeNegativeBalance($query)
    {
        $query->where('amount', '<', 0);
    }

    public function scopePositiveBalance($query)
    {
        $query->where('amount', '>=', 0);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'balanceable_id', 'id');
    }
}
