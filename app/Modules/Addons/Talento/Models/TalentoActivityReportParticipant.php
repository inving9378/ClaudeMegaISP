<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoActivityReportParticipant extends Model
{
    protected $table = 'talento_activity_report_participants';
    public $timestamps = false;

    protected $fillable = ['report_id', 'colaborador_id', 'quantity_share', 'points_earned'];

    protected $casts = [
        'quantity_share' => 'decimal:4',
        'points_earned'  => 'decimal:4',
        'created_at'     => 'datetime',
    ];

    public function report()
    {
        return $this->belongsTo(TalentoProjectActivityReport::class, 'report_id');
    }

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class);
    }
}
