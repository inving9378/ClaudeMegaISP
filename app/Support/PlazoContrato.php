<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Plazo del contrato del cliente (FR-03 #537, decisión de Irving 2026-10-17). Reglas en código, sin
 * cambiar datos:
 *  - catálogo 6/12/18/24/36 + «Especial» (meses capturados a mano en plazo_especial_meses);
 *  - cliente sin plazo → 18 meses;
 *  - inicio = fecha de activación válida (AAAA-MM-DD); si no hay («now», vacía, otro formato), la
 *    fecha de alta;
 *  - fin = inicio + plazo en meses naturales (por tiempo transcurrido, no por meses pagados).
 * Lo usa la capa de contrato de la Ronda 2 (#541).
 */
final class PlazoContrato
{
    public const ESPECIAL = 'Especial';

    public const MESES_POR_DEFECTO = 18;

    public const MAX_MESES_ESPECIAL = 120;

    public const VIGENTE = 'vigente';
    public const RENOVADO = 'renovado';
    public const CUMPLIDO = 'cumplido';

    private static ?int $idEspecial = null;

    private static bool $idEspecialLeido = false;

    /** id de «Especial» en duration_contracts de ESTE entorno (se resuelve por nombre). */
    public static function idEspecial(): ?int
    {
        if (! self::$idEspecialLeido) {
            $id = DB::table('duration_contracts')->where('name', self::ESPECIAL)->value('id');
            self::$idEspecial = $id ? (int) $id : null;
            self::$idEspecialLeido = true;
        }

        return self::$idEspecial;
    }

    /** Solo para pruebas: olvida el id leído. */
    public static function olvidar(): void
    {
        self::$idEspecial = null;
        self::$idEspecialLeido = false;
    }

    public static function esEspecial($durationContractId): bool
    {
        $id = self::idEspecial();

        return $id !== null && (int) $durationContractId === $id;
    }

    /**
     * Meses del plazo: los del catálogo, los capturados si es «Especial», o 18 si no tiene.
     * @param object $info fila/modelo de client_main_information (duration_contract_id, plazo_especial_meses).
     */
    public static function meses($info): int
    {
        $id = $info->duration_contract_id ?? null;
        if (! $id) {
            return self::MESES_POR_DEFECTO;
        }
        if (self::esEspecial($id)) {
            $m = (int) ($info->plazo_especial_meses ?? 0);

            return $m > 0 ? $m : self::MESES_POR_DEFECTO;
        }
        $m = (int) DB::table('duration_contracts')->where('id', $id)->value('duration');

        return $m > 0 ? $m : self::MESES_POR_DEFECTO;
    }

    /** Inicio del contrato: activación válida o, si no, fecha de alta (o de creación). */
    public static function inicio($info): ?Carbon
    {
        foreach (['activation_date', 'discharge_date', 'created_at'] as $campo) {
            $f = self::fecha($info->{$campo} ?? null);
            if ($f) {
                return $f;
            }
        }

        return null;
    }

    public static function fin($info): ?Carbon
    {
        $inicio = self::inicio($info);

        return $inicio ? $inicio->copy()->addMonthsNoOverflow(self::meses($info)) : null;
    }

    /**
     * FR-07 (#541): estado del contrato en una fecha. Vigente mientras no se cumpla el plazo; cumplido
     * después, salvo que tenga autorrenovación: entonces se abren plazos de la misma duración
     * (renovado, plazo 2, 3…). Los plazos se cuentan siempre desde el inicio original, en meses
     * naturales, así no se recorren en los fines de mes. null si no hay fecha de inicio.
     *
     * @return array{estado: string, plazo: int, inicio: Carbon, fin: Carbon, meses: int}|null
     */
    public static function estadoEn($info, Carbon $fecha, bool $autorrenovacion): ?array
    {
        $inicio = self::inicio($info);
        if (! $inicio) {
            return null;
        }
        $meses = self::meses($info);
        $fecha = $fecha->copy()->startOfDay();
        $fin = $inicio->copy()->addMonthsNoOverflow($meses);
        if ($fecha->lt($fin)) {
            return ['estado' => self::VIGENTE, 'plazo' => 1, 'inicio' => $inicio, 'fin' => $fin, 'meses' => $meses];
        }
        if (! $autorrenovacion) {
            return ['estado' => self::CUMPLIDO, 'plazo' => 1, 'inicio' => $inicio, 'fin' => $fin, 'meses' => $meses];
        }
        $k = 1;
        while ($fecha->gte($fin) && $k < 1000) {
            $k++;
            $fin = $inicio->copy()->addMonthsNoOverflow($meses * $k);
        }

        return ['estado' => self::RENOVADO, 'plazo' => $k, 'inicio' => $inicio->copy()->addMonthsNoOverflow($meses * ($k - 1)),
            'fin' => $fin, 'meses' => $meses];
    }

    /** AAAA-MM-DD (con o sin hora) que sea una fecha real; todo lo demás no cuenta. */
    public static function fecha($valor): ?Carbon
    {
        if ($valor instanceof \DateTimeInterface) {
            return Carbon::instance($valor)->startOfDay();
        }
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', trim((string) $valor), $m)) {
            return null;
        }
        if (! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
    }

    /**
     * Reglas de validación de los meses del plazo Especial para un formulario: obligatorios (1-120)
     * si la duración elegida es «Especial»; si no, se ignoran.
     */
    public static function reglaMeses($durationContractId): array
    {
        return self::esEspecial($durationContractId)
            ? ['required', 'integer', 'min:1', 'max:' . self::MAX_MESES_ESPECIAL]
            : ['nullable'];
    }

    /** Antes de guardar: los meses solo se conservan si el plazo es «Especial». */
    public static function normalizar($modelo): void
    {
        if ($modelo->plazo_especial_meses !== null && ! self::esEspecial($modelo->duration_contract_id ?? null)) {
            $modelo->plazo_especial_meses = null;
        }
    }
}
