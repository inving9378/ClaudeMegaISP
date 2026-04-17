<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class DiscountSale extends Model
{
    use HasFactory;

    protected $table = 'discounts_sales';

    protected $fillable = ['discount_id', 'sale_id', 'rule_id', 'discount', 'type', 'data'];

    protected $casts = [
        'data' => 'json'
    ];

    public function discount()
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }

    public function sale()
    {
        return $this->belongsTo(ClientMainInformation::class, 'sale_id');
    }
}
