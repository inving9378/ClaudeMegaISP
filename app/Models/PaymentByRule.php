<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class PaymentByRule extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'payment_by_rule';

    protected $fillable = ['seller_id', 'payment_method_id', 'payment_date', 'amount', 'invoice_number', 'comments', 'signature'];

    protected $appends = ['payment_method_str', 'created_str'];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($obj) {
            $obj->created_by = auth()->user()->id;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(MethodOfPayment::class, 'payment_method_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function commissions()
    {
        return $this->hasMany(PaymentByRuleDetails::class, 'payment_id');
    }

    public function getPaymentDateAttribute($val)
    {
        return Carbon::createFromFormat('Y-m-d', $val)->format('d/m/Y');
    }

    public function getPaymentMethodStrAttribute($val)
    {
        return $this->paymentMethod->type;
    }

    public function getCreatedStrAttribute($val)
    {
        return $this->user->name;
    }

    public function scopeSearch($query, $params)
    {
        return $query->where($params[0], 'like', '%' . $params[1] . '%');
    }

    public function scopePaymentDate($query, $dates)
    {
        return $query->whereBetween('payment_date', $dates);
    }

    public function scopePaymentMethod($query, $method)
    {
        return $query->where('payment_method_id', $method);
    }

    public function scopeCreatedBy($query, $user)
    {
        return $query->where('created_by', $user);
    }

    /**
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
