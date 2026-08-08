<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class FleetDocument extends BaseModel
{
    use SoftDeletes;

    protected $table = 'fleet_documents';

    protected $fillable = [
        'vehicle_id', 'driver_id', 'document_type', 'folio_number', 'issued_by',
        'issue_date', 'expiration_date', 'cost', 'file_path', 'notes',
        'alert_30_days', 'alert_7_days', 'alert_1_day', 'alert_same_day', 'alert_channels',
    ];

    /**
     * #580 — Las columnas `ocr_*` quedan FUERA de $fillable a propósito: las escribe el controller
     * con forceFill desde la bitácora `fleet_document_ocr_runs`. `update()` hace mass assignment
     * con `$request->except([...])`, así que dejarlas fillable permitiría que el cliente se
     * autodeclarara "leído por IA" o se quitara la marca de revisión manual.
     */
    protected $casts = [
        'issue_date'       => 'date',
        'expiration_date'  => 'date',
        'cost'             => 'decimal:2',
        'alert_30_days'    => 'boolean',
        'alert_7_days'     => 'boolean',
        'alert_1_day'      => 'boolean',
        'alert_same_day'   => 'boolean',
        'alert_channels'   => 'array',
        'ocr_needs_review' => 'boolean',
        'ocr_fields'       => 'array',
        'ocr_ran_at'       => 'datetime',
        'ocr_reviewed_at'  => 'datetime',
    ];

    public function ocrRuns()
    {
        return $this->hasMany(FleetDocumentOcrRun::class, 'document_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    // Conductor (operador / user) al que pertenece el documento, p.ej. licencia.
    // Opcional: nullable, los documentos pueden ser solo-de-vehículo (#93).
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function scopeForClient(Builder $q, ?int $clientId): Builder
    {
        return $q->whereHas('vehicle', fn($v) => $clientId
            ? $v->where('client_id', $clientId)
            : $v->whereNull('client_id'));
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
