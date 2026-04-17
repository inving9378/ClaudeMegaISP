<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Colony extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function municipio()
    {
        return $this->belongsTo(Municipality::class, 'municipality_id');
    }
    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {
                    if ($value !== 'action') {
                        $query->orWhere($value, 'like', '%' . $search . '%');
                    }
                }
            });
        } elseif (!empty($filter)) {
            $query->where(function ($query) use ($filter, $search, $columns) {
                foreach ($filter as $key => $values) {
                    foreach ($values as $keyV => $val) {
                        if ($val == 'null') {
                            $query = $query;
                            break;
                        }
                        $query->where($keyV, $val)
                            ->where(function ($query) use ($search, $columns) {
                                foreach (collect($columns)->filter(function ($value) {
                                    return $value != 'action';
                                })->toArray() as $value) {
                                    $query->orWhere($value, 'like', '%' . $search . '%');
                                }
                            });
                    }
                }
            });
        }
        return $query;
    }
}
