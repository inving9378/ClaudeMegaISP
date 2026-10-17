<?php

namespace App\Services\Finance\Billing;

use App\Models\ClientHistorialCambio;
use App\Services\Clientes\HistorialCliente;
use App\Support\PlazoContrato;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-07 (#541, decisión de Irving 2026-10-17): capa de contrato. Decide ANTES del motor de cobro si
 * se genera el cargo sin saldo de un cliente de pago recurrente; el motor no cambia su cálculo.
 *
 *  a) contrato vigente: se factura cada mes hasta completar el plazo aunque no pague (monto del plan);
 *  b) cumplido y sin autorrenovación: máximo N facturas impagas seguidas («Facturas máximas sin pago»,
 *     3 por defecto); después ya no se factura;
 *  c) cumplido con autorrenovación: se abre otro plazo de la misma duración y vuelve a aplicar (a);
 *  d) al llegar a N impagas, en el siguiente ciclo ya no se factura y el cliente pasa a Inactivo
 *     (historial con origen «sistema»).
 *
 * Solo con el interruptor BILLING_CAPA_CONTRATO (apagado por default) y solo para ciclos desde la
 * fecha de las reglas (sin retroactivo). Con null el motor sigue con su regla de siempre (tope por
 * pagos reales). Una «factura impaga» es un cargo sin saldo autorizado por esta capa después del
 * último pago del cliente: cualquier pago reinicia la cuenta.
 */
final class CapaContrato
{
    public const FACTURAS_MAX_POR_DEFECTO = 3;

    public static function encendida(): bool
    {
        return (bool) config('billing.capa_contrato');
    }

    /**
     * @param mixed $client Client con id.
     * @return array{cobrar: bool, motivo: string, estado_contrato?: string, plazo?: int, fin?: Carbon, inactivar?: bool, impagos?: int, max?: int}|null
     *         null = no aplica la capa (apagada, ciclo anterior a las reglas o sin fecha de inicio):
     *         el motor sigue con su regla de siempre.
     */
    public static function decidirCargoSinSaldo($client, Carbon $ciclo): ?array
    {
        if (! self::encendida()) {
            return null;
        }
        $desde = ReglasFacturacion::reglasDesde();
        if (! $desde || $ciclo->copy()->startOfDay()->lt($desde->copy()->startOfDay())) {
            return null;
        }

        $id = (int) $client->id;
        $info = DB::table('client_main_information')->where('client_id', $id)->first();
        if (! $info) {
            return null;
        }
        if (in_array($info->estado, [ReglasFacturacion::NUEVO, ReglasFacturacion::INACTIVO, ReglasFacturacion::CANCELADO], true)) {
            return ['cobrar' => false, 'motivo' => "Cliente {$info->estado}: la capa de contrato no le genera cargos"];
        }

        $contrato = PlazoContrato::estadoEn($info, $ciclo, self::autorrenovacion($id));
        if (! $contrato) {
            return null;
        }
        $base = ['estado_contrato' => $contrato['estado'], 'plazo' => $contrato['plazo'], 'fin' => $contrato['fin']];

        if ($contrato['estado'] !== PlazoContrato::CUMPLIDO) {
            $texto = $contrato['estado'] === PlazoContrato::VIGENTE ? 'vigente' : "renovado (plazo {$contrato['plazo']})";

            return $base + ['cobrar' => true, 'motivo' => "Contrato {$texto} hasta {$contrato['fin']->format('d/m/Y')}: se factura aunque no pague"];
        }

        $max = self::facturasMaxSinPago($id);
        $impagos = self::cargosSinPago($id);
        if ($impagos < $max) {
            return $base + ['cobrar' => true, 'impagos' => $impagos + 1, 'max' => $max,
                'motivo' => 'Contrato cumplido sin autorrenovación: factura sin pago ' . ($impagos + 1) . " de {$max}"];
        }

        return $base + ['cobrar' => false, 'inactivar' => true, 'impagos' => $impagos, 'max' => $max,
            'motivo' => "Contrato cumplido sin autorrenovación y {$impagos} facturas seguidas sin pago: ya no se factura y pasa a Inactivo"];
    }

    /** Autorrenovación = la «Renovación automática» de su paquete (cada cliente tiene a lo más uno). */
    public static function autorrenovacion(int $clientId): bool
    {
        return DB::table('client_bundle_services')->where('client_id', $clientId)->where('automatic_renewal', 1)->exists();
    }

    public static function tienePaquete(int $clientId): bool
    {
        return DB::table('client_bundle_services')->where('client_id', $clientId)->exists();
    }

    public static function facturasMaxSinPago(int $clientId): int
    {
        if (! Schema::hasColumn('billing_configurations', 'facturas_max_sin_pago')) {
            return self::FACTURAS_MAX_POR_DEFECTO;
        }
        $n = DB::table('billing_configurations')->where('client_id', $clientId)->value('facturas_max_sin_pago');

        return $n !== null && (int) $n > 0 ? (int) $n : self::FACTURAS_MAX_POR_DEFECTO;
    }

    /** Cargos sin saldo que autorizó la capa después del último pago del cliente. */
    public static function cargosSinPago(int $clientId): int
    {
        if (! Schema::hasTable('client_contrato_cargos')) {
            return 0;
        }
        $ultimoPago = DB::table('payments')->where('paymentable_id', $clientId)->where('paymentable_type', 'like', '%Client')
            ->whereNull('deleted_at')->max('created_at');

        return DB::table('client_contrato_cargos')->where('client_id', $clientId)
            ->when($ultimoPago, fn ($q) => $q->where('created_at', '>', $ultimoPago))
            ->count();
    }

    /** Después de cobrar: deja el cargo para contar impagos y, si abrió un plazo nuevo, lo anota en el historial. */
    public static function registrarCargo(int $clientId, Carbon $ciclo, array $decision): void
    {
        DB::table('client_contrato_cargos')->insertOrIgnore([
            'client_id' => $clientId, 'ciclo' => $ciclo->toDateString(), 'estado_contrato' => $decision['estado_contrato'] ?? '',
            'plazo_numero' => $decision['plazo'] ?? 1, 'created_at' => now(),
        ]);
        if (($decision['estado_contrato'] ?? null) === PlazoContrato::RENOVADO && isset($decision['fin'])) {
            self::registrarRenovacion($clientId, (int) $decision['plazo'], $decision['fin']);
        }
    }

    public static function registrarRenovacion(int $clientId, int $plazo, Carbon $fin): void
    {
        $finTexto = $fin->format('d/m/Y');
        $yaEsta = DB::table('client_historial_cambios')->where('client_id', $clientId)->where('campo', 'renovacion')
            ->where('valor_nuevo', $finTexto)->exists();
        if (! $yaEsta) {
            HistorialCliente::registrar($clientId, 'renovacion', null, $finTexto, ClientHistorialCambio::ORIGEN_SISTEMA,
                "Contrato renovado automáticamente: plazo {$plazo}, hasta {$finTexto}");
        }
    }

    /** (d): pasa a Inactivo por el modelo (mismos efectos que una inactivación normal) y queda en el historial. */
    public static function inactivarPorImpagos(int $clientId, int $impagos): void
    {
        HistorialCliente::conOrigen(ClientHistorialCambio::ORIGEN_SISTEMA, function () use ($clientId) {
            \App\Models\ClientMainInformation::where('client_id', $clientId)->first()?->update(['estado' => ReglasFacturacion::INACTIVO]);
        }, "{$impagos} facturas seguidas sin pago con el contrato cumplido y sin autorrenovación");
    }
}
