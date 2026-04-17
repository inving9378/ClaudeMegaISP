<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HistorySellerRule extends BaseModel
{
    use HasFactory;

    protected $table = 'history_sellers_rules';

    protected $fillable = ['seller_id', 'rule_id', 'data', 'created_at', 'updated_at'];

    protected $casts = [
        'data' => 'json'
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function rule()
    {
        return $this->belongsTo(CommissionRule::class, 'rule_id');
    }
}
