<?php

namespace App\Services\Metricas;

/**
 * Velocidad entre dos lecturas de los contadores de una interfaz VLAN (item #988).
 *
 * Misma lógica que mikrotik:sync-consumption: diferencia de contadores entre la lectura
 * anterior y la actual, y si un contador BAJÓ se toma como reinicio (router reiniciado,
 * interfaz recreada o desborde) y la lectura nueva pasa a ser la base. RouterOS entrega
 * contadores de 64 bits por la API, así que un desborde real no ocurre en la práctica; si
 * pasara, cae en el mismo caso y solo se pierde ese minuto.
 *
 * Aquí se hace la ÚNICA conversión a la perspectiva del cliente:
 * bajada = tx de la interfaz (lo que el router le manda), subida = rx (lo que el cliente sube).
 *
 * Pura (sin Laravel) para poder probarla sin base de datos.
 */
final class DeltaContadores
{
    public const OK = 'ok';
    public const PRIMERA_LECTURA = 'primera_lectura';
    public const HUECO_LARGO = 'hueco_largo';
    public const CONTADOR_REINICIADO = 'contador_reiniciado';
    public const LECTURA_INVALIDA = 'lectura_invalida';
    public const INTERFAZ_CAIDA = 'interfaz_caida';

    /**
     * @param  array{rx_byte:int, tx_byte:int, rx_packet:int, tx_packet:int, leido_en:float}|null  $previo
     * @return array{estado:string, bajada_bps:?int, subida_bps:?int, bajada_pps:?int, subida_pps:?int}
     */
    public static function calcular(LecturaInterfaz $actual, ?array $previo, int $huecoMaxSegundos, int $bpsMaximo): array
    {
        $fila = ['estado' => self::OK, 'bajada_bps' => null, 'subida_bps' => null, 'bajada_pps' => null, 'subida_pps' => null];

        $segundos = $previo === null ? 0.0 : $actual->leidoEn - (float) $previo['leido_en'];

        if ($previo === null || $segundos <= 0) {
            $fila['estado'] = self::PRIMERA_LECTURA;
        } elseif ($segundos > $huecoMaxSegundos) {
            $fila['estado'] = self::HUECO_LARGO;
        } elseif ($actual->txByte < $previo['tx_byte'] || $actual->rxByte < $previo['rx_byte']
            || $actual->txPacket < $previo['tx_packet'] || $actual->rxPacket < $previo['rx_packet']) {
            $fila['estado'] = self::CONTADOR_REINICIADO;
        } else {
            $bajada = (int) round(($actual->txByte - $previo['tx_byte']) * 8 / $segundos);
            $subida = (int) round(($actual->rxByte - $previo['rx_byte']) * 8 / $segundos);

            if ($bajada > $bpsMaximo || $subida > $bpsMaximo) {
                $fila['estado'] = self::LECTURA_INVALIDA;
            } else {
                $fila['bajada_bps'] = $bajada;
                $fila['subida_bps'] = $subida;
                $fila['bajada_pps'] = (int) round(($actual->txPacket - $previo['tx_packet']) / $segundos);
                $fila['subida_pps'] = (int) round(($actual->rxPacket - $previo['rx_packet']) / $segundos);
            }
        }

        // La interfaz abajo pesa más que cualquier otro estado: es lo que la pantalla debe decir.
        if (! $actual->running) {
            $fila['estado'] = self::INTERFAZ_CAIDA;
        }

        return $fila;
    }
}
