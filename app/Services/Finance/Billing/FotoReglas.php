<?php

namespace App\Services\Finance\Billing;

use App\Support\PlazoContrato;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-08 (#542): foto de SOLO LECTURA de cómo quedaría cada cliente con las reglas por estado
 * (FR-06) y de contrato (FR-07), con las mismas funciones que usa el cobro. Carga los datos en
 * bloque (unas pocas consultas) para recorrer todos los clientes. La usan la simulación, el paso de
 * inactivos antiguos a cancelado y el diagnóstico.
 */
final class FotoReglas
{
    /** @return array<int, array<string, mixed>> una fila por cliente (client_id → datos) */
    public static function clientes(Carbon $hoy): array
    {
        $hoy = $hoy->copy()->startOfDay();
        $conEspecial = Schema::hasColumn('client_main_information', 'plazo_especial_meses');
        $info = DB::table('client_main_information as m')
            ->join('clients as c', 'c.id', '=', 'm.client_id')
            ->whereNull('c.deleted_at')
            ->get(array_merge(['m.client_id', 'm.estado', 'm.type_of_billing_id', 'm.duration_contract_id', 'm.activation_date',
                'm.discharge_date', 'm.created_at', 'c.fecha_suspension', 'c.fecha_pago', 'c.fecha_corte'],
                $conEspecial ? ['m.plazo_especial_meses'] : []));

        $saldos = DB::table('balances')->where('balanceable_type', 'like', '%Client')->pluck('amount', 'balanceable_id')->all();
        $paquete = array_flip(DB::table('client_bundle_services')->pluck('client_id')->all());
        $auto = array_flip(DB::table('client_bundle_services')->where('automatic_renewal', 1)->pluck('client_id')->all());
        $inactHist = Schema::hasTable('client_historial_cambios')
            ? DB::table('client_historial_cambios')->where('campo', 'estado')->where('valor_nuevo', ReglasFacturacion::INACTIVO)
                ->groupBy('client_id')->selectRaw('client_id, MAX(created_at) f')->pluck('f', 'client_id')->all()
            : [];

        $r = [];
        foreach ($info as $m) {
            $id = (int) $m->client_id;
            $m->plazo_especial_meses = $m->plazo_especial_meses ?? null;
            $saldo = (float) ($saldos[$id] ?? 0);
            $adeudo = $saldo < 0 ? round(-$saldo, 2) : 0.0;
            $inactivoDesde = null;
            if ($m->estado === ReglasFacturacion::INACTIVO) {
                $f = $inactHist[$id] ?? $m->fecha_suspension;
                try {
                    $inactivoDesde = $f ? Carbon::parse($f) : null;
                } catch (\Throwable $e) {
                    $inactivoDesde = null;
                }
            }
            $autorrenovacion = isset($auto[$id]);
            $contrato = PlazoContrato::estadoEn($m, $hoy, $autorrenovacion);
            $fila = [
                'client_id'      => $id,
                'estado'         => (string) $m->estado,
                'recurrente'     => (int) $m->type_of_billing_id === 1,
                'tipo'           => (int) $m->type_of_billing_id,
                'adeudo'         => $adeudo,
                'inactivo_desde' => $inactivoDesde,
                'documento'      => ReglasFacturacion::documentoDelMes($m->estado, $adeudo, $inactivoDesde, $hoy),
                'contrato'       => $contrato['estado'] ?? 'sin_inicio',
                'fin'            => isset($contrato['fin']) ? $contrato['fin']->toDateString() : null,
                'plazo_numero'   => $contrato['plazo'] ?? null,
                'meses'          => PlazoContrato::meses($m),
                'sin_plazo'      => ! $m->duration_contract_id,
                'activacion_valida' => PlazoContrato::fecha($m->activation_date) !== null,
                'activacion'     => (string) $m->activation_date,
                'autorrenovacion'=> $autorrenovacion,
                'tiene_paquete'  => isset($paquete[$id]),
                'fecha_corte'    => (string) $m->fecha_corte,
                'impagos'        => null,
                'max_sin_pago'   => null,
            ];
            if ($fila['recurrente'] && $fila['contrato'] === PlazoContrato::CUMPLIDO) {
                $fila['impagos'] = CapaContrato::cargosSinPago($id);
                $fila['max_sin_pago'] = CapaContrato::facturasMaxSinPago($id);
            }
            $r[$id] = $fila;
        }

        return $r;
    }

    /**
     * Inactivo «antiguo» para pasar a Cancelado (decisión de Irving): más de 12 meses inactivo o sin
     * adeudo. Devuelve el motivo, o null si no aplica. Sin fecha de inactivación conocida: 'sin_fecha'.
     */
    public static function motivoInactivoACancelado(array $fila, Carbon $hoy): ?string
    {
        if ($fila['estado'] !== ReglasFacturacion::INACTIVO) {
            return null;
        }
        if ($fila['adeudo'] <= 0) {
            return 'sin_adeudo';
        }
        if (! $fila['inactivo_desde']) {
            return 'sin_fecha';
        }
        $limite = $hoy->copy()->startOfDay()->subMonthsNoOverflow(ReglasFacturacion::MESES_ESTADO_CUENTA_INACTIVO);

        return $fila['inactivo_desde']->lt($limite) ? 'mas_de_12_meses' : null;
    }
}
