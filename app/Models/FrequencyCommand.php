<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FrequencyCommand extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'has_time'];
}
