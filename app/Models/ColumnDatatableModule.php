<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColumnDatatableModule extends BaseModel
{
    use HasFactory;
    protected $guarded = [];

    public function user_column_datatable_module(){
        return $this->hasMany(UserColumnDatatableModule::class);
    }
}

