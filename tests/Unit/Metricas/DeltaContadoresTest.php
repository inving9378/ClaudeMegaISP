<?php

namespace Tests\Unit\Metricas;

use App\Services\Metricas\DeltaContadores;
use App\Services\Metricas\LecturaInterfaz;
use App\Services\Metricas\SimuladoMetricasDriver;
use PHPUnit\Framework\TestCase;

/**
 * Item #988: cálculo de bajada/subida desde los contadores de la VLAN. No arranca la app ni
 * toca MySQL (mismo criterio que GuardBaseDePruebasTest).
 */
class DeltaContadoresTest extends TestCase
{
    private const HUECO = 300;
    private const MAXIMO = 40_000_000_000;

    private function lectura(int $rx, int $tx, float $en, bool $running = true, ?int $rxP = null, ?int $txP = null): LecturaInterfaz
    {
        return new LecturaInterfaz($rx, $tx, $rxP ?? intdiv($rx, 500), $txP ?? intdiv($tx, 1000), $running, $en);
    }

    private function previo(LecturaInterfaz $l): array
    {
        return ['rx_byte' => $l->rxByte, 'tx_byte' => $l->txByte, 'rx_packet' => $l->rxPacket, 'tx_packet' => $l->txPacket, 'leido_en' => $l->leidoEn];
    }

    public function test_bajada_es_tx_y_subida_es_rx(): void
    {
        $antes = $this->lectura(rx: 1_000_000, tx: 10_000_000, en: 1000.0, rxP: 1000, txP: 5000);
        // En 60 s: tx sube 75 MB (10 Mbps... ×8) y rx sube 7.5 MB.
        $ahora = $this->lectura(rx: 1_000_000 + 7_500_000, tx: 10_000_000 + 75_000_000, en: 1060.0, rxP: 1600, txP: 65000);

        $f = DeltaContadores::calcular($ahora, $this->previo($antes), self::HUECO, self::MAXIMO);

        $this->assertSame('ok', $f['estado']);
        $this->assertSame(10_000_000, $f['bajada_bps']);
        $this->assertSame(1_000_000, $f['subida_bps']);
        $this->assertSame(1000, $f['bajada_pps']);
        $this->assertSame(10, $f['subida_pps']);
    }

    public function test_sin_lectura_previa_no_hay_velocidad(): void
    {
        $f = DeltaContadores::calcular($this->lectura(5, 5, 10.0), null, self::HUECO, self::MAXIMO);

        $this->assertSame('primera_lectura', $f['estado']);
        $this->assertNull($f['bajada_bps']);
        $this->assertNull($f['subida_bps']);
    }

    public function test_contador_que_baja_es_reinicio_y_no_da_negativo(): void
    {
        $antes = $this->lectura(9_000_000, 9_000_000, 100.0);
        $ahora = $this->lectura(2_000, 9_500_000, 160.0);

        $f = DeltaContadores::calcular($ahora, $this->previo($antes), self::HUECO, self::MAXIMO);

        $this->assertSame('contador_reiniciado', $f['estado']);
        $this->assertNull($f['bajada_bps']);
        $this->assertNull($f['subida_bps']);
    }

    public function test_hueco_largo_no_se_promedia(): void
    {
        $antes = $this->lectura(1_000, 1_000, 100.0);
        $ahora = $this->lectura(9_000_000, 9_000_000, 100.0 + self::HUECO + 1);

        $f = DeltaContadores::calcular($ahora, $this->previo($antes), self::HUECO, self::MAXIMO);

        $this->assertSame('hueco_largo', $f['estado']);
        $this->assertNull($f['bajada_bps']);
    }

    public function test_salto_imposible_se_descarta(): void
    {
        $antes = $this->lectura(0, 0, 100.0);
        $ahora = $this->lectura(0, 1_000_000_000_000, 160.0); // ≈133 Gbps

        $f = DeltaContadores::calcular($ahora, $this->previo($antes), self::HUECO, self::MAXIMO);

        $this->assertSame('lectura_invalida', $f['estado']);
        $this->assertNull($f['bajada_bps']);
    }

    public function test_interfaz_abajo_manda_sobre_los_demas_estados(): void
    {
        $antes = $this->lectura(1_000, 1_000, 100.0);
        $ahora = $this->lectura(1_000, 1_000, 160.0, running: false);

        $f = DeltaContadores::calcular($ahora, $this->previo($antes), self::HUECO, self::MAXIMO);
        $this->assertSame('interfaz_caida', $f['estado']);
        $this->assertSame(0, $f['bajada_bps']);

        $sinBase = DeltaContadores::calcular($ahora, null, self::HUECO, self::MAXIMO);
        $this->assertSame('interfaz_caida', $sinBase['estado']);
    }

    public function test_simulado_el_contador_siempre_crece_y_su_diferencia_es_la_tasa(): void
    {
        $semilla = '2|vlan101';
        $t = 1_793_000_000.0;
        $previo = null;

        for ($i = 0; $i < 1440; $i += 7) {
            $en = $t + $i * 60;
            foreach (['bajada', 'subida'] as $sentido) {
                $this->assertGreaterThan(0, SimuladoMetricasDriver::tasaBps($semilla, $sentido, $en));
            }
            $actual = SimuladoMetricasDriver::bytesAcumulados($semilla, 'bajada', $en);
            if ($previo !== null) {
                $this->assertGreaterThan($previo, $actual);
            }
            $previo = $actual;
        }

        // En un minuto, la diferencia del contador ≈ la tasa del medio del minuto (±2 %).
        $a = SimuladoMetricasDriver::bytesAcumulados($semilla, 'bajada', $t);
        $b = SimuladoMetricasDriver::bytesAcumulados($semilla, 'bajada', $t + 60);
        $tasa = SimuladoMetricasDriver::tasaBps($semilla, 'bajada', $t + 30);
        $this->assertEqualsWithDelta($tasa, ($b - $a) * 8 / 60, $tasa * 0.02);
    }
}
