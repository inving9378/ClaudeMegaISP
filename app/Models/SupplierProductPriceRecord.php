<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierProductPriceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_product_price_id',
        'base_price',
        'price',
        'bulk_price',
        'bulk_min_quantity',
        'source',
        'supplier_invoice_id',
        'changed_by',
    ];

    protected $casts = [
        'base_price'        => 'float',
        'price'             => 'float',
        'bulk_price'        => 'float',
        'bulk_min_quantity' => 'integer',
    ];

    public function supplierProductPrice()
    {
        return $this->belongsTo(SupplierProductPrice::class);
    }

    public function supplierInvoice()
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
