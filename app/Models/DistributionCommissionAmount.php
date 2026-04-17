<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class DistributionCommissionAmount extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'distribution_commission_sales_amount';

    protected $fillable = [
        'distribution_id',
        'month',
        'year',
        'amount',
        'is_payment',
        'initial'
    ];


    public function distibution()
    {
        return $this->belongsTo(DistributionCommission::class, 'distribution_id');
    }

    /**
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
