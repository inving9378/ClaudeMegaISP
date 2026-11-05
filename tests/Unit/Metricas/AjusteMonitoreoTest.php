<?php

namespace Tests\Unit\Metricas;

use App\Models\User;
use App\Services\Metricas\AjusteMonitoreo;
use Illuminate\Support\Facades\DB;

/** Item #988, Paso 2: el interruptor «Monitoreo de dedicados» de Configuración. */
class AjusteMonitoreoTest extends TablasTemporalesTestCase
{
    public function test_sin_ajuste_guardado_esta_encendido(): void
    {
        $this->assertTrue(AjusteMonitoreo::activo());
    }

    public function test_apagado_no_colecta_nada(): void
    {
        $this->dedicado();
        $this->apagar();

        $this->artisan('metricas:colectar')->assertExitCode(0);

        $this->assertSame(array_fill_keys(array_keys($this->lecturasGuardadas()), 0), $this->lecturasGuardadas());
    }

    public function test_encendido_colecta_y_deja_latido(): void
    {
        $servicio = $this->dedicado();

        $this->artisan('metricas:colectar')->assertExitCode(0);

        $this->assertSame(1, DB::table('metricas_colector_latidos')->count());
        $this->assertSame(1, DB::table('cliente_metricas')->where('servicio_id', $servicio)->count());
    }

    public function test_metricas_activo_del_env_manda_sobre_el_ajuste(): void
    {
        $this->apagar();
        config(['metricas.activo' => true]);
        $this->assertTrue(AjusteMonitoreo::activo());

        DB::table('settings')->delete();
        config(['metricas.activo' => false]);
        $this->assertFalse(AjusteMonitoreo::activo());
        $this->assertTrue(AjusteMonitoreo::estado()['ajuste'], 'el ajuste no cambia por el override');
    }

    public function test_el_override_vacio_o_ausente_no_cuenta(): void
    {
        $this->assertNull(AjusteMonitoreo::override(null));
        $this->assertNull(AjusteMonitoreo::override(''));
        $this->assertFalse(AjusteMonitoreo::override(false));
        $this->assertTrue(AjusteMonitoreo::override('true'));
    }

    public function test_guarda_quien_y_cuando_lo_cambio(): void
    {
        $usuario = new User();
        $usuario->id = 7;
        $usuario->name = 'Persona de prueba';

        $estado = AjusteMonitoreo::cambiar(false, $usuario);

        $this->assertFalse($estado['activo']);
        $this->assertSame('Persona de prueba', $estado['cambiado_por']);
        $this->assertNotNull($estado['cambiado_en']);
        $this->assertSame('Monitoreo de dedicados apagado', DB::table('activity_log')->where('log_name', 'metricas')->value('description'));
        $this->assertSame(7, (int) DB::table('activity_log')->where('log_name', 'metricas')->value('causer_id'));
    }

    private function apagar(): void
    {
        DB::table('settings')->updateOrInsert(['key' => AjusteMonitoreo::CLAVE], ['value' => json_encode(['activo' => false])]);
    }
}
