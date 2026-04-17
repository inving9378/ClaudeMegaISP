<?php

namespace App\Models;

use App\Http\Requests\module\client\ClientTransactionRequest;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends BaseModel
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded = [];

    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)){
            return $query->where(function ($query) use ($search, $columns){
                foreach (collect($columns)->filter(function ($value){
                    return $value != 'action';
                })->toArray() as $value){
                    $query->orWhere($value,'like','%'.$search.'%');
                }
            });
        }
    }

    public function getRequestAndStoreMethod()
    {
        $request = new ClientTransactionRequest();
        $storeMethod = 'App\Http\Controllers\Module\Client\ClientTransactionController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod,
            'parameter_id' => 'client_id',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}


