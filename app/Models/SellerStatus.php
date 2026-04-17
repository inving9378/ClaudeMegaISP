<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerStatus extends BaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'seller_status';

    protected $fillable = [
        'name'
    ];

    public function seller()
    {
        return $this->hasMany(Seller::class, 'status_id');
    }
}
