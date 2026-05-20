<?php

namespace App\Modules\Core\Configuracion\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value store para la configuración de la API móvil. Helper estático
 * `getAll()` y `setBulk()` para evitar repetir el patrón en el controller.
 */
class ApiMobileConfig extends Model
{
    protected $table = 'api_mobile_config';

    protected $fillable = ['key', 'value'];

    /** @return array<string, mixed> decodificado desde el text store */
    public static function getAll(): array
    {
        $out = [];
        foreach (self::query()->get(['key', 'value']) as $row) {
            $decoded = json_decode((string) $row->value, true);
            $out[$row->key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value;
        }
        return $out;
    }

    /** @param array<string, mixed> $data */
    public static function setBulk(array $data): void
    {
        foreach ($data as $key => $value) {
            $stored = is_array($value) || is_object($value) || is_bool($value) || is_null($value)
                ? json_encode($value)
                : (string) $value;
            self::updateOrCreate(['key' => $key], ['value' => $stored]);
        }
    }
}
