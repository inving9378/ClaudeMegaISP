<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierVendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'name',
        'last_name',
        'phone',
        'email',
        'position',
        'status',
    ];

    protected $appends = ['full_name', 'status_name'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function invoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . ($this->last_name ?? ''));
    }

    public function getStatusNameAttribute(): string
    {
        return $this->status === 'active' ? 'Activo' : 'Inactivo';
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
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (!empty($filter['supplier_id'])) {
            $query->where('supplier_id', $filter['supplier_id']);
        }

        if (!empty($filter['status'])) {
            $query->where('status', $filter['status']);
        }

        return $query;
    }
}
