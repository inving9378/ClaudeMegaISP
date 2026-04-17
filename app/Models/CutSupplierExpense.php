<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CutSupplierExpense extends BaseModel
{
    use HasFactory;
    protected $table = 'cut_suppliers_expenses';

    protected $fillable = [
        'payment_method_id',
        'payment_date',
        'invoice_number',
        'amount',
        'comments',
        'created_by',
        'box_id'
    ];

    protected $appends = ['payment_method_str', 'created_by_str', 'loading'];

    protected $casts = ['payment_date' => 'date'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            $obj->created_by = auth()->user()->id;
        });
    }

    public function paymentMethod()
    {
        return $this->belongsTo(MethodOfPayment::class, 'payment_method_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function box()
    {
        return $this->belongsTo(CutBox::class, 'box_id');
    }

    public function getPaymentMethodStrAttribute()
    {
        return $this->paymentMethod->type;
    }

    public function getCreatedByStrAttribute()
    {
        return $this->createdByUser->getClientNameWithFathersNamesAttribute();
    }

    public function getLoadingAttribute()
    {
        return false;
    }
}
