<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_invoice_id',
        'inventory_item_id',
        'purchase_type',
        'quantity',
        'store_price',
        'total',
        'bulk_quantity',
        'notes',
    ];

    protected $casts = [
        'quantity'      => 'float',
        'store_price'   => 'float',
        'total'         => 'float',
        'bulk_quantity' => 'integer',
    ];

    public function supplierInvoice()
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function inventoryItemStocks()
    {
        return $this->hasMany(InventoryItemStock::class);
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class, 'supplier_invoice_item_id');

    }
}
