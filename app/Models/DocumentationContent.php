<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentationContent extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'documentation_submenu_id',
        'content',
        'created_by',
        'updated_by'
    ];

    protected $appends = ['documentation_submenu_title'];


    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submenu():BelongsTo
    {
        return $this->belongsTo(DocumentationSubmenu::class, 'documentation_submenu_id');
    }
    
    /**
     * Accesor para obtener el título del submenú
     */
    public function getDocumentationSubmenuTitleAttribute()
    {
        return $this->submenu?->title ?? 'Sin submenú';
    }

    /**
     * Scope para filtros dinámicos
     */
    public function scopeFilters($query, $filters)
    {
        if (!empty($filters)) {
            foreach ($filters as $field => $value) {
                if (!empty($value)) {
                    if ($field === 'documentation_submenu_id') {
                        $query->where($field, $value);
                    } else {
                        $query->where($field, 'like', '%' . $value . '%');
                    }
                }
            }
        }
        return $query;
    }
}
