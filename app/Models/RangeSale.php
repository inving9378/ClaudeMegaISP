<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RangeSale extends BaseModel
{
    use HasFactory;
    protected $table = 'ranges_of_sales_sectors';

    protected $fillable = [
        'sector',
        'range',
        'number_of_prospects',
        'number_of_sales',
    ];
}
