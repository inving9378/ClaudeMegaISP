<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class Discount extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'discounts';

    protected $fillable = ['seller_id', 'date', 'discount', 'comments', 'invoice_number'];

    protected $appends = ['created_str'];

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

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function sales()
    {
        return $this->hasMany(DiscountSale::class, 'discount_id');
    }

    public function getDateAttribute($val)
    {
        return Carbon::createFromFormat('Y-m-d', $val)->format('d/m/Y');
    }
    public function getCreatedStrAttribute($val)
    {
        return $this->user->name;
    }

    public function scopeSearch($query, $params)
    {
        return $query->where($params[0], 'like', '%' . $params[1] . '%');
    }

    public function scopeDiscountDate($query, $dates)
    {
        return $query->whereBetween('date', $dates);
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
