<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserColumnDatatableModule extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function column_datatable_module(){
        return $this->belongsTo('App\Models\ColumnDatatableModule');
    }

    public function user(){
        return $this->belongsTo('App\Models\User');
    }

}
