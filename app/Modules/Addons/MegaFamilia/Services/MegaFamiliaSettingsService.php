<?php

namespace App\Modules\Addons\MegaFamilia\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Settings globales de MegaFamilia con persistencia en BD.
 *
 * Arquitectura: la tabla `megafamilia_settings` es la FUENTE de verdad; el cache
 * (`megafamilia:settings`) es solo un ACELERADOR (TTL 60 min) que se invalida en
 * cada set/forget. Así `php artisan cache:clear` ya NO pierde configuración:
 * el siguiente get() repuebla el cache desde la BD transparentemente.
 *
 * Valores: se serializan con JSON (preserva bool/int/string/null). Si encrypted=true,
 * el JSON se cifra con Crypt (APP_KEY) antes de guardarse.
 */
class MegaFamiliaSettingsService
{
    private const CACHE_KEY = 'megafamilia:settings';
    private const TTL = 3600; // 60 minutos

    /** Un setting puntual (con default si no existe). */
    public function get(string $key, $default = null)
    {
        $all = $this->all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /** Todos los settings descifrados como array plano [key => value]. */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL, function () {
            $out = [];
            foreach (DB::table('megafamilia_settings')->get() as $row) {
                $out[$row->key] = $this->decode($row->value, (bool) $row->encrypted, $row->key);
            }
            return $out;
        });
    }

    /** Crea o actualiza un setting. Invalida el cache. */
    public function set(string $key, $value, bool $encrypted = false): bool
    {
        $stored = json_encode($value);
        if ($encrypted) {
            $stored = Crypt::encryptString($stored);
        }

        DB::table('megafamilia_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value'      => $stored,
                'encrypted'  => $encrypted,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        Cache::forget(self::CACHE_KEY);
        return true;
    }

    /** Elimina un setting. Invalida el cache. */
    public function forget(string $key): bool
    {
        $deleted = DB::table('megafamilia_settings')->where('key', $key)->delete();
        Cache::forget(self::CACHE_KEY);
        return $deleted > 0;
    }

    /** Fuerza repoblar el cache desde BD (útil tras una migración de datos). */
    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function decode(?string $stored, bool $encrypted, string $key)
    {
        if ($stored === null) {
            return null;
        }
        try {
            $raw = $encrypted ? Crypt::decryptString($stored) : $stored;
        } catch (\Throwable $e) {
            // APP_KEY cambiada o valor corrupto: no rompas la app, loguea y devuelve null.
            Log::warning("MegaFamiliaSettings: no se pudo descifrar '{$key}': " . $e->getMessage());
            return null;
        }
        return json_decode($raw, true);
    }
}
