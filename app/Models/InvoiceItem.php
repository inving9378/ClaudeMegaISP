<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'modelable_type',
        'modelable_id',
        'name',
        'description',
        'quantity',
        'unit_price',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2'
    ];

    /**
     * Relación con la factura
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Relación polimórfica con el item (servicio, producto, etc.)
     */
    public function modelable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope para items de tipo servicio
     */
    public function scopeInternetServices($query)
    {
        return $query->where('modelable_type', 'App\Models\ClientInternetService');
    }

    /**
     * Scope para items de tipo servicio
     */
    public function scopeBundleService($query)
    {
        return $query->where('modelable_type', 'App\Models\ClientBundleService');
    }

    /**
     * Scope para items de tipo servicio
     */
    public function scopeVozService($query)
    {
        return $query->where('modelable_type', 'App\Models\ClientVozService');
    }

    /**
     * Obtener tipo de item legible
     */
    public function getItemTypeAttribute(): string
    {
        $types = [
            'App\Models\Service' => 'servicio'
        ];

        return $types[$this->modelable_type] ?? 'item';
    }
}
