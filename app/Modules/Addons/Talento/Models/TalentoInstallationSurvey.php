<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoInstallationSurvey extends Model
{
    protected $table = 'talento_installation_surveys';
    public $timestamps = false;

    protected $fillable = [
        'work_order_id', 'client_id',
        'rating_overall', 'rating_technician', 'comments',
        'google_review_offered', 'google_review_opened',
        'submitted_at', 'reminder_sent_at', 'auto_closed',
    ];

    protected $casts = [
        'google_review_offered' => 'boolean',
        'google_review_opened'  => 'boolean',
        'auto_closed'           => 'boolean',
        'submitted_at'          => 'datetime',
        'reminder_sent_at'      => 'datetime',
        'created_at'            => 'datetime',
    ];

    public function workOrder()
    {
        return $this->belongsTo(TalentoWorkOrder::class, 'work_order_id');
    }

    public function isPending(): bool
    {
        return $this->submitted_at === null && !$this->auto_closed;
    }
}
