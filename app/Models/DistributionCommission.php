<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class DistributionCommission extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'distribution_commission_sales';

    protected $fillable = [
        'date',
        'sale_id',
        'duration',
        'initial',
        'percent',
        'iva',
        'service',
        'total_amount',
        'total_amount_per_week',
        'total_discount',
        'discount_per_week',
        'amount_per_week'
    ];

    protected $casts = ['date' => 'date'];

    public static function boot()
    {
        parent::boot();
        static::created(function ($obj) {
            $date = $obj->date;
            for ($i = 0; $i < $obj->duration; $i++) {
                DistributionCommissionAmount::create([
                    'distribution_id' => $obj->id,
                    'month' => $date->month,
                    'year' => $date->year,
                    'amount' => $obj->amount_per_week,
                    'is_payment' => false,
                    'initial' => $i == 0
                ]);
                $date->addMonthNoOverflow();
            }
        });
    }

    public function sale()
    {
        return $this->belongsTo(ClientMainInformation::class, 'sale_id');
    }

    public function amunts()
    {
        return $this->hasMany(DistributionCommissionAmount::class, 'distribution_id');
    }


    /**
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
