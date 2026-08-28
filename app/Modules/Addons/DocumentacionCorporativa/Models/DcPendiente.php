<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lo que falta de un concepto: quién lo consigue y para cuándo.
 */
class DcPendiente extends Model
{
    use SoftDeletes;

    protected $table = 'dc_pendientes';

    protected $fillable = [
        'empresa_id', 'concepto_id', 'responsable_user_id', 'fecha_compromiso',
        'estado', 'comentarios', 'recordatorio_enviado_at',
    ];

    protected $casts = [
        'fecha_compromiso'        => 'date',
        'recordatorio_enviado_at' => 'datetime',
    ];

    public const ESTADOS = ['pendiente', 'en_proceso', 'entregado', 'no_aplica'];

    public function concepto()
    {
        return $this->belongsTo(DcConcepto::class, 'concepto_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function scopeAbiertos($query)
    {
        return $query->whereIn('estado', ['pendiente', 'en_proceso']);
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
