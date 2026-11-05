<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Models\TalentoWeeklyRule;
use App\Modules\Addons\Talento\Services\TalentoWeeklyRuleEvaluationService as Svc;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/** UNI-T J5 (#1009). Correr SOLO por nombre, APP_ENV=testing (megaisp_test). */
class AislamientoReglasDemoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function exigirTabla(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('talento_weekly_rules')) {
            $this->markTestSkipped('megaisp_test no tiene talento_weekly_rules (BD de pruebas sin migrar)');
        }
    }

    private function regla(string $metric, array $roles): TalentoWeeklyRule
    {
        $r = new TalentoWeeklyRule();
        $r->name = 'T-' . $metric;
        $r->roles = $roles;
        $r->metric_type = $metric;
        $r->active = true;
        $r->save();

        return $r;
    }

    private function preparar(): TalentoWeeklyRule
    {
        $this->exigirTabla();
        DB::table('talento_weekly_rules')->update(['active' => 0]);

        return $this->regla('calidad_senal', ['TECNICO']);
    }

    public function test_apagado_la_regla_demo_aplica_a_cualquiera(): void
    {
        $r = $this->preparar();
        $svc = app(Svc::class);
        config(['unificacion.interruptores.reglas_demo_aisladas' => false]);

        $this->assertSame($r->id, $svc->reglaQueAplicaA(['TECNICO'], 'calidad_senal', 'operativo')?->id);
        $this->assertSame($r->id, $svc->reglaQueAplicaA(['TECNICO'], 'calidad_senal', 'demo_tecnico_bajo')?->id);
    }

    public function test_encendido_solo_cuentas_demo_reciben_regla_demo(): void
    {
        $r = $this->preparar();
        $svc = app(Svc::class);
        config(['unificacion.interruptores.reglas_demo_aisladas' => true]);

        // la regla sintética no se llama «DEMO…»: con el interruptor encendido sigue aplicando a todos
        $this->assertSame($r->id, $svc->reglaQueAplicaA(['TECNICO'], 'calidad_senal', 'operativo')?->id);
        $this->assertTrue(Svc::esCuentaDemo('prueba_x'));
        $this->assertFalse(Svc::esCuentaDemo('operativo_x'));
    }

    public function test_regla_demo_por_nombre_se_excluye_para_operativas(): void
    {
        $this->exigirTabla();
        DB::table('talento_weekly_rules')->update(['active' => 0]);
        $r = $this->regla('calidad_senal', ['TECNICO']);
        DB::table('talento_weekly_rules')->where('id', $r->id)->update(['name' => 'DEMO — regla de prueba']);

        $svc = app(Svc::class);
        $this->assertTrue(Svc::esReglaDemo($r->fresh()));
        $otra = new TalentoWeeklyRule();
        $otra->name = 'Demostración de ventas';
        $this->assertFalse(Svc::esReglaDemo($otra));
        $this->assertNull($svc->reglaQueAplicaA(['TECNICO'], 'calidad_senal', 'operativo_x', true));
        $this->assertSame($r->id, $svc->reglaQueAplicaA(['TECNICO'], 'calidad_senal', 'demo_tecnico_bajo', true)?->id);
        $this->assertSame($r->id, $svc->reglaQueAplicaA(['TECNICO'], 'calidad_senal', 'operativo_x', false)?->id);
    }
}
