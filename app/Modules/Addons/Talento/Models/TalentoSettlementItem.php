<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoSettlementItem extends Model
{
    protected $table = 'talento_settlement_items';

    protected $fillable = [
        'settlement_id', 'stock_id', 'inventory_item_id', 'item_name',
        'unit_cost', 'current_stock', 'disposition', 'debit_amount', 'notes',
    ];

    protected $casts = [
        'unit_cost'     => 'decimal:2',
        'current_stock' => 'decimal:3',
        'debit_amount'  => 'decimal:2',
    ];

    public function settlement()
    {
        return $this->belongsTo(TalentoSettlement::class, 'settlement_id');
    }
}
