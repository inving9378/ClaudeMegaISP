<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItemType extends BaseModel
{
    use HasFactory;

    /**
     * Clasificación de negocio del tipo de artículo (columna `categoria`). PUNTO ÚNICO DE VERDAD:
     * el formulario del admin, la validación y el agrupado de "Mi material" del Portal de
     * Colaborador leen de aquí. Agregar una categoría = agregar una entrada a este arreglo.
     *
     * herramienta    = durable, se USA y se DEVUELVE (casco, taladro, OTDR, uniforme…).
     * material       = consumible, se GASTA o queda instalado (cinta, fibra, tornillo, splitter…).
     * equipo_cliente = equipo que se PRESTA/INSTALA en el cliente (ONT, módem, teléfono de casa…).
     * null           = SIN CLASIFICAR (default; NO se adivina).
     *
     * ⚠️ La clave persistida es el slug (`equipo_cliente`), NO la etiqueta: `categoria` es
     * varchar(20) y las otras dos claves ya son tokens en minúscula sin espacios.
     */
    public const CATEGORIAS = [
        'herramienta'    => 'Herramienta',
        'material'       => 'Material',
        'equipo_cliente' => 'Equipo de cliente',
    ];

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
        'type',
        'categoria'
    ];

    protected $appends = ['type_name', 'categoria_name'];

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

    /** Etiqueta legible de la categoría. Sin clasificar (null) → cadena vacía, nunca "null". */
    public function getCategoriaNameAttribute()
    {
        return self::CATEGORIAS[$this->categoria] ?? '';
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
