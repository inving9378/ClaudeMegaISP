<?php

namespace App\Modules\Addons\FirmaElectronica\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Evidencia de firma: append-only. */
class FirmaEvidencia extends Model
{
    protected $table = 'firma_evidencias';
    protected $guarded = ['id'];
    protected $casts = ['firmado_at' => 'datetime', 'consentimiento' => 'boolean'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('firma_evidencias es inmutable.'));
        static::deleting(fn () => throw new LogicException('firma_evidencias es inmutable.'));
    }
}
