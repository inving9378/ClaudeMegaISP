<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItemType extends BaseModel
{
    use HasFactory;

    public static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            $obj->created_by = auth()->user() ? auth()->user()->id : 0;
        });
    }

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'type'
    ];

    protected $appends = ['type_name'];

    public function inventory_items()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }


    public function getTypeNameAttribute()
    {
        $types = [
            'tool' => 'Herramienta',
            'material' => 'Material'
        ];

        return $types[$this->type];
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
            });
        }
        return $query;
    }
}
