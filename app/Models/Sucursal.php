<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sucursal extends BaseModel
{
    use HasFactory;
    protected $table = 'sucursals';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address'
    ];

    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)) {
            return $query->where(function ($query) use ($search, $columns) {
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        }
    }
}
