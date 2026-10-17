<?php

namespace App\Services\Finance\Billing;

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
 */
final class ReglasFacturacion
{
    public const CANCELADO = 'Cancelado';

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
