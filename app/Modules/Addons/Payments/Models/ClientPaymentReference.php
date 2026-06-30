<?php

namespace App\Modules\Addons\Payments\Models;

use App\Models\BaseModel;
use App\Models\Client;

/**
 * Clave de conciliación estable e inmutable por cliente (motor nativo).
 * Se genera automáticamente al crear el cliente (ClientObserver) o vía backfill;
 * NO hay edición manual. Ver PaymentReferenceService para el algoritmo.
 */
class ClientPaymentReference extends BaseModel
{
    protected $table = 'client_payment_references';

    protected $fillable = [
        'client_id',
        'reference',
        'algo_version',
    ];

    protected $casts = [
        'algo_version' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
