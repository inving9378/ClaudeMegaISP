<?php

namespace App\Modules\Addons\Roadmap\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * MODO DE IA DEL SUPERVISOR POR DÍA/HORA + PRESUPUESTO DIARIO (#1116, PASO 3 de #1076).
 *
 * Dos modos: `economico` (ahorro por defecto de config/circuito.php → ia.modelos) y `normal`
 * (lo que se asignó en Integraciones → Módulos IA). Las reglas día/hora eligen el modo; al llegar
 * al tope diario en MXN el modo se fuerza a `economico` y se avisa a Irving (no se corta la IA).
 *
 * Interruptor `activo` APAGADO por default: sin él, `modoEfectivo()` devuelve null y el supervisor
 * no cambia en nada. El tope 0 = solo mide, no actúa. Todo cambio queda en `circuito_ia_historial`.
 */
final class ModoIa
{
    public const ECONOMICO = 'economico';
    public const NORMAL    = 'normal';
    public const ZONA      = 'America/Mexico_City';

    /** Modo que debe usarse ahora, o null si el control está apagado (no interviene). */
    public static function modoEfectivo(?Carbon $ahora = null): ?string
    {
        if (! self::disponible() || self::ajuste('activo', '0') !== '1') {
            return null;
        }

        if (self::topeAlcanzado()) {
            return self::ECONOMICO;
        }

        $ahora = ($ahora ?? Carbon::now())->copy()->setTimezone(self::ZONA);
        $hora  = $ahora->format('H:i:s');
        foreach (self::reglas() as $r) {
            if ((int) $r->dia_semana === (int) $ahora->isoWeekday() && $hora >= $r->hora_inicio && $hora < $r->hora_fin) {
                return $r->modo;
            }
        }

        return self::ajuste('modo_base', self::NORMAL) === self::ECONOMICO ? self::ECONOMICO : self::NORMAL;
    }

    public static function presupuestoDiario(): float
    {
        return max(0.0, (float) self::ajuste('presupuesto_diario_mxn', '0'));
    }

    /** ¿El gasto de hoy llegó al tope? Con tope 0 nunca. Al cruzarlo avisa una vez por día. */
    public static function topeAlcanzado(): bool
    {
        $tope = self::presupuestoDiario();
        if ($tope <= 0) {
            return false;
        }
        $gasto = self::gastoMxn(Carbon::now(self::ZONA)->toDateString(), null);
        if ($gasto < $tope) {
            return false;
        }

        $dia = Carbon::now(self::ZONA)->toDateString();
        if (Cache::add("circuito.ia.tope_avisado.{$dia}", 1, 86400)) {
            $texto = sprintf('Presupuesto diario de IA del supervisor alcanzado: %.2f de %.2f MXN. El supervisor pasó a modo ECONÓMICO hasta mañana.', $gasto, $tope);
            Log::channel('claude')->warning($texto);
            AlertaExterna::avisar([['clave' => 'ia_tope_diario', 'nivel' => 'alarma', 'texto' => $texto]]);
        }

        return true;
    }

    /** Gasto en MXN entre dos fechas (Y-m-d); `$hasta` null = igual a `$desde`. Cache de 60 s. */
    public static function gastoMxn(string $desde, ?string $hasta): float
    {
        $hasta ??= $desde;

        return (float) Cache::remember("circuito.ia.gasto.{$desde}.{$hasta}", 60, function () use ($desde, $hasta) {
            $mxn = 0.0;
            foreach (GastoIa::lineas(storage_path('logs'), $desde, $hasta) as $l) {
                $mxn += (float) ($l['datos']['costo_mxn'] ?? 0);
            }

            return round($mxn, 4);
        });
    }

    /** Último modelo usado por pieza en los logs de hoy. @return array<string,string> */
    public static function ultimoModeloPorPieza(): array
    {
        $hoy = Carbon::now()->toDateString();
        $out = [];
        foreach (GastoIa::lineas(storage_path('logs'), $hoy, $hoy) as $l) {
            if (! empty($l['datos']['pieza'])) {
                $out[$l['datos']['pieza']] = (string) ($l['datos']['model'] ?? '?');
            }
        }

        return $out;
    }

    public static function ajuste(string $clave, string $defecto): string
    {
        $v = self::todosLosAjustes()[$clave] ?? null;

        return $v === null ? $defecto : (string) $v;
    }

    /** Cambia un ajuste y deja historial. Quien llama ya validó que sea super-administrator. */
    public static function fijar(string $clave, string $valor, string $usuario): void
    {
        $anterior = self::todosLosAjustes()[$clave] ?? null;
        DB::table('circuito_ia_ajustes')->updateOrInsert(['clave' => $clave], ['valor' => $valor, 'updated_at' => now(), 'created_at' => now()]);
        DB::table('circuito_ia_historial')->insert(['clave' => $clave, 'anterior' => $anterior, 'nuevo' => $valor, 'usuario' => $usuario]);
        Cache::forget('circuito.ia.ajustes');
    }

    /** Reemplaza las reglas día/hora (con historial). @param array<int,array{0:int,1:string,2:string,3:string}> $reglas */
    public static function fijarReglas(array $reglas, string $usuario): void
    {
        $anterior = json_encode(self::reglas()->map(fn ($r) => [$r->dia_semana, $r->hora_inicio, $r->hora_fin, $r->modo])->all());
        DB::transaction(function () use ($reglas) {
            DB::table('circuito_ia_reglas')->delete();
            foreach ($reglas as [$dia, $ini, $fin, $modo]) {
                DB::table('circuito_ia_reglas')->insert(['dia_semana' => $dia, 'hora_inicio' => $ini, 'hora_fin' => $fin, 'modo' => $modo, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        DB::table('circuito_ia_historial')->insert(['clave' => 'reglas', 'anterior' => mb_substr($anterior, 0, 255), 'nuevo' => mb_substr(json_encode($reglas), 0, 255), 'usuario' => $usuario]);
        Cache::forget('circuito.ia.reglas');
    }

    private static function reglas()
    {
        if (! self::disponible()) {
            return collect();
        }

        return Cache::remember('circuito.ia.reglas', 60, fn () => DB::table('circuito_ia_reglas')->where('activo', true)->get());
    }

    private static function todosLosAjustes(): array
    {
        if (! self::disponible()) {
            return [];
        }

        return Cache::remember('circuito.ia.ajustes', 60, fn () => DB::table('circuito_ia_ajustes')->pluck('valor', 'clave')->all());
    }

    private static function disponible(): bool
    {
        try {
            return Schema::hasTable('circuito_ia_ajustes');
        } catch (\Throwable) {
            return false;
        }
    }
}
