<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppLayoutConfiguration extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'color_mode',
        'client_datatable_color'
    ];

    protected $casts = [
        'tabs_json' => 'json',
    ];
}
