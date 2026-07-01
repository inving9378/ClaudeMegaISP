<?php

namespace App\Modules\Addons\PortalPago\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta de cobro propia de Meganet (CLABE a la que el cliente transfiere SPEI).
 */
class PortalPagoAccount extends Model
{
    protected $table = 'portal_pago_accounts';

    protected $fillable = [
        'nombre',
        'clabe',
        'cuenta',
        'tarjeta',
        'banco',
        'titular',
        'beneficiario',
        'activa',
        'instance_id',
    ];

    protected $casts = [
        'activa'      => 'boolean',
        'instance_id' => 'integer',
    ];

    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PortalPagoPaymentLink::class, 'account_id');
    }

    public function recurrences(): HasMany
    {
        return $this->hasMany(PortalPagoRecurrence::class, 'account_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }
}
