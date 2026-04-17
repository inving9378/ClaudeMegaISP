<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'created_by',
        'updated_by'
    ];

    /*
    |----------------------------------------------------------------------------
    |  scopes
    |----------------------------------------------------------------------------
    */
    public function scopeName($querry, $text)
    {
        if(!empty($text)){
            return $querry->where('brands.name', 'LIKE', "%$text%");
        }
    }
}
