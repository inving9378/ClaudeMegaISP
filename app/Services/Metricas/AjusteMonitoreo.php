<?php

namespace App\Services\Metricas;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Interruptor maestro «Monitoreo de dedicados» de Configuración (item #988). Vive en settings,
 * encendido por default, con quién y cuándo lo cambió; cada cambio queda además en activity_log.
 * METRICAS_ACTIVO en el .env es un override opcional: si está definido, manda sobre el ajuste.
 */
final class AjusteMonitoreo
{
    public const CLAVE = 'metricas_monitoreo';

    public static function activo(): bool
    {
        return self::estado()['activo'];
    }

    /** @return array{activo: bool, ajuste: bool, override: ?bool, cambiado_por: ?string, cambiado_en: ?string} */
    public static function estado(): array
    {
        $guardado = json_decode((string) DB::table('settings')->where('key', self::CLAVE)->value('value'), true);
        $ajuste = (bool) ($guardado['activo'] ?? true);
        $override = self::override(config('metricas.activo'));

        return [
            'activo'       => $override ?? $ajuste,
            'ajuste'       => $ajuste,
            'override'     => $override,
            'cambiado_por' => $guardado['usuario'] ?? null,
            'cambiado_en'  => $guardado['en'] ?? null,
        ];
    }

    public static function cambiar(bool $activo, User $usuario): array
    {
        $ahora = now()->format('Y-m-d H:i:s');
        DB::table('settings')->updateOrInsert(['key' => self::CLAVE], [
            'value' => json_encode([
                'activo' => $activo, 'usuario_id' => $usuario->id, 'usuario' => $usuario->name, 'en' => $ahora,
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => $ahora,
        ]);
        activity('metricas')->causedBy($usuario)->withProperties(['activo' => $activo])
            ->log($activo ? 'Monitoreo de dedicados encendido' : 'Monitoreo de dedicados apagado');

        return self::estado();
    }

    /** true / false si METRICAS_ACTIVO está definido; null si no (manda el ajuste). */
    public static function override(mixed $valor): ?bool
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
