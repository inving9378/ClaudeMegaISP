<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentationSubmenu extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'documentation_menu_id',
        'title',
        'description',
        'created_by',
        'updated_by'
    ];

    protected $appends = ['documentation_menu_title'];

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

    public function menu():BelongsTo
    {
        return $this->belongsTo(DocumentationMenu::class, 'documentation_menu_id');
    }

    public function content():HasMany
    {
        return $this->hasMany(DocumentationContent::class, 'documentation_submenu_id');
    }

    /**
     * Accessor para obtener el título del menú relacionado
     */
    public function getDocumentationMenuTitleAttribute(): string
    {
        return $this->menu?->title ?? 'Sin menú';
    }

    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        // Búsqueda general (search)
        if (isset($search) && !empty($search)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $column) {
                    if ($column !== 'action' && $column !== 'id') {
                        $query->orWhere($column, 'like', '%' . $search . '%');
                    }
                }
            });
        }

        // Filtros específicos (formato plano del frontend: {campo: valor})
        if (!empty($filter) && is_array($filter)) {
            foreach ($filter as $field => $value) {
                // Ignorar valores vacíos o null
                if ($value === null || $value === '' || $value === 'null') {
                    continue;
                }

                // Filtro específico para documentation_menu_id
                if ($field === 'documentation_menu_id') {
                    $query->where('documentation_menu_id', $value);
                }
                // Agregar más campos de filtro aquí si es necesario
                // elseif ($field === 'otro_campo') { ... }
            }
        }

        return $query;
    }
}
