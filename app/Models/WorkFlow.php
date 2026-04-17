<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkFlow extends BaseModel
{
    use HasFactory;
    protected $table = 'work_flows';

    protected $fillable = ['name'];
}
