<?php

namespace App\Models;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Traits\Models\Inventory\InventoryMovement\ScopeInventoryMovement;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryMovement extends BaseModel
{
    use HasFactory;
    use ScopeInventoryMovement;
    public static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            $obj->created_by = auth()->user() ? auth()->user()->id : 0;
        });
        //TODO COMENTAR ESTO cuando haga migraciones
        static::updating(function ($obj) {
            $obj->updated_by = auth()->user() ? auth()->user()->id : 0;
        });
    }

    protected $guarded = [];

    protected $appends = [
        'status_name',
        'name_item',
        'type_item',
        'desde',
        'hacia',
    ];

    public function inventory_item()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movementableTo()
    {
        return $this->morphTo('movementable_to');
    }

    /**
     * Relación polimórfica: desde dónde fue el movimiento
     */
    public function movementableFrom()
    {
        return $this->morphTo('movementable_from');
    }

    public function getStatusNameAttribute()
    {
        $statuses = ComunConstantsController::INVENTORY_MOVEMENT_STATUS;
        return isset($statuses[$this->status]) ? $statuses[$this->status] : '';
    }

    public function getNameItemAttribute()
    {
        return $this->inventory_item->name ?? '';
    }
    public function getTypeItemAttribute()
    {
        return $this->inventory_item->inventory_item_type->type ?? '';
    }

    public function getDesdeAttribute()
    {
        return $this->movementableFrom->name ?? '';
    }

    public function getHaciaAttribute()
    {
        $movementableToType = get_class($this->movementableTo);
        if ($movementableToType == 'App\Models\Client') {
            return $this->movementableTo->client_main_information->name ?? '';
        }
        return $this->movementableTo->name ?? '';
    }
}
