<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rfc',
        'address',
        'phone',
        'email',
        'status',
        'created_by',
    ];

    protected $appends = ['status_name', 'vendors_count'];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            $obj->created_by = auth()->user()?->id ?? 0;
        });
    }

    public function vendors()
    {
        return $this->hasMany(SupplierVendor::class);
    }

    public function invoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function productPrices()
    {
        return $this->hasMany(SupplierProductPrice::class);
    }

    public function inventoryItemStocks()
    {
        return $this->hasMany(InventoryItemStock::class);
    }

    public function getStatusNameAttribute(): string
    {
        return $this->status === 'active' ? 'Activo' : 'Inactivo';
    }

    public function getVendorsCountAttribute(): int
    {
        return $this->vendors()->count();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFilters($query, $columns, $search, $filter)
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('rfc', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($filter['status'])) {
            $query->where('status', $filter['status']);
        }

        return $query;
    }
}
