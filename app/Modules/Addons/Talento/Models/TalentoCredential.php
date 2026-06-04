<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Crypt;

class TalentoCredential extends BaseModel
{
    protected $table = 'talento_credentials';

    protected $fillable = [
        'colaborador_id', 'type', 'document_number', 'file_path',
        'issued_at', 'expires_at', 'status', 'alert_weeks_before', 'notes', 'created_by',
    ];

    protected $casts = [
        'issued_at'  => 'date',
        'expires_at' => 'date',
    ];

    // Ocultar file_path en serializaciones — nunca exponer la ruta cifrada
    protected $hidden = ['file_path'];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function fund()
    {
        return $this->hasOne(TalentoFund::class, 'credential_id');
    }

    /** Store a file path encrypted at rest. */
    public function setFilePathAttribute(?string $value): void
    {
        $this->attributes['file_path'] = $value ? Crypt::encryptString($value) : null;
    }

    /** Decrypt on read — returns plain path for internal use only. */
    public function getDecryptedPath(): ?string
    {
        if (!$this->attributes['file_path']) return null;
        try {
            return Crypt::decryptString($this->attributes['file_path']);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Días restantes hasta vencimiento (negativo = ya venció). */
    public function daysUntilExpiry(): ?int
    {
        return $this->expires_at ? now()->diffInDays($this->expires_at, false) : null;
    }

    public function weeksUntilExpiry(): ?float
    {
        return $this->expires_at ? now()->diffInWeeks($this->expires_at, false) : null;
    }
}
