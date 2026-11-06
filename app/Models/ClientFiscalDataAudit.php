<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** #1152: un renglón por campo cambiado de «Datos de facturación» (append-only). */
class ClientFiscalDataAudit extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
