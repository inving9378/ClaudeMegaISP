<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MR-14 (item roadmap #950) — enlace_de_servicio: la costura cliente/ONT ↔ puerto de NAP ↔
 * hilo del drop. Cliente y ONT se guardan por nombre/serie (D19), nunca por ID de BD.
 */
class MapaRedEnlaceServicio extends Model
{
    public const ESTADOS = ['activo', 'suspendido', 'cancelado', 'baja'];

    protected $table = 'mapared_enlaces_servicio';

    protected $fillable = [
        'cliente_nombre',
        'cliente_numero_contrato',
        'ont_serie',
        'puerto_nap_id',
        'hilo_id',
        'fecha_alta',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha_alta' => 'date',
    ];

    public function puertoNap()
    {
        return $this->belongsTo(MapaRedPuerto::class, 'puerto_nap_id');
    }

    public function hilo()
    {
        return $this->belongsTo(MapaRedHilo::class, 'hilo_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Enlaces visibles al abrir una NAP (DoD #950): todos los enlaces cuyo puerto pertenece
     * al elemento dueño (`puertable_type`/`puertable_id`) con rol `nap_salida`.
     */
    public static function porNap(string $puertableType, int $puertableId)
    {
        $puertoIds = MapaRedPuerto::query()
            ->delDueno($puertableType, $puertableId)
            ->where('rol', MapaRedPuerto::ROL_NAP_SALIDA)
            ->pluck('id');

        return self::query()
            ->whereIn('puerto_nap_id', $puertoIds)
            ->orderBy('cliente_nombre')
            ->get();
    }
}
