<?php

namespace App\Services\Asistente\Herramientas;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lecturas y formatos compartidos por las herramientas de clientes. Solo lectura.
 * Número de cliente = clients.id (el mismo que se ve en la ficha, con o sin ceros a la izquierda).
 */
class DatosCliente
{
    public const TIPO_CLIENTE = 'App\Models\Client';

    /** Fecha de pagos.date tolerante a los dos formatos que conviven (YYYY-MM-DD[ HH:MM] y DD/MM/YYYY). */
    public const FECHA_PAGO_SQL = "COALESCE(STR_TO_DATE(LEFT(p.date, 10), '%Y-%m-%d'), STR_TO_DATE(p.date, '%d/%m/%Y'))";

    public static function cliente(int $numero): ?object
    {
        return DB::table('client_main_information as c')
            ->join('clients as cl', 'cl.id', '=', 'c.client_id')
            ->leftJoin('colonies as col', 'col.id', '=', 'c.colony_id')
            ->whereNull('cl.deleted_at')
            ->whereNull('c.deleted_at')
            ->where('cl.id', $numero)
            ->first([
                'cl.id as numero', 'c.id as cmi_id', 'c.name', 'c.father_last_name', 'c.mother_last_name',
                'c.estado', 'c.email', 'c.phone', 'c.phone2', 'c.phone3', 'c.street', 'c.external_number',
                'c.internal_number', 'c.zip', 'c.colony_id', 'col.name as colonia', 'c.activation_date',
                'c.discharge_date', 'cl.fecha_corte', 'cl.fecha_pago',
            ]);
    }

    public static function nombre(object $c): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', [$c->name ?? '', $c->father_last_name ?? '', $c->mother_last_name ?? ''])));
    }

    public static function saldo(int $numero): ?float
    {
        $v = DB::table('balances')->where('balanceable_type', self::TIPO_CLIENTE)->where('balanceable_id', $numero)->value('amount');

        return $v === null ? null : (float) $v;
    }

    /** Saldo negativo = el cliente debe; positivo = saldo a favor. */
    public static function saldoTexto(?float $saldo): string
    {
        if ($saldo === null) {
            return 'sin saldo registrado';
        }
        if ($saldo < -0.004) {
            return 'debe ' . self::dinero(abs($saldo));
        }
        if ($saldo > 0.004) {
            return 'tiene saldo a favor de ' . self::dinero($saldo);
        }

        return 'está al corriente ($0.00)';
    }

    public static function dinero(float|int|string|null $monto): string
    {
        return '$' . number_format((float) $monto, 2, '.', ',');
    }

    public static function fecha(mixed $valor, bool $conHora = false): ?string
    {
        if ($valor === null || $valor === '' || $valor === '0' || str_starts_with((string) $valor, '0000')) {
            return null;
        }
        try {
            $f = $valor instanceof Carbon ? $valor : Carbon::parse((string) $valor);
        } catch (\Throwable) {
            return (string) $valor;
        }

        return $f->locale('es')->isoFormat($conHora ? 'D [de] MMMM [de] YYYY, HH:mm' : 'D [de] MMMM [de] YYYY');
    }

    public static function mes(string $ym): string
    {
        try {
            return Carbon::createFromFormat('Y-m', $ym)->locale('es')->isoFormat('MMMM YYYY');
        } catch (\Throwable) {
            return $ym;
        }
    }

    public static function noEncontrado(int $numero): array
    {
        return ['error' => "No encontré al cliente número {$numero}. Pide el nombre o el teléfono y búscalo con buscar_cliente."];
    }
}
