<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Models\User;
use App\Modules\Addons\Roadmap\Models\TorreConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Acceso ÚNICO a la configuración de la Torre.
 *
 * ⚠️ La caché **debe** invalidarse en cada guardado. Si no, la Torre muestra una cosa y el servidor
 * aplica otra, y ese desfase es peor que no tener panel: enseña a desconfiar del tablero, y una vez
 * que alguien deja de creerle a un control deja de creerle también a los que sí eran ciertos.
 */
class TorreConfigService
{
    public const CACHE_KEY = 'torre_config_singleton';

    /** Canal de auditoría. Todo cambio de política queda aquí, con quién y desde qué valor. */
    public const LOG_CANAL = 'torre_config';

    /** La fila única, cacheada. Si la tabla está vacía (base recién migrada), la crea con defaults. */
    public function get(): TorreConfig
    {
        $id = Cache::rememberForever(self::CACHE_KEY, function () {
            $fila = TorreConfig::query()->orderBy('id')->first();

            return $fila?->id ?? TorreConfig::create([])->id;
        });

        // Se cachea el ID, no el modelo: un modelo serializado en caché envejece con el esquema.
        return TorreConfig::findOr($id, fn () => TorreConfig::query()->orderBy('id')->firstOrFail());
    }

    /**
     * Guarda cambios validados, los audita y limpia la caché. Devuelve el diff aplicado.
     *
     * @param  array<string,mixed>  $cambios
     * @return array<string,array{antes:mixed,despues:mixed}>
     */
    public function update(array $cambios, ?User $usuario = null): array
    {
        $cfg  = $this->get();
        $diff = [];

        foreach ($cambios as $campo => $valor) {
            if (! in_array($campo, $cfg->getFillable(), true)) {
                continue;   // campo desconocido: se ignora en silencio, nunca se escribe a ciegas
            }
            $antes = $cfg->{$campo};
            if ($antes == $valor) {
                continue;
            }
            $cfg->{$campo} = $valor;
            $diff[$campo]  = ['antes' => $antes, 'despues' => $cfg->{$campo}];
        }

        if (! $diff) {
            return [];
        }

        DB::transaction(function () use ($cfg) {
            $cfg->save();
        });

        $this->olvidar();
        $this->auditar($diff, $usuario);

        return $diff;
    }

    /** Invalida la caché. Público porque una migración o un seeder también deben poder llamarla. */
    public function olvidar(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Deja el rastro. **Subir el techo se registra como `warning`**, no como `info`: es el evento
     * donde un item de más riesgo empieza a ejecutarse sin que nadie lo revise. Bajarlo es `info`.
     */
    private function auditar(array $diff, ?User $usuario): void
    {
        $actor = $usuario
            ? ('irving:' . ($usuario->login_user ?? $usuario->email ?? $usuario->id))
            : (app()->runningInConsole() ? 'consola:' . ($_SERVER['argv'][1] ?? 'artisan') : 'sistema');

        $subeTecho = false;
        if (isset($diff['nivel_automatizacion'])) {
            $orden = array_flip(TorreConfig::NIVELES);
            $subeTecho = ($orden[$diff['nivel_automatizacion']['despues']] ?? 0)
                       > ($orden[$diff['nivel_automatizacion']['antes']] ?? 0);
        }

        $payload = ['por' => $actor, 'cambios' => $diff, 'ts' => now()->toIso8601String()];

        try {
            $canal = Log::channel(self::LOG_CANAL);
            $subeTecho
                ? $canal->warning('techo-de-automatizacion-SUBIDO', $payload)
                : $canal->info('torre-config-actualizada', $payload);
        } catch (\Throwable $e) {
            // Un canal mal configurado no puede impedir que la política se guarde; pero que se sepa.
            Log::warning('torre-config: no se pudo auditar en su canal', $payload + ['error' => $e->getMessage()]);
        }
    }
}
