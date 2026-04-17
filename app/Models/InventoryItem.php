<?php

namespace App\Models;

use App\Http\Traits\Models\Inventory\InventoryItem\ScopeInventoryItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryItem extends BaseModel
{
    use HasFactory;
    use ScopeInventoryItem;

    public static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            $obj->created_by = auth()->user() ? auth()->user()->id : 0;
        });
    }

    protected $guarded = [];

    protected $append = ['current_stock', 'location_item', 'zone_name', 'first_image_id'];


    public function inventory_item_type()
    {
        return $this->belongsTo(InventoryItemType::class, 'inventory_item_type_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users()
    {
        return $this->morphedByMany(User::class, 'modelable', 'inventory_item_stocks', 'inventory_item_id', 'modelable_id')
            ->withPivot('current_stock');
    }

    public function file()
    {
        return $this->morphOne(File::class, 'fileable');
    }

    public function medias()
    {
        return $this->hasMany(InventoryItemMedia::class, 'inventory_item_id');
    }


    public function inventory_stores()
    {
        return $this->morphedByMany(InventoryStore::class, 'modelable', 'inventory_item_stocks', 'inventory_item_id', 'modelable_id')
            ->withPivot('current_stock');
    }

    /**
     * Relación con InventoryItemStock
     */
    public function stocks()
    {
        return $this->hasMany(InventoryItemStock::class, 'inventory_item_id');
    }

    public function getCurrentStockAttribute()
    {
        return $this->stocks()->sum('current_stock');
    }

    public function getLocationItemAttribute()
    {
        $stocks = $this->stocks()->get();
        $str = '';

        foreach ($stocks as $stock) {
            // Obtener el modelo basado en modelable_type y modelable_id
            $model = $stock->modelable_type::find($stock->modelable_id);

            // Verificar si el modelo existe y tiene el campo "name"
            $name = $model ? $model->name : 'Desconocido';

            // Construir el string con el formato "Nombre(cantidad)"
            $str .= "{$name}({$stock->current_stock}), ";
        }

        // Eliminar la última coma del string
        $str = rtrim($str, ',');

        return $str;
    }

    public function getZoneNameAttribute()
    {
        return '';
    }

    public function getFirstImageIdAttribute()
    {
        $firstImage = $this->medias()->where('order', 1)->first();
        if ($firstImage) {
            return $firstImage->url;
        }
        return null;
    }
}
