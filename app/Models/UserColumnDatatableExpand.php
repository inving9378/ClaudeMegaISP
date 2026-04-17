<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserColumnDatatableExpand extends BaseModel
{
    use HasFactory;

    protected $table = 'user_column_dt_expand';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
