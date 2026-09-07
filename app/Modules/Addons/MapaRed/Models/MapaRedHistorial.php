<?php

namespace App\Modules\Addons\MapaRed\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * MR-23 fase 4d (item roadmap #9990456) — un renglón de historial por campo de negocio
 * cambiado (accion=editar) o por evento crear/eliminar (campo/valores en null).
 * Registro inmutable: sin updated_at, sin soft delete.
 */
class MapaRedHistorial extends Model
{
    const UPDATED_AT = null;

    protected $table = 'mapared_historial';

    protected $fillable = [
        'entidad_tipo',
        'entidad_id',
        'accion',
        'campo',
        'valor_anterior',
        'valor_nuevo',
        'usuario_id',
    ];

    /** Campos de negocio de MapaRedLayer que sí generan renglón al editarse (q3, Opción 1). */
    public const CAMPOS_RELEVANTES_LAYER = ['text', 'coords', 'data', 'label', 'classification', 'color', 'project_id'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public static function registrarCreacion(string $entidadTipo, int $entidadId): void
    {
        static::insertar($entidadTipo, $entidadId, 'crear');
    }

    public static function registrarEliminacion(string $entidadTipo, int $entidadId): void
    {
        static::insertar($entidadTipo, $entidadId, 'eliminar');
    }

    public static function registrarEdicion(string $entidadTipo, int $entidadId, string $campo, mixed $anterior, mixed $nuevo): void
    {
        static::insertar($entidadTipo, $entidadId, 'editar', $campo, $anterior, $nuevo);
    }

    private static function insertar(string $entidadTipo, int $entidadId, string $accion, ?string $campo = null, mixed $anterior = null, mixed $nuevo = null): void
    {
        static::create([
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $entidadId,
            'accion' => $accion,
            'campo' => $campo,
            'valor_anterior' => static::serializar($anterior),
            'valor_nuevo' => static::serializar($nuevo),
            'usuario_id' => auth()->id(),
        ]);
    }

    private static function serializar(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        return is_scalar($valor) ? (string) $valor : json_encode($valor, JSON_UNESCAPED_UNICODE);
    }
}
