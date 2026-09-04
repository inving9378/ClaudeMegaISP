<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Título de concesión, permiso, autorización, derecho de vía, convenio de
 * infraestructura o arrendamiento de sitio — apartado XIII.
 *
 * El nivel de alerta (90/60/30/7 días) y el semáforo se DERIVAN de
 * `vigencia_fin`, igual que `DcDocumento::getEstadoAttribute()`: una copia
 * persistida envejecería sola a medianoche. `estado_tramite`, en cambio, SÍ se
 * persiste — es un estado administrativo (¿está en renovación?, ¿en trámite?)
 * que nadie puede derivar de una fecha.
 */
class DcConcesion extends Model
{
    use SoftDeletes;

    protected $table = 'dc_concesiones';

    protected $fillable = [
        'empresa_id', 'tipo', 'autoridad', 'folio', 'objeto', 'fecha_otorgamiento',
        'vigencia_fin', 'obligaciones', 'responsable_user_id', 'estado_tramite',
        'documento_id',
    ];

    protected $casts = [
        'fecha_otorgamiento' => 'date',
        'vigencia_fin'       => 'date',
    ];

    protected $appends = ['dias_para_vencer', 'nivel_alerta', 'semaforo'];

    public const TIPOS = [
        'titulo_concesion', 'permiso', 'autorizacion', 'derecho_via',
        'convenio_infraestructura', 'arrendamiento_sitio',
    ];

    public const ESTADOS_TRAMITE = ['vigente', 'en_renovacion', 'en_tramite', 'vencido'];

    /** Umbrales del calendario regulatorio, de mayor a menor antelación. */
    public const UMBRALES_ALERTA = [90, 60, 30, 7];

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function documento()
    {
        return $this->belongsTo(DcDocumento::class, 'documento_id');
    }

    public function pagos()
    {
        return $this->hasMany(DcConcesionPago::class, 'concesion_id')->orderByDesc('fecha_vencimiento');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    /** Concesiones dentro de la ventana de alerta más amplia (90 días) o ya vencidas. */
    public function scopePorVencerOVencidas($query)
    {
        return $query->where('vigencia_fin', '<=', now()->addDays(max(self::UMBRALES_ALERTA))->endOfDay());
    }

    /** Días naturales hasta `vigencia_fin`. Negativo si ya venció. */
    public function getDiasParaVencerAttribute(): ?int
    {
        if ($this->vigencia_fin === null) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->vigencia_fin->copy()->startOfDay(), false);
    }

    /**
     * El escalón de alerta más cercano que ya se cruzó (90, 60, 30 o 7), o
     * `null` si a la vigencia aún le faltan más de 90 días. Vencida cuenta como
     * el escalón más urgente.
     */
    public function getNivelAlertaAttribute(): ?int
    {
        $dias = $this->dias_para_vencer;
        if ($dias === null) {
            return null;
        }

        if ($dias < 0) {
            return 7;
        }

        foreach (array_reverse(self::UMBRALES_ALERTA) as $umbral) {
            if ($dias <= $umbral) {
                return $umbral;
            }
        }

        return null;
    }

    /**
     * Semáforo de ESTE registro: rojo si venció o está fuera de trámite vigente,
     * amarillo dentro de la ventana de 90 días o en renovación/trámite, verde en
     * cualquier otro caso.
     */
    public function getSemaforoAttribute(): string
    {
        $dias = $this->dias_para_vencer;

        if ($this->estado_tramite === 'vencido' || ($dias !== null && $dias < 0)) {
            return 'rojo';
        }

        if (in_array($this->estado_tramite, ['en_renovacion', 'en_tramite'], true)) {
            return 'amarillo';
        }

        if ($dias !== null && $dias <= 90) {
            return 'amarillo';
        }

        return 'verde';
    }
}
