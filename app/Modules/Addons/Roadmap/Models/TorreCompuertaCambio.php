<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora del tablero de compuertas (regla 4): quién, cuándo, de qué valor a cuál.
 * Sin `updated_at`: una entrada de bitácora no se edita.
 */
class TorreCompuertaCambio extends Model
{
    public $timestamps = false;

    protected $table = 'torre_compuerta_cambios';

    protected $fillable = [
        'compuerta', 'accion', 'valor_antes', 'valor_despues',
        'detalle', 'user_id', 'user_login', 'ip', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    /** Registra un cambio. Nunca lanza: que falle la bitácora no debe tumbar la acción. */
    public static function registrar(string $compuerta, string $accion, ?string $antes, ?string $despues, ?string $detalle = null): void
    {
        try {
            $u = auth()->user();
            static::create([
                'compuerta'     => $compuerta,
                'accion'        => $accion,
                'valor_antes'   => $antes,
                'valor_despues' => $despues,
                'detalle'       => $detalle,
                'user_id'       => $u?->id,
                'user_login'    => $u?->login_user ?? $u?->name,
                'ip'            => request()->ip(),
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
