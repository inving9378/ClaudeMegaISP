<?php

namespace App\Models;

use App\Http\Traits\PaymentsTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class PaymentByRuleDetails extends Model
{
    use HasFactory, LogsActivity, PaymentsTrait;

    protected $table = 'payment_by_rule_commissions';
    protected $appends = ['period', 'type_str'];

    protected $casts = [
        'sales' => 'json',
        'data' => 'json',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function payment()
    {
        return $this->belongsTo(PaymentByRule::class, 'payment_id');
    }

    public function getPeriodAttribute()
    {
        return sprintf('%s - %s', $this->start_date->format('d/m/Y'), $this->end_date->format('d/m/Y'));
    }

    public function getTypeStrAttribute()
    {
        return $this->paymentLabelByCode($this->type);
    }
    /**
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
