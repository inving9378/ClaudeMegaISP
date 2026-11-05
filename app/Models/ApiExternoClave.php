<?php

namespace App\Models;

use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;

/**
 * Item #1159 — clave de una consulta pública (`/api/externo/*`). Solo se guarda la huella sha256;
 * la clave completa existe una sola vez, en lo que devuelve `generar()`.
 */
class ApiExternoClave extends BaseModel
{
    /** Consultas que una clave puede abrir. Una clave abre una sola. */
    public const ALCANCES = ['cobertura'];

    private const INICIO = 'mgx_';

    protected $table = 'api_externo_claves';

    protected $fillable = ['nombre', 'alcance', 'prefijo', 'huella', 'revocada_at'];

    protected $hidden = ['huella'];

    protected $casts = [
        'ultimo_uso_at' => 'datetime',
        'revocada_at'   => 'datetime',
        'usos'          => 'integer',
    ];

    /** Bitácora de altas y revocaciones (sin la huella). */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('api_externo')
            ->logOnly(['nombre', 'alcance', 'prefijo', 'revocada_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public static function huellaDe(string $clave): string
    {
        return hash('sha256', $clave);
    }

    /**
     * Crea una clave nueva. Devuelve [registro, clave completa]; la clave completa no se puede
     * volver a ver: quien la recibe debe guardarla.
     *
     * @return array{0: self, 1: string}
     */
    public static function generar(string $nombre, string $alcance): array
    {
        if (! in_array($alcance, self::ALCANCES, true)) {
            throw new \InvalidArgumentException("Alcance desconocido: {$alcance}. Válidos: " . implode(', ', self::ALCANCES));
        }

        $clave = self::INICIO . Str::random(40);
        $registro = self::create([
            'nombre'  => $nombre,
            'alcance' => $alcance,
            'prefijo' => substr($clave, 0, 12),
            'huella'  => self::huellaDe($clave),
        ]);

        return [$registro, $clave];
    }

    /** La clave vigente para ese alcance, o null. */
    public static function vigente(?string $clave, string $alcance): ?self
    {
        if ($clave === null || $clave === '' || ! str_starts_with($clave, self::INICIO)) {
            return null;
        }

        return self::where('huella', self::huellaDe($clave))
            ->where('alcance', $alcance)
            ->whereNull('revocada_at')
            ->first();
    }

    /** Cuenta un uso sin pasar por el modelo (no ensucia la bitácora ni toca updated_by). */
    public function registrarUso(): void
    {
        self::whereKey($this->getKey())->update([
            'ultimo_uso_at' => now(),
            'usos'          => \DB::raw('usos + 1'),
        ]);
    }
}
