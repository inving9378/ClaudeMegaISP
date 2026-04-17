<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediumOfSale extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'medium_sales';

    protected $fillable = [
        'name'
    ];

    public function client_main_information()
    {
        return $this->hasMany(ClientMainInformation::class, 'medium_id');
    }
}
