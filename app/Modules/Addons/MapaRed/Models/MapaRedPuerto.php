<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * MR-10 (item roadmap #946) — puerto como entidad de primera clase, polimórfica.
 *
 * Cualquier elemento de la red (OLT, splitter, NAP, ODF, ONT...) es dueño de sus puertos vía
 * `puertable_type`/`puertable_id`. Roles y estados están fijados por el enum de la migración
 * `2026_09_06_220000_create_mapared_puertos_table`.
 */
class MapaRedPuerto extends Model
{
    public const ROL_PON = 'pon';
    public const ROL_SPLITTER_IN = 'splitter_in';
    public const ROL_SPLITTER_OUT = 'splitter_out';
    public const ROL_NAP_SALIDA = 'nap_salida';
    public const ROL_ODF = 'odf';
    public const ROL_ONT = 'ont';

    public const ESTADO_LIBRE = 'libre';
    public const ESTADO_OCUPADO = 'ocupado';
    public const ESTADO_RESERVADO = 'reservado';
    public const ESTADO_DANADO = 'dañado';

    protected $table = 'mapared_puertos';

    protected $fillable = [
        'puertable_type',
        'puertable_id',
        'numero',
        'frame',
        'slot',
        'rol',
        'estado',
        'etiqueta',
    ];

    public function puertable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeLibre($query)
    {
        return $query->where('estado', self::ESTADO_LIBRE);
    }

    public function scopeOcupado($query)
    {
        return $query->where('estado', self::ESTADO_OCUPADO);
    }

    public function scopeDelDueno($query, string $puertableType, int $puertableId)
    {
        return $query->where('puertable_type', $puertableType)->where('puertable_id', $puertableId);
    }

    /**
     * Conteo libre/ocupado/reservado/dañado de un dueño en UNA sola query (DoD #946).
     */
    public static function ocupacionDe($puertable): array
    {
        $base = array_fill_keys(
            [self::ESTADO_LIBRE, self::ESTADO_OCUPADO, self::ESTADO_RESERVADO, self::ESTADO_DANADO],
            0
        );

        $conteo = self::query()
            ->delDueno(get_class($puertable), $puertable->getKey())
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        return array_merge($base, $conteo);
    }

    /**
     * Genera automáticamente los puertos de un splitter N:M (DoD #946: splitter 1:8 en una
     * NAP -> 8 puertos de salida + 1 de entrada). `$rolEntrada`/`$rolSalida` se dejan
     * configurables porque un splitter puede ser un objeto propio (`splitter_in`/`splitter_out`,
     * MR-13) o vivir integrado en una NAP, donde la salida hacia el cliente se etiqueta
     * `nap_salida` en vez de `splitter_out`.
     */
    public static function generarParaSplitter(
        $puertable,
        int $salidas,
        int $entradas = 1,
        string $rolEntrada = self::ROL_SPLITTER_IN,
        string $rolSalida = self::ROL_SPLITTER_OUT
    ): array {
        $type = get_class($puertable);
        $id = $puertable->getKey();
        $ahora = now();
        $filas = [];

        for ($i = 1; $i <= $entradas; $i++) {
            $filas[] = [
                'puertable_type' => $type,
                'puertable_id' => $id,
                'numero' => (string) $i,
                'rol' => $rolEntrada,
                'estado' => self::ESTADO_LIBRE,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        for ($i = 1; $i <= $salidas; $i++) {
            $filas[] = [
                'puertable_type' => $type,
                'puertable_id' => $id,
                'numero' => (string) $i,
                'rol' => $rolSalida,
                'estado' => self::ESTADO_LIBRE,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        self::insert($filas);

        return self::query()->delDueno($type, $id)->whereIn('rol', [$rolEntrada, $rolSalida])->get()->all();
    }

    /**
     * Puertos PON de un OLT con notación Frame/Slot/Port (item #946: "para OLT usar el patrón
     * Frame/Slot/Port como lo maneja MultiOLT" — ver HuaweiDriver, notación tipo "0/3/2").
     */
    public static function generarPonParaOlt($puertable, int $frame, int $slot, int $cantidadPuertos): array
    {
        $type = get_class($puertable);
        $id = $puertable->getKey();
        $ahora = now();
        $filas = [];

        for ($i = 1; $i <= $cantidadPuertos; $i++) {
            $filas[] = [
                'puertable_type' => $type,
                'puertable_id' => $id,
                'numero' => (string) $i,
                'frame' => $frame,
                'slot' => $slot,
                'rol' => self::ROL_PON,
                'estado' => self::ESTADO_LIBRE,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        self::insert($filas);

        return self::query()->delDueno($type, $id)->where('rol', self::ROL_PON)
            ->where('frame', $frame)->where('slot', $slot)->get()->all();
    }
}
