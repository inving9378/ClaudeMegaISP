<?php

namespace App\Modules\Core\Clientes\Models;

use App\Models\BaseModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientAdditionalInformation extends BaseModel
{
    use HasFactory,SoftDeletes;
    protected $guarded = [];
}
