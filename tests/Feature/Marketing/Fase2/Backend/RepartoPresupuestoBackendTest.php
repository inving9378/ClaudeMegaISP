<?php

namespace Tests\Feature\Marketing\Fase2\Backend;

use App\Modules\Addons\Marketing\Services\Campanas360\RepartoPresupuesto;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 2 · backend — reparto del presupuesto en MXN con bcmath (§2.5.4): suma exacta, centavos, 0, normalización.
 */
class RepartoPresupuestoBackendTest extends MarketingTestCase
{
    private function suma(array $renglones): string
    {
        $s = '0.00';
        foreach ($renglones as $r) {
            $s = bcadd($s, $r['monto_mxn'], 2);
        }

        return $s;
    }

    public function test_reparto_exacto_simple(): void
    {
        $r = (new RepartoPresupuesto())->calcular([
            ['canal' => 'anuncios_meta', 'porcentaje' => '60.00', 'monto_mxn' => '1.00'],
            ['canal' => 'reels', 'porcentaje' => '40'],
        ], ['anuncios_meta', 'reels'], '15000.00');

        $this->assertSame([
            ['canal' => 'anuncios_meta', 'porcentaje' => '60.00', 'monto_mxn' => '9000.00'],
            ['canal' => 'reels', 'porcentaje' => '40.00', 'monto_mxn' => '6000.00'],
        ], $r);
    }

    public function test_tercios_con_centavos_el_ultimo_absorbe(): void
    {
        $r = (new RepartoPresupuesto())->calcular([
            ['canal' => 'facebook', 'porcentaje' => '33.33'],
            ['canal' => 'instagram', 'porcentaje' => '33.33'],
            ['canal' => 'reels', 'porcentaje' => '33.34'],
        ], ['facebook', 'instagram', 'reels'], '100.00');

        $this->assertSame(['33.33', '33.33', '33.34'], array_column($r, 'monto_mxn'));
        $this->assertSame('100.00', $this->suma($r));

        $r2 = (new RepartoPresupuesto())->calcular([
            ['canal' => 'facebook', 'porcentaje' => '33.33'],
            ['canal' => 'instagram', 'porcentaje' => '33.33'],
            ['canal' => 'reels', 'porcentaje' => '33.34'],
        ], ['facebook', 'instagram', 'reels'], '10000.01');
        $this->assertSame('10000.01', $this->suma($r2));
    }

    public function test_presupuesto_cero(): void
    {
        $r = (new RepartoPresupuesto())->calcular([['canal' => 'facebook', 'porcentaje' => '50'], ['canal' => 'reels', 'porcentaje' => '50']],
            ['facebook', 'reels'], '0');

        $this->assertSame(['0.00', '0.00'], array_column($r, 'monto_mxn'));
        $this->assertSame(['50.00', '50.00'], array_column($r, 'porcentaje'));
    }

    public function test_porcentajes_que_no_suman_100_se_normalizan(): void
    {
        $r = (new RepartoPresupuesto())->calcular([
            ['canal' => 'facebook', 'porcentaje' => '30'],
            ['canal' => 'reels', 'porcentaje' => '30'],
        ], ['facebook', 'reels'], '999.99');

        $this->assertSame(['50.00', '50.00'], array_column($r, 'porcentaje'));
        $this->assertSame('999.99', $this->suma($r));
        $this->assertSame('500.00', $r[0]['monto_mxn']);
        $this->assertSame('499.99', $r[1]['monto_mxn']);
    }

    public function test_todos_en_cero_se_reparte_parejo(): void
    {
        $r = (new RepartoPresupuesto())->calcular([
            ['canal' => 'facebook', 'porcentaje' => '0'], ['canal' => 'reels', 'porcentaje' => '0'], ['canal' => 'video', 'porcentaje' => 'x'],
        ], ['facebook', 'reels', 'video'], '100');

        $this->assertSame('100.00', $this->suma($r));
        $this->assertSame(['33.33', '33.33', '33.34'], array_column($r, 'porcentaje'));
    }

    public function test_canales_ajenos_y_repetidos_se_descartan(): void
    {
        $r = (new RepartoPresupuesto())->calcular([
            ['canal' => 'tiktok', 'porcentaje' => '50'],
            ['canal' => 'facebook', 'porcentaje' => '50'],
            ['canal' => 'facebook', 'porcentaje' => '50'],
            'basura',
        ], ['facebook'], '1234.56');

        $this->assertSame([['canal' => 'facebook', 'porcentaje' => '100.00', 'monto_mxn' => '1234.56']], $r);
        $this->assertSame([], (new RepartoPresupuesto())->calcular([], ['facebook'], '10'));
    }

    public function test_un_centavo_entre_muchos_nunca_da_negativos(): void
    {
        $r = (new RepartoPresupuesto())->calcular([
            ['canal' => 'facebook', 'porcentaje' => '50'], ['canal' => 'instagram', 'porcentaje' => '50'], ['canal' => 'reels', 'porcentaje' => '0'],
        ], ['facebook', 'instagram', 'reels'], '0.01');

        $this->assertSame('0.01', $this->suma($r));
        foreach ($r as $renglon) {
            $this->assertGreaterThanOrEqual(0, bccomp($renglon['monto_mxn'], '0', 2));
        }
    }

    public function test_dinero_normaliza_a_dos_decimales(): void
    {
        $this->assertSame('599.00', RepartoPresupuesto::dinero('599'));
        $this->assertSame('0.01', RepartoPresupuesto::dinero('0.005'));
        $this->assertSame('15000.50', RepartoPresupuesto::dinero('15000.5'));
        $this->assertSame('0.00', RepartoPresupuesto::dinero('abc'));
        $this->assertSame('0.00', RepartoPresupuesto::dinero(null));
    }
}
