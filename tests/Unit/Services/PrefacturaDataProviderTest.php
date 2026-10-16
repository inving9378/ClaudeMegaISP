<?php

namespace Tests\Unit\Services;

use App\Services\DocumentTemplateService;
use App\Services\Finance\Billing\PrefacturaDataProvider;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Candado del PrefacturaDataProvider (item #480). El validador de plantillas hace $data[clave]
 * sobre cada variable registrada: si el provider dejara de devolver alguna, o la devolviera
 * null, la Prefactura dejaría de generarse. Solo prueba armar(), que es PURA: no arranca la app
 * ni toca MySQL.
 */
class PrefacturaDataProviderTest extends TestCase
{
    private function provider(): PrefacturaDataProvider
    {
        return new PrefacturaDataProvider();
    }

    private function contextoPendiente(array $cambios = []): array
    {
        return array_merge([
            'numero'          => 'PROF-202610-1',
            'estado'          => PrefacturaDataProvider::ESTADO_PENDIENTE,
            'parcial'         => false,
            'saldo'           => 449.0,
            'subtotal'        => 387.07,
            'iva'             => 61.93,
            'total'           => 449.0,
            'vence'           => Carbon::create(2026, 11, 14),
            'periodo'         => 'noviembre-diciembre 2026',
            'conceptos'       => [
                ['nombre' => 'Internet <Gamer> 100+', 'descripcion' => '', 'cantidad' => 1.0,
                 'subtotal' => 387.07, 'iva' => 61.93, 'total' => 449.0],
            ],
            'fiscal'          => null,
            'correo_empresa'  => 'soporte@meganett.com.mx',
            'banco'           => 'SANTANDER',
            'cuenta'          => '014180655063756953',
            'portal'          => '',
            'referencia_pago' => 'MEG-00005607-37',
            'pago'            => [],
            'logo'            => 'data:image/png;base64,AAAA',
            'ahora'           => Carbon::create(2026, 10, 16, 15, 42),
        ], $cambios);
    }

    /** @return array<string, array{0: array}> */
    public static function contextos(): array
    {
        return [
            'vacío'                  => [[]],
            'pagado sin datos de pago' => [['estado' => PrefacturaDataProvider::ESTADO_PAGADO]],
            'nulos por todos lados'  => [[
                'numero' => null, 'vence' => null, 'periodo' => null, 'conceptos' => [],
                'fiscal' => null, 'logo' => null, 'pago' => ['fecha' => null, 'metodo' => null],
            ]],
        ];
    }

    private function assertTodasLasClavesComoString(array $r): void
    {
        $this->assertSame(PrefacturaDataProvider::CLAVES, array_keys($r['data']));
        $this->assertSame(PrefacturaDataProvider::DINAMICAS, array_keys($r['dinamicas']));
        foreach (array_merge($r['data'], $r['dinamicas']) as $clave => $valor) {
            $this->assertIsString($valor, "La clave {$clave} no es string");
        }
        $this->assertArrayHasKey('iva_monto', $r['data']);
        $this->assertArrayHasKey('logo_pdf', $r['data']);
        $this->assertNotSame('', $r['data']['logo_pdf'], 'logo_pdf vacío rompería el <img>');
    }

    public function test_las_19_claves_del_spec_mas_iva_monto_y_logo_pdf(): void
    {
        $delSpec = ['documento_titulo', 'banda_label', 'chip_clase', 'chip_texto', 'banda_nota',
            'receptor_rfc', 'receptor_nombre_fiscal', 'receptor_regimen_fiscal', 'receptor_cp_fiscal',
            'receptor_uso_cfdi', 'receptor_nota', 'conceptos_filas', 'subtotal', 'descuentos', 'total',
            'total_letra', 'bloque_pago', 'bloque_fiscal', 'leyenda_legal'];

        $this->assertCount(19, $delSpec);
        $this->assertEqualsCanonicalizing(
            array_merge($delSpec, ['iva_monto', 'logo_pdf']),
            PrefacturaDataProvider::CLAVES
        );
    }

    public function test_el_grupo_prefactura_del_validador_es_el_mismo_que_el_provider(): void
    {
        $grupo = DocumentTemplateService::DATA_CLIENT_VARIABLES_VALUE['Client']['Prefactura'];
        $this->assertEqualsCanonicalizing(
            array_map(fn ($c) => 'data.' . $c, PrefacturaDataProvider::CLAVES),
            array_keys($grupo)
        );
    }

    /** @dataProvider contextos */
    public function test_siempre_devuelve_todas_las_claves_como_string(array $contexto): void
    {
        $this->assertTodasLasClavesComoString($this->provider()->armar($contexto));
    }

    public function test_pendiente(): void
    {
        $r = $this->provider()->armar($this->contextoPendiente());
        $this->assertTodasLasClavesComoString($r);
        $d = $r['data'];

        $this->assertSame('chip-pend', $d['chip_clase']);
        $this->assertSame('PENDIENTE DE PAGO', $d['chip_texto']);
        $this->assertSame('TOTAL A PAGAR', $d['banda_label']);
        $this->assertSame('Fecha límite: <b>14/11/2026</b>', $d['banda_nota']);
        $this->assertSame('$61.93', $d['iva_monto']);
        $this->assertSame('$449.00', $d['total']);
        $this->assertSame('$0.00', $d['descuentos']);
        $this->assertSame('CUATROCIENTOS CUARENTA Y NUEVE PESOS 00/100 M.N.', $d['total_letra']);
        $this->assertSame('449.00', $r['dinamicas']['data_dinamic.debit']);
        $this->assertSame('16/10/2026', $r['dinamicas']['data_dinamic.invoice_date']);
        $this->assertSame('14/11/2026', $r['dinamicas']['data_dinamic.invoice_pay_up']);
        $this->assertStringContainsString('CLABE', $d['bloque_pago']);
        $this->assertStringContainsString('MEG-00005607-37', $d['bloque_pago']);
        $this->assertStringContainsString('Internet &lt;Gamer&gt; 100+', $d['conceptos_filas'], 'se escapa el nombre');
        $this->assertStringContainsString('Sin datos fiscales registrados', $d['receptor_nota']);
        $this->assertSame('—', $d['receptor_rfc']);
        $this->assertStringContainsString('fisc-pend', $d['bloque_fiscal']);
        $this->assertStringContainsString('Emitido el 16/10/2026 15:42', $d['leyenda_legal']);
        $this->assertStringContainsString('Folio PROF-202610-1', $d['leyenda_legal']);
    }

    public function test_pagado(): void
    {
        $r = $this->provider()->armar($this->contextoPendiente([
            'estado' => PrefacturaDataProvider::ESTADO_PAGADO,
            'pago'   => ['fecha' => Carbon::create(2026, 10, 14), 'metodo' => 'Efectivo en Caja', 'referencia' => '2026-10-14139223'],
        ]));
        $d = $r['data'];

        $this->assertSame('chip-ok', $d['chip_clase']);
        $this->assertSame('PAGADO', $d['chip_texto']);
        $this->assertSame('TOTAL PAGADO', $d['banda_label']);
        $this->assertSame('Pagado el <b>14/10/2026</b>', $d['banda_nota']);
        $this->assertStringContainsString('Pago recibido', $d['bloque_pago']);
        $this->assertStringContainsString('Efectivo en Caja', $d['bloque_pago']);
        $this->assertStringNotContainsString('CLABE', $d['bloque_pago']);
    }

    public function test_parcial_muestra_el_saldo_y_lo_abonado(): void
    {
        $r = $this->provider()->armar($this->contextoPendiente(['parcial' => true, 'saldo' => 448.0]));

        $this->assertSame('448.00', $r['dinamicas']['data_dinamic.debit']);
        $this->assertStringContainsString('Abonado <b>$1.00</b> de $449.00', $r['data']['banda_nota']);
        $this->assertSame('$449.00', $r['data']['total'], 'el total de la factura no cambia');
    }

    public function test_con_datos_fiscales(): void
    {
        $r = $this->provider()->armar($this->contextoPendiente([
            'fiscal' => ['rfc' => 'XAXX010101000', 'razon_social' => 'PRUEBA & CIA', 'regimen' => '605 — Sueldos',
                         'cp' => '55790', 'uso' => 'S01 — Sin efectos fiscales'],
        ]));
        $d = $r['data'];

        $this->assertSame('XAXX010101000', $d['receptor_rfc']);
        $this->assertSame('PRUEBA &amp; CIA', $d['receptor_nombre_fiscal']);
        $this->assertSame('', $d['receptor_nota']);
        $this->assertStringContainsString('fisc-ok', $d['bloque_fiscal']);
        $this->assertStringNotContainsString('timbr', strtolower($d['bloque_fiscal']));
    }

    public function test_cuenta_que_no_es_clabe_se_etiqueta_cuenta(): void
    {
        $d = $this->provider()->armar($this->contextoPendiente(['cuenta' => '65506375']))['data'];

        $this->assertStringContainsString('>Cuenta<', $d['bloque_pago']);
        $this->assertStringNotContainsString('CLABE', $d['bloque_pago']);
    }

    public function test_periodo_legible(): void
    {
        $this->assertSame('noviembre-diciembre 2026', PrefacturaDataProvider::periodoLegible('2026-11'));
        $this->assertSame('diciembre 2026-enero 2027', PrefacturaDataProvider::periodoLegible('2026-12'));
        $this->assertSame('', PrefacturaDataProvider::periodoLegible(null));
        $this->assertSame('', PrefacturaDataProvider::periodoLegible('basura'));
    }

    public function test_fechas_en_los_formatos_que_conviven_en_la_bd(): void
    {
        $this->assertSame('14/10/2026', PrefacturaDataProvider::fecha('2026-10-14')?->format('d/m/Y'));
        $this->assertSame('14/10/2026', PrefacturaDataProvider::fecha('2026-10-14 18:20')?->format('d/m/Y'));
        $this->assertSame('14/10/2026', PrefacturaDataProvider::fecha('14/10/2026')?->format('d/m/Y'));
        $this->assertNull(PrefacturaDataProvider::fecha(''));
        $this->assertNull(PrefacturaDataProvider::fecha('no es fecha'));
    }
}
