<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Paquete de entrega generado: el ZIP (+ su hash) y el acta de
 * entrega-recepción en PDF. Apartado XIV — "paquetes entregados con acuse y
 * hash". Es la unidad que `dc_entrega_items` detalla línea por línea.
 */
class DcEntrega extends Model
{
    use SoftDeletes;

    protected $table = 'dc_entregas';

    protected $fillable = [
        'empresa_id', 'solicitud_id', 'generado_por_user_id', 'fecha_entrega',
        'ruta_zip', 'hash_sha256', 'ruta_acta_pdf', 'indice', 'estado', 'error',
        'descargas_zip_count', 'descargas_acta_count',
    ];

    protected $casts = [
        'fecha_entrega' => 'datetime',
        'indice'        => 'array',
    ];

    public const ESTADO_GENERANDO = 'generando';
    public const ESTADO_GENERADA  = 'generada';
    public const ESTADO_FALLIDA   = 'fallida';

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function solicitud()
    {
        return $this->belongsTo(DcSolicitud::class, 'solicitud_id');
    }

    public function generadoPor()
    {
        return $this->belongsTo(User::class, 'generado_por_user_id');
    }

    public function items()
    {
        return $this->hasMany(DcEntregaItem::class, 'entrega_id');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
