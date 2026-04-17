<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends BaseModel
{
    use HasFactory;

    const MULTIPLE_RELATIONS = [
        'partners' => 'partners',
    ];

    protected $fillable = [
        'title',
        'description',
        'type',
        'partner',
        'project_lead',
        'category',
        'workflow',
    ];

    public function partners()
    {
        return $this->morphToMany(
            Partner::class,
            'partner_module',
            'partner_module'
        )->withTimestamps();
    }

    public function scopeFilters($query, $columns, $search = null)
    {
        if (isset($search)) {
            return $query->where(function ($query) use ($search, $columns) {
                foreach (collect($columns)->filter(function ($value) {
                    return $value != 'action';
                })->toArray() as $value) {
                    $query->orWhere($value, 'like', '%' . $search . '%');
                }
            });
        }
    }
}
