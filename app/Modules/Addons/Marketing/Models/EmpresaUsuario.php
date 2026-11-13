<?php

namespace App\Modules\Addons\Marketing\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Asignación de una persona a una empresa de Marketing con su rol POR EMPRESA.
 * No es un rol Spatie y nunca da ni quita permisos del sistema.
 * Quitar a alguien = activo = false (la fila no se borra).
 */
class EmpresaUsuario extends BaseModel
{
    public const ROLES = ['admin', 'marketing', 'ventas'];

    protected $table = 'marketing_empresa_usuario';

    protected $fillable = ['company_id', 'user_id', 'rol', 'activo'];

    protected $casts = [
        'company_id' => 'int',
        'user_id'    => 'int',
        'activo'     => 'bool',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $asignacion) {
            $asignacion->created_by = auth()->id();
            $asignacion->updated_by = auth()->id();
        });

        static::updating(function (self $asignacion) {
            $asignacion->updated_by = auth()->id();
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'company_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Empresa {id} que la persona administra (asignación activa con rol admin), o null.
     * Sirve a las rutas «Empresas y equipo» con {id}: una empresa ajena o inexistente es 404.
     */
    public static function empresaAdministradaPor(int $userId, mixed $empresaId): ?Empresa
    {
        if (! is_int($empresaId) && ! (is_string($empresaId) && ctype_digit($empresaId))) {
            return null;
        }

        $asignacion = static::query()
            ->with('empresa')
            ->where('company_id', (int) $empresaId)
            ->where('user_id', $userId)
            ->where('activo', true)
            ->where('rol', 'admin')
            ->first();

        return $asignacion?->empresa;
    }

    /**
     * Igual que empresaAdministradaPor() pero corta la petición con 404 JSON si no la administra.
     */
    public static function empresaAdministradaOFallar(int $userId, mixed $empresaId): Empresa
    {
        $empresa = static::empresaAdministradaPor($userId, $empresaId);

        if (! $empresa) {
            throw new HttpResponseException(response()->json([
                'ok'      => false,
                'error'   => 'no_encontrado',
                'mensaje' => 'No se encontró esa empresa.',
            ], 404));
        }

        return $empresa;
    }
}
