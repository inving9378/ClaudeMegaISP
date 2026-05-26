<?php

namespace App\Modules\Addons\Payments\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Configuración de proveedor externo de pagos (OpenPay, Stripe, etc.).
 *
 * El campo $config usa cast 'encrypted:json' → en BD se almacena como
 * blob encriptado con APP_KEY; al leer/escribir se ve como array PHP
 * sin que tengas que llamar a Crypt manualmente.
 *
 * Estructura esperada del config por proveedor:
 *   openpay:  ['merchant_id', 'api_key', 'webhook_secret', 'sandbox' => bool]
 *   stripe:   ['publishable_key', 'secret_key', 'webhook_secret']
 *   conekta:  ['public_key', 'private_key', 'webhook_secret']
 */
class PaymentProvider extends BaseModel
{
    use SoftDeletes;

    protected $table = 'payment_providers';

    protected $fillable = [
        'name',
        'provider',
        'is_active',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config'    => 'encrypted:json',
    ];

    /** Las creds nunca salen en serialización default. Si necesitas verlas, accédelas explícitamente. */
    protected $hidden = ['config'];

    public function clabes()
    {
        return $this->hasMany(PaymentClabe::class);
    }
}
