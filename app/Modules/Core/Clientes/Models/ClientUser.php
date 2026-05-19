<?php

namespace App\Modules\Core\Clientes\Models;

use App\Models\BaseModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientUser extends BaseModel
{
    use HasFactory;
    protected $guarded = [];

    public function client(){
        return $this->belongsTo('App\Models\Client');
    }
}
