<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CutBox extends BaseModel
{
    use HasFactory;
    protected $table = 'cut_boxs';

    protected $appends = ['start_date', 'start_time', 'end_time', 'user_str', 'closed', 'sucursal'];

    protected $casts = ['end_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function installations()
    {
        return $this->hasMany(CutInstallation::class, 'box_id');
    }

    public function extras_incomes()
    {
        return $this->hasMany(CutExtraIncome::class, 'box_id');
    }

    public function suppliers_expenses()
    {
        return $this->hasMany(CutSupplierExpense::class, 'box_id');
    }

    public function observations()
    {
        return $this->hasMany(CutObservation::class, 'box_id');
    }

    public function getStartDateAttribute()
    {
        return $this->created_at->format('d/m/Y');
    }

    public function getStartTimeAttribute()
    {
        return $this->created_at->format('g:i A');
    }

    public function getEndTimeAttribute()
    {
        return isset($this->end_at) ? $this->end_at->format('g:i A') : null;
    }

    public function getUserStrAttribute()
    {
        return $this->user->getClientNameWithFathersNamesAttribute();
    }

    public function getSucursalAttribute()
    {
        return $this->user->sucursal_str;
    }

    public function getClosedAttribute()
    {
        return $this->end_at !== null;
    }

    public function getReceivedPayments($cash = false)
    {
        $payments = Payment::query()->where('add_by', $this->user_id)->where('is_first_payment', false)->whereDate('created_at', $this->created_at->format('Y-m-d'));
        if ($cash) {
            $payments->where('payment_method_id', 1);
        }
        return $payments->get();
    }

    public function getReceivedPaymentsAmount()
    {
        return $this->getReceivedPayments(true)->sum('amount');
    }

    public function getInstallationsAmount()
    {
        return $this->installations()->where('activated', true)->sum('service_amount') + $this->installations()->where('activated', true)->sum('installation_cost');
    }

    public function getExtrasAmount()
    {
        return $this->extras_incomes()->where('payment_method_id', 1)->sum('amount');
    }

    public function getSuppliersAmount()
    {
        return $this->suppliers_expenses()->where('payment_method_id', 1)->sum('amount');
    }
}
