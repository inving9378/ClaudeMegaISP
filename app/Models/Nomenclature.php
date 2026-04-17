<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nomenclature extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'client_id'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeNotUsed($query)
    {
        return $query->whereNull('client_id');
    }

    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {
                    if ($value !== 'action') {
                        $query->orWhere($value, 'like', '%' . $search . '%');
                    }
                }
            });
        }
        return $query;
    }
}
