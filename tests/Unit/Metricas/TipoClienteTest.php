<?php

namespace Tests\Unit\Metricas;

use App\Models\ClientAdditionalInformation;
use App\Services\Clientes\TipoCliente;
use Illuminate\Support\Facades\DB;

/** Item #988: «Tipo de cliente» es la única fuente de verdad; is_dedicated se deriva y el default es Residencial. */
class TipoClienteTest extends TablasTemporalesTestCase
{
    public function test_un_alta_sin_tipo_queda_residencial(): void
    {
        $info = ClientAdditionalInformation::create(['client_id' => $this->cliente()]);

        $this->assertSame('residencial', $info->tipo_cliente);
        $this->assertFalse((bool) $info->is_dedicated);
        $this->assertSame('Particular', $info->category);
    }

    public function test_un_tipo_que_no_existe_o_vacio_queda_residencial_sin_error(): void
    {
        foreach ([null, '', 'empresa'] as $tipo) {
            $info = ClientAdditionalInformation::create(['client_id' => $this->cliente(), 'tipo_cliente' => $tipo]);
            $this->assertSame('residencial', $info->tipo_cliente);
        }
    }

    public function test_crear_un_cliente_con_cada_tipo(): void
    {
        foreach (['residencial' => [false, 'Particular'], 'negocio' => [false, 'Empresa'], 'pyme' => [false, 'Empresa'], 'dedicado' => [true, 'Empresa']] as $tipo => [$dedicado, $categoria]) {
            $info = ClientAdditionalInformation::create(['client_id' => $this->cliente(), 'tipo_cliente' => $tipo]);

            $this->assertSame($tipo, $info->tipo_cliente);
            $this->assertSame($dedicado, (bool) $info->is_dedicated, $tipo);
            $this->assertSame($categoria, $info->categoria_derivada, $tipo);
        }
    }

    public function test_cambiar_el_tipo_sincroniza_is_dedicated_en_los_dos_sentidos(): void
    {
        $info = ClientAdditionalInformation::create(['client_id' => $this->cliente()]);

        $info->update(['tipo_cliente' => 'dedicado']);
        $this->assertTrue((bool) DB::table('client_additional_information')->where('id', $info->id)->value('is_dedicated'));

        $info->update(['tipo_cliente' => 'pyme']);
        $this->assertFalse((bool) DB::table('client_additional_information')->where('id', $info->id)->value('is_dedicated'));
    }

    public function test_lo_que_manda_de_vuelta_la_ficha_no_cambia_el_tipo_ni_los_campos_retirados(): void
    {
        $info = ClientAdditionalInformation::create(['client_id' => $this->cliente(), 'tipo_cliente' => 'dedicado']);
        DB::table('client_additional_information')->where('id', $info->id)->update(['category' => 'Empresa', 'segmento_id' => 2]);
        $info->refresh();

        // La ficha guarda con todos sus campos, incluidos los ocultos con su valor por defecto.
        $info->update(['is_dedicated' => false, 'category' => 'Particular', 'segmento_id' => 4]);

        $fila = DB::table('client_additional_information')->where('id', $info->id)->first();
        $this->assertSame('dedicado', $fila->tipo_cliente);
        $this->assertTrue((bool) $fila->is_dedicated);
        $this->assertSame('Empresa', $fila->category, 'category se conserva');
        $this->assertSame(2, (int) $fila->segmento_id, 'segmento_id nunca se escribe por separado');
    }

    public function test_un_alta_importada_con_la_bandera_vieja_queda_dedicado(): void
    {
        $info = ClientAdditionalInformation::create(['client_id' => $this->cliente(), 'is_dedicated' => true]);
        $this->assertSame('dedicado', $info->tipo_cliente);

        $this->assertSame(['is_dedicated' => true, 'tipo_cliente' => 'dedicado'], TipoCliente::aplicarEnDatos(['is_dedicated' => 1]));
        $this->assertSame('residencial', TipoCliente::aplicarEnDatos([])['tipo_cliente']);
        $this->assertSame('pyme', TipoCliente::aplicarEnDatos(['tipo_cliente' => 'pyme', 'is_dedicated' => 1])['tipo_cliente']);
        $this->assertFalse(TipoCliente::aplicarEnDatos(['tipo_cliente' => 'pyme', 'is_dedicated' => 1])['is_dedicated']);
    }

    public function test_poner_tipo_dedicado_dispara_el_monitoreo_y_quitarlo_lo_pausa(): void
    {
        $cliente = $this->cliente();
        DB::table('client_main_information')->insert(['client_id' => $cliente, 'estado' => 'Activo']);
        $router = DB::table('routers')->insertGetId(['title' => 'Router', 'ip_host' => '192.0.2.1']);
        DB::table('client_internet_services')->insert(['client_id' => $cliente, 'estado' => 'Activo', 'router_id' => $router]);
        $info = ClientAdditionalInformation::create(['client_id' => $cliente]);
        $this->assertSame(0, DB::table('servicio_monitoreo')->count());

        $info->update(['tipo_cliente' => 'dedicado']);
        $this->assertTrue((bool) DB::table('servicio_monitoreo')->value('activo'));

        $info->update(['tipo_cliente' => 'negocio']);
        $this->assertSame('no_dedicado', DB::table('servicio_monitoreo')->value('motivo_pausa'));
        $this->assertSame('no_dedicado', DB::table('servicio_monitoreo_pausas')->value('motivo'));
    }

    private function cliente(): int
    {
        return DB::table('clients')->insertGetId([]);
    }
}
