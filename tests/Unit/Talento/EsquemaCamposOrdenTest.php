<?php

namespace Tests\Unit\Talento;

use App\Modules\Addons\Talento\Support\EsquemaCamposOrden as E;
use PHPUnit\Framework\TestCase;

/**
 * Item #1200 — esquema de campos de las plantillas de orden. Prueba pura: sin BD ni contenedor.
 */
class EsquemaCamposOrdenTest extends TestCase
{
    private function esquemaCaja(): array
    {
        return E::normalizarEsquema([
            ['key' => 'nomenclatura', 'label' => 'Nomenclatura', 'type' => 'texto', 'required' => true],
            ['key' => 'zona', 'label' => 'Zona', 'type' => 'texto', 'required' => true, 'identificador' => true],
            ['key' => 'caja', 'label' => 'Caja', 'type' => 'texto', 'required' => true, 'identificador' => true],
            ['key' => 'coordenadas', 'label' => 'Coordenadas', 'type' => 'coordenadas', 'required' => true],
            ['key' => 'modelo_caja', 'label' => 'Modelo de caja', 'type' => 'texto'],
            ['key' => 'potencia', 'label' => 'Potencia', 'type' => 'numero', 'unit' => 'dBm', 'required' => true, 'when' => 'cierre'],
            ['key' => 'etiquetado', 'label' => 'Etiquetado', 'type' => 'si_no', 'when' => 'cierre'],
        ]);
    }

    private function esquemaCompleto(): array
    {
        return E::normalizarEsquema([
            ['key' => 'lugar', 'label' => 'Lugar', 'type' => 'texto_largo'],
            ['key' => 'km', 'label' => 'Km', 'type' => 'numero', 'unit' => 'km'],
            ['key' => 'fibra', 'label' => 'Tipo de fibra', 'type' => 'seleccion', 'options' => ['FIG 0', 'FIG 8']],
            ['key' => 'motivo', 'label' => 'Motivo', 'type' => 'multi_seleccion', 'options' => ['Nueva', 'Saturada', 'Dañada']],
            ['key' => 'fecha', 'label' => 'Fecha', 'type' => 'fecha'],
            ['key' => 'conectores', 'label' => 'Conectores', 'type' => 'tabla', 'rows_count' => 16, 'row_label_prefix' => 'Conector',
                'columns' => [
                    ['key' => 'id_cliente', 'label' => 'ID', 'type' => 'texto'],
                    ['key' => 'potencia', 'label' => 'Potencia', 'type' => 'numero', 'unit' => 'dBm'],
                    ['key' => 'activo', 'label' => 'Activo', 'type' => 'si_no'],
                ]],
            ['key' => 'postes', 'label' => 'Postes', 'type' => 'tabla', 'dynamic' => true, 'max_rows' => 3,
                'columns' => [['key' => 'coord', 'label' => 'Coordenadas', 'type' => 'coordenadas']]],
        ]);
    }

    public function test_esquema_valido_sin_errores_y_ordenado(): void
    {
        $e = $this->esquemaCaja();
        $this->assertSame([], $e['errors']);
        $this->assertSame('nomenclatura', $e['fields'][0]['key']);
        $this->assertSame('creacion', $e['fields'][0]['when']);
        $this->assertSame([], $this->esquemaCompleto()['errors']);
    }

    public function test_esquema_rechaza_claves_repetidas_tipos_y_opciones(): void
    {
        $e = E::normalizarEsquema([
            ['key' => 'a', 'label' => 'A', 'type' => 'texto'],
            ['key' => 'a', 'label' => 'A2', 'type' => 'texto'],
            ['key' => 'B mal', 'label' => 'B', 'type' => 'texto'],
            ['key' => 'c', 'label' => 'C', 'type' => 'inventado'],
            ['key' => 'd', 'label' => 'D', 'type' => 'seleccion', 'options' => []],
            ['key' => 'e', 'label' => 'E', 'type' => 'tabla', 'columns' => []],
            ['key' => 'f', 'label' => 'F', 'type' => 'si_no', 'identificador' => true],
        ]);
        foreach (['fields.1.key', 'fields.2.key', 'fields.3.type', 'fields.4.options', 'fields.5.columns', 'fields.5.rows_count', 'fields.6.identificador'] as $k) {
            $this->assertArrayHasKey($k, $e['errors'], $k);
        }
    }

    public function test_datos_validos_se_normalizan(): void
    {
        $r = E::validarDatos($this->esquemaCaja()['fields'], [
            'nomenclatura' => ' D1 ', 'zona' => '3', 'caja' => '12', 'coordenadas' => '19.432608, -99.133209',
        ]);
        $this->assertSame([], $r['errors']);
        $this->assertSame('D1', $r['values']['nomenclatura']);
        $this->assertSame('19.432608,-99.133209', $r['values']['coordenadas']);
        $this->assertArrayNotHasKey('modelo_caja', $r['values']);
    }

    public function test_falta_obligatorio_de_creacion(): void
    {
        $r = E::validarDatos($this->esquemaCaja()['fields'], ['nomenclatura' => 'D1', 'zona' => '3', 'coordenadas' => '19.4,-99.1']);
        $this->assertSame(['datos.caja'], array_keys($r['errors']));
    }

    public function test_obligatorio_de_cierre_no_se_exige_al_crear_y_no_se_acepta(): void
    {
        $base = ['nomenclatura' => 'D1', 'zona' => '3', 'caja' => '1', 'coordenadas' => '19.4,-99.1'];
        $this->assertSame([], E::validarDatos($this->esquemaCaja()['fields'], $base)['errors']);

        $r = E::validarDatos($this->esquemaCaja()['fields'], $base + ['potencia' => -20]);
        $this->assertArrayHasKey('datos.potencia', $r['errors']);
    }

    public function test_llave_desconocida_se_rechaza(): void
    {
        $r = E::validarDatos($this->esquemaCaja()['fields'], [
            'nomenclatura' => 'D1', 'zona' => '3', 'caja' => '1', 'coordenadas' => '19.4,-99.1', 'inventado' => 'x',
        ]);
        $this->assertSame(['datos.inventado'], array_keys($r['errors']));
    }

    /** @dataProvider coordenadasInvalidas */
    public function test_coordenadas_invalidas(string $v): void
    {
        $r = E::validarDatos($this->esquemaCaja()['fields'], ['nomenclatura' => 'D1', 'zona' => '3', 'caja' => '1', 'coordenadas' => $v]);
        $this->assertArrayHasKey('datos.coordenadas', $r['errors']);
    }

    public static function coordenadasInvalidas(): array
    {
        return [['91,10'], ['10,181'], ['19.4'], ['abc,def'], ['19.4;-99.1']];
    }

    public function test_tipos_numero_seleccion_multi_fecha(): void
    {
        $f = $this->esquemaCompleto()['fields'];
        $ok = E::validarDatos($f, ['km' => '1,5', 'fibra' => 'FIG 8', 'motivo' => ['Dañada', 'Nueva', 'Nueva'], 'fecha' => '2026-11-09']);
        $this->assertSame([], $ok['errors']);
        $this->assertSame(1.5, $ok['values']['km']);
        $this->assertSame(['Nueva', 'Dañada'], $ok['values']['motivo']);

        $mal = E::validarDatos($f, ['km' => 'diez', 'fibra' => 'FIG 3', 'motivo' => ['Otra'], 'fecha' => '2026-02-30']);
        $this->assertEqualsCanonicalizing(['datos.km', 'datos.fibra', 'datos.motivo', 'datos.fecha'], array_keys($mal['errors']));
    }

    public function test_tabla_fija_conserva_posiciones_y_valida_celdas(): void
    {
        $f = $this->esquemaCompleto()['fields'];
        $r = E::validarDatos($f, ['conectores' => [
            ['id_cliente' => '100', 'potencia' => '-21.5', 'activo' => true],
            [],
            ['id_cliente' => '300', 'activo' => 'no'],
            [],
        ]]);
        $this->assertSame([], $r['errors']);
        $this->assertCount(3, $r['values']['conectores']); // la vacía del final se recorta, la de en medio no
        $this->assertSame([], $r['values']['conectores'][1]);
        $this->assertFalse($r['values']['conectores'][2]['activo']);

        $mal = E::validarDatos($f, ['conectores' => [['potencia' => 'alta', 'otra' => 1]]]);
        $this->assertArrayHasKey('datos.conectores.0.potencia', $mal['errors']);
        $this->assertArrayHasKey('datos.conectores.0.otra', $mal['errors']);

        $demasiadas = E::validarDatos($f, ['conectores' => array_fill(0, 17, ['id_cliente' => 'x'])]);
        $this->assertArrayHasKey('datos.conectores', $demasiadas['errors']);
    }

    public function test_tabla_dinamica_descarta_vacias_y_respeta_maximo(): void
    {
        $f = $this->esquemaCompleto()['fields'];
        $r = E::validarDatos($f, ['postes' => [['coord' => '19.1,-99.1'], [], ['coord' => '19.2,-99.2']]]);
        $this->assertSame([], $r['errors']);
        $this->assertCount(2, $r['values']['postes']);

        $mal = E::validarDatos($f, ['postes' => array_fill(0, 4, ['coord' => '19.1,-99.1'])]);
        $this->assertArrayHasKey('datos.postes', $mal['errors']);
    }

    public function test_checklist_acepta_texto_y_cantidad(): void
    {
        $r = E::normalizarChecklist(['Toallitas', ['label' => 'Fleje', 'cantidad' => '20', 'unidad' => 'm'], '  ', ['label' => 'X', 'cantidad' => 'mucho']]);
        $this->assertSame(['label' => 'Toallitas', 'cantidad' => null, 'unidad' => null], $r['items'][0]);
        $this->assertSame(20.0, $r['items'][1]['cantidad']);
        $this->assertCount(3, $r['items']);
        $this->assertArrayHasKey('checklist.3.cantidad', $r['errors']);
    }

    public function test_titulo_con_identificadores_sin_repetir_tipo(): void
    {
        $f = $this->esquemaCaja()['fields'];
        $this->assertSame('[Habilitar caja] Zona 3 · Caja 12', E::titulo('Habilitar caja', 'Habilitar caja', $f, ['zona' => '3', 'caja' => '12']));
        $this->assertSame('[Habilitar caja] Zona 3 · Caja 12', E::titulo('Habilitar caja', 'habilitar  CAJA', $f, ['zona' => 'Zona 3', 'caja' => '12']));
        $this->assertSame('[Montaje] Tramo norte', E::titulo('Montaje', 'Tramo norte', [], []));
        $this->assertSame('[Montaje] Nueva orden', E::titulo('Montaje', '', [], []));
    }

    public function test_descripcion_en_renglones_etiqueta_valor(): void
    {
        $f = $this->esquemaCompleto()['fields'];
        $f[] = ['key' => 'pot', 'label' => 'Potencia', 'type' => 'numero', 'unit' => 'dBm', 'when' => 'cierre', 'required' => true, 'sort_order' => 999];
        $d = E::descripcion($f, [
            'km' => 1.5,
            'motivo' => ['Nueva', 'Dañada'],
            'fecha' => '2026-11-09',
            'conectores' => [['id_cliente' => '100', 'potencia' => -21.5, 'activo' => true], [], ['id_cliente' => '300']],
        ], 'Llevar escalera.', [['label' => 'Fleje', 'cantidad' => 20.0, 'unidad' => 'm'], ['label' => 'Toallitas', 'cantidad' => null, 'unidad' => null]], 'Avisar al llegar');

        $this->assertStringContainsString("Km: 1.5 km\nMotivo: Nueva, Dañada\nFecha: 09/11/2026\nConectores:\n  Conector 1: ID: 100 · Potencia: -21.5 dBm · Activo: Sí\n  Conector 3: ID: 300", $d);
        $this->assertStringContainsString("\n\nLlevar escalera.\n\nMATERIALES:\n- Fleje: 20 m\n- Toallitas", $d);
        $this->assertStringContainsString('AL CERRAR SE CAPTURA: Potencia (dBm)', $d);
        $this->assertStringEndsWith("NOTAS:\nAvisar al llegar", $d);
    }
}
