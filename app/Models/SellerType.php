<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerType extends BaseModel
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'seller_types';

    protected $fillable = [
        'name'
    ];

    public function seller()
    {
        return $this->hasMany(Seller::class, 'type_id');
    }
}
