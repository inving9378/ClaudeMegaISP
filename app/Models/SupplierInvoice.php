<?php

namespace App\Models;

use App\Http\Controllers\Utils\ComunConstantsController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'supplier_vendor_id',
        'invoice_number',
        'date',
        'total',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date'  => 'date',
        'total' => 'float',
    ];

    protected $appends = ['status_name'];

    protected $with = ['supplier', 'supplierVendor'];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            $obj->created_by = auth()->user()?->id ?? 0;
        });
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierVendor()
    {
        return $this->belongsTo(SupplierVendor::class);
    }

    public function items()
    {
        return $this->hasMany(SupplierInvoiceItem::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function inventoryItemStocks()
    {
        return $this->hasManyThrough(
            InventoryItemStock::class,
            SupplierInvoiceItem::class,
            'supplier_invoice_id',
            'supplier_invoice_item_id'
        );
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'pending'    => 'Pendiente',
            'dispatched' => 'Distribuida',
            'received'   => 'Recibida',
            'cancelled'  => 'Cancelada',
            'denied'     => 'Denegada',
            default      => '',
        };
    }

    public function scopeFilters($query, $columns, $search, $filter)
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', fn($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        if (!empty($filter['supplier_id'])) {
            $query->where('supplier_id', $filter['supplier_id']);
        }

        if (!empty($filter['status'])) {
            $query->where('status', $filter['status']);
        }

        if (!empty($filter['date_from'])) {
            $query->whereDate('date', '>=', $filter['date_from']);
        }

        if (!empty($filter['date_to'])) {
            $query->whereDate('date', '<=', $filter['date_to']);
        }

        return $query;
    }

    public function syncStatusFromMovements(): bool
    {
        if ($this->status !== 'dispatched') {
            return false;
        }

        $movementsQuery = $this->inventoryMovements();
        $totalMovements = (clone $movementsQuery)->count();
        if ($totalMovements === 0) {
            return false;
        }

        $acceptedMovements = (clone $movementsQuery)
            ->where('status', ComunConstantsController::INVENTORY_MOVEMENT_ACCEPTED)
            ->count();

        if ($acceptedMovements !== $totalMovements) {
            return false;
        }

        $this->status = 'received';
        return $this->save();
    }
}
