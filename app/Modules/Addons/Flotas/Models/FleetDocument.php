<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class FleetDocument extends BaseModel
{
    use SoftDeletes;

    protected $table = 'fleet_documents';

    protected $fillable = [
        'vehicle_id', 'document_type', 'folio_number', 'issued_by',
        'issue_date', 'expiration_date', 'cost', 'file_path', 'notes',
        'alert_30_days', 'alert_7_days', 'alert_1_day', 'alert_same_day', 'alert_channels',
    ];

    protected $casts = [
        'issue_date'      => 'date',
        'expiration_date' => 'date',
        'cost'            => 'decimal:2',
        'alert_30_days'   => 'boolean',
        'alert_7_days'    => 'boolean',
        'alert_1_day'     => 'boolean',
        'alert_same_day'  => 'boolean',
        'alert_channels'  => 'array',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    // vigente / por_vencer (≤30 días) / vencido
    public function getStatusAttribute(): string
    {
        if (!$this->expiration_date) {
            return 'vigente';
        }
        $today = Carbon::today();
        if ($this->expiration_date->lt($today)) {
            return 'vencido';
        }
        if ($this->expiration_date->lte($today->addDays(30))) {
            return 'por_vencer';
        }
        return 'vigente';
    }

    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (!$this->expiration_date) {
            return null;
        }
        return (int) Carbon::today()->diffInDays($this->expiration_date, false);
    }
}
