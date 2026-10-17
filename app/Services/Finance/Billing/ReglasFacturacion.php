<?php

namespace App\Services\Finance\Billing;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capa previa de las reglas de facturación (programa «Reglas de facturación y contrato», Irving
 * 2026-10-17, #534). Decide SI se genera un cargo, una prefactura o una reactivación; el motor de
 * cobro (ClientBillingService, fechas, montos) no cambia su cálculo: solo consulta esta capa antes
 * de actuar.
 *
 * FR-02 (#536): «Cancelado» es una baja definitiva:
 *  - no genera cargos automáticos ni prefacturas;
 *  - un pago abona al saldo pero no reactiva al cliente;
 *  - ningún proceso automático lo saca de Cancelado; solo un cambio manual en la ficha.
 *
 * FR-06 (#540): documento del mes según el estado (documentoDelMes()). FR-10: nunca se timbra un
 * CFDI de un periodo no pagado (puedeTimbrar()). Las reglas por estado y de contrato aplican solo
 * desde reglasDesde() (la fecha del despliegue, billing_config.reglas_facturacion_desde).
 */
final class ReglasFacturacion
{
    public const CANCELADO = 'Cancelado';
    public const ACTIVO = 'Activo';
    public const BLOQUEADO = 'Bloqueado';
    public const INACTIVO = 'Inactivo';
    public const NUEVO = 'Nuevo';

    public const DOC_PREFACTURA = 'prefactura';
    public const DOC_ESTADO_CUENTA = 'estado_cuenta';

    /** Un inactivo recibe estado de cuenta solo durante sus primeros 12 meses inactivo. */
    public const MESES_ESTADO_CUENTA_INACTIVO = 12;

    /** true solo mientras corre un cambio de estado hecho a mano en la ficha (ver cambioManual()). */
    private static bool $cambioManual = false;

    /**
     * @param mixed $client Client (modular o legacy) con relación client_main_information.
     * @param bool  $fresco true = lee el estado de la BD aunque la relación ya esté cargada.
     */
    public static function estaCancelado($client, bool $fresco = false): bool
    {
        $info = $fresco ? null : ($client->client_main_information ?? null);
        if (! $info && method_exists($client, 'client_main_information')) {
            $info = $client->client_main_information()->first();
        }

        return $info !== null && $info->estado === self::CANCELADO;
    }

    public static function generaCargos($client): bool
    {
        return ! self::estaCancelado($client);
    }

    public static function generaPrefactura($client): bool
    {
        return ! self::estaCancelado($client);
    }

    public static function reactivaConPago($client): bool
    {
        return ! self::estaCancelado($client, true);
    }

    /** Desde cuándo aplican las reglas por estado y de contrato (el despliegue); null si aún no hay fecha. */
    public static function reglasDesde(): ?Carbon
    {
        if (! Schema::hasColumn('billing_config', 'reglas_facturacion_desde')) {
            return null;
        }
        $f = DB::table('billing_config')->orderBy('id')->value('reglas_facturacion_desde');

        return $f ? Carbon::parse($f) : null;
    }

    /**
     * FR-06 (#540, decisión de Irving 2026-10-17): qué documento recibe el cliente este mes.
     *  - Nuevo: nada · Activo: prefactura (según «Envío prefactura») · Bloqueado: estado de cuenta
     *  - Inactivo: estado de cuenta solo si debe algo (adeudo > 0) y lleva máximo 12 meses inactivo;
     *    si no se sabe desde cuándo está inactivo, nada.
     *  - Cancelado: nada (solo el estado de cuenta final, al cancelarse).
     * Puro: recibe los datos ya leídos (ver adeudoDe() e inactivoDesde()).
     */
    public static function documentoDelMes(?string $estado, float $adeudo, ?Carbon $inactivoDesde, Carbon $hoy): ?string
    {
        switch ($estado) {
            case self::ACTIVO:
                return self::DOC_PREFACTURA;
            case self::BLOQUEADO:
                return self::DOC_ESTADO_CUENTA;
            case self::INACTIVO:
                $limite = $hoy->copy()->startOfDay()->subMonthsNoOverflow(self::MESES_ESTADO_CUENTA_INACTIVO);

                return $adeudo > 0 && $inactivoDesde !== null && $inactivoDesde->gte($limite)
                    ? self::DOC_ESTADO_CUENTA
                    : null;
            default:
                return null;
        }
    }

    /** Lo que debe el cliente (saldo negativo de su cuenta); 0 si no debe. */
    public static function adeudoDe(int $clientId): float
    {
        $saldo = (float) (DB::table('balances')->where('balanceable_id', $clientId)
            ->where('balanceable_type', 'like', '%Client')->value('amount') ?? 0);

        return $saldo < 0 ? round(-$saldo, 2) : 0.0;
    }

    /**
     * Desde cuándo está inactivo: el último paso a Inactivo del historial (FR-04); si no hay, la
     * fecha de suspensión (el bloqueo que precede a la inactivación); si no, null (no se sabe).
     */
    public static function inactivoDesde(int $clientId): ?Carbon
    {
        if (Schema::hasTable('client_historial_cambios')) {
            $f = DB::table('client_historial_cambios')->where('client_id', $clientId)->where('campo', 'estado')
                ->where('valor_nuevo', self::INACTIVO)->max('created_at');
            if ($f) {
                return Carbon::parse($f);
            }
        }
        $f = DB::table('clients')->where('id', $clientId)->value('fecha_suspension');
        try {
            return $f ? Carbon::parse($f) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** FR-10: nunca se timbra un CFDI de un periodo no pagado (prefactura y estado de cuenta no tienen valor fiscal). */
    public static function puedeTimbrar($invoice): bool
    {
        return $invoice !== null && $invoice->status === Invoice::STATUS_PAID && (float) $invoice->pending_balance <= 0;
    }

    /**
     * Corre $fn permitiendo sacar a un cliente de Cancelado. Solo para el guardado manual de la
     * ficha (ClientInformationController::update); todo lo demás (pagos, bloqueos, promesas,
     * despliegues) lo deja en Cancelado.
     */
    public static function cambioManual(callable $fn)
    {
        $antes = self::$cambioManual;
        self::$cambioManual = true;
        try {
            return $fn();
        } finally {
            self::$cambioManual = $antes;
        }
    }

    public static function esCambioManual(): bool
    {
        return self::$cambioManual;
    }
}
