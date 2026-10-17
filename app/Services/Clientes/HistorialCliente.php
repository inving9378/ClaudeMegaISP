<?php

namespace App\Services\Clientes;

use App\Models\ClientHistorialCambio;
use App\Services\Finance\Billing\ReglasFacturacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FR-04 (#538): historial de cambios de estado y de contrato del cliente (quién, cuándo, anterior →
 * nuevo, origen). Lo alimentan HistorialClienteObserver (cambios por Eloquent, en las dos clases de
 * ClientMainInformation) y los pocos caminos que actualizan por query builder (activarCliente).
 *
 * Origen: el que fije conOrigen() (p. ej. 'pago' en PaymentClientJob); si no hay, 'usuario' cuando
 * hay alguien con sesión o es el guardado manual de la ficha; si no, 'sistema' (cron, colas).
 * Registrar nunca rompe la operación que lo dispara.
 */
final class HistorialCliente
{
    /** Campos de client_main_information que se registran → etiqueta del campo. */
    public const CAMPOS = [
        'estado'               => 'estado',
        'duration_contract_id' => 'plazo',
        'plazo_especial_meses' => 'plazo_especial_meses',
        'activation_date'      => 'activation_date',
    ];

    private static ?string $origen = null;

    private static ?string $motivo = null;

    /** Corre $fn con un origen (y motivo) fijo para todo lo que se registre dentro. */
    public static function conOrigen(string $origen, callable $fn, ?string $motivo = null)
    {
        [$o, $m] = [self::$origen, self::$motivo];
        self::$origen = $origen;
        self::$motivo = $motivo;
        try {
            return $fn();
        } finally {
            [self::$origen, self::$motivo] = [$o, $m];
        }
    }

    public static function motivoActual(): ?string
    {
        return self::$motivo;
    }

    public static function origenActual(): string
    {
        if (self::$origen !== null) {
            return self::$origen;
        }

        return ReglasFacturacion::esCambioManual() || auth()->check()
            ? ClientHistorialCambio::ORIGEN_USUARIO
            : ClientHistorialCambio::ORIGEN_SISTEMA;
    }

    public static function registrar(int $clientId, string $campo, $antes, $despues, ?string $origen = null, ?string $motivo = null): void
    {
        $antes = self::texto($campo, $antes);
        $despues = self::texto($campo, $despues);
        if ($antes === $despues) {
            return;
        }
        try {
            ClientHistorialCambio::create([
                'client_id'      => $clientId,
                'campo'          => $campo,
                'valor_anterior' => $antes,
                'valor_nuevo'    => $despues,
                'origen'         => $origen ?? self::origenActual(),
                'user_id'        => auth()->id(),
                'motivo'         => $motivo ?? self::$motivo,
            ]);
        } catch (\Throwable $e) {
            Log::error("[HistorialCliente] No se registró el cambio de {$campo} del cliente {$clientId}: " . $e->getMessage());
        }
    }

    /** Valor legible: el plazo con su nombre del catálogo. */
    private static function texto(string $campo, $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if ($campo === 'plazo') {
            $nombre = DB::table('duration_contracts')->where('id', $valor)->value('name');

            return $nombre ? (string) $nombre : (string) $valor;
        }

        return mb_substr((string) $valor, 0, 255);
    }
}
