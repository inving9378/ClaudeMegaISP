<?php

namespace App\Modules\Core\Clientes\Models;

use App\Models\BaseModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientInvoiceService extends BaseModel
{
    use HasFactory;
    protected $table = 'client_serviceables';
    protected $guarded = [];
}
