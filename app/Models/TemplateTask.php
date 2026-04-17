<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateTask extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'title_template',
        'title_task',
        'project_id',
        'template_verification_id',
        'priority',
        'description',
        'assigned_to',
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
