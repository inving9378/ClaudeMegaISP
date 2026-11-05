<?php

namespace Tests\Unit\Metricas;

use App\Services\Metricas\CausaSinDatos;
use PHPUnit\Framework\TestCase;

/** Item #988: el motivo claro de «Sin datos», de lo más general a lo más particular. */
class CausaSinDatosTest extends TestCase
{
    public function test_apagado_en_configuracion_es_lo_primero_y_dice_quien(): void
    {
        $c = CausaSinDatos::de($this->hechos(['monitoreo' => ['activo' => false, 'override' => null, 'cambiado_por' => 'Persona', 'cambiado_en' => '2026-11-04 10:00:00'], 'configurado' => false]));

        $this->assertSame('monitoreo_apagado', $c['codigo']);
        $this->assertStringContainsString('Persona', $c['detalle']);
    }

    public function test_apagado_por_el_env_lo_dice(): void
    {
        $c = CausaSinDatos::de($this->hechos(['monitoreo' => ['activo' => false, 'override' => false, 'cambiado_por' => null, 'cambiado_en' => null]]));

        $this->assertStringContainsString('METRICAS_ACTIVO', $c['detalle']);
    }

    public function test_servicio_no_configurado(): void
    {
        $this->assertSame('servicio_no_configurado', CausaSinDatos::de($this->hechos(['configurado' => false]))['codigo']);
        $this->assertSame('servicio_no_configurado', CausaSinDatos::de($this->hechos(['con_router' => false]))['codigo']);
        $this->assertSame('servicio_no_configurado', CausaSinDatos::de($this->hechos(['con_que_medir' => false]))['codigo']);
    }

    public function test_el_colector_no_ha_corrido(): void
    {
        $this->assertSame('colector_sin_correr', CausaSinDatos::de($this->hechos(['latido_segundos' => null]))['codigo']);
        $c = CausaSinDatos::de($this->hechos(['latido_segundos' => 3600]));
        $this->assertSame('colector_sin_correr', $c['codigo']);
        $this->assertStringContainsString('60 min', $c['detalle']);
    }

    public function test_router_sin_respuesta_trae_el_ultimo_error(): void
    {
        $c = CausaSinDatos::de($this->hechos(['router' => ['estado' => 'sin_conexion', 'error' => 'Sin conexión con 192.0.2.1:8728']]));

        $this->assertSame('router_sin_respuesta', $c['codigo']);
        $this->assertStringContainsString('192.0.2.1', $c['detalle']);
    }

    public function test_identidad_cambiada_o_sin_confirmar(): void
    {
        $this->assertSame('identidad_distinta', CausaSinDatos::de($this->hechos(['router' => ['estado' => 'identidad_distinta', 'error' => 'Contestó «B»']]))['codigo']);
        $this->assertSame('identidad_sin_confirmar', CausaSinDatos::de($this->hechos(['router' => ['estado' => 'sin_identidad_esperada', 'error' => 'x']]))['codigo']);
    }

    public function test_vlan_no_encontrada_o_repetida(): void
    {
        $this->assertSame('vlan_no_encontrada', CausaSinDatos::de($this->hechos(['estado_fila' => 'interfaz_no_encontrada']))['codigo']);
        $this->assertSame('vlan_repetida', CausaSinDatos::de($this->hechos(['estado_fila' => 'vlan_repetida']))['codigo']);
    }

    public function test_ip_sin_respuesta_lista_cuales(): void
    {
        $c = CausaSinDatos::de($this->hechos(['ips_sin_respuesta' => ['192.0.2.10', '192.0.2.11']]));

        $this->assertSame('ip_sin_respuesta', $c['codigo']);
        $this->assertStringContainsString('192.0.2.11', $c['detalle']);
    }

    public function test_sin_ips_y_primera_lectura(): void
    {
        $this->assertSame('sin_ips', CausaSinDatos::de($this->hechos(['sin_ips' => true]))['codigo']);
        $this->assertSame('sin_lecturas', CausaSinDatos::de($this->hechos())['codigo']);
    }

    private function hechos(array $cambios = []): array
    {
        return $cambios + [
            'monitoreo' => ['activo' => true, 'override' => null, 'cambiado_por' => null, 'cambiado_en' => null],
            'configurado' => true, 'con_router' => true, 'con_que_medir' => true,
            'latido_segundos' => 30, 'umbral_segundos' => 180, 'router' => null, 'estado_fila' => null,
            'sin_ips' => false, 'ips_sin_respuesta' => [],
        ];
    }
}
