<?php

namespace Tests\Feature\Unificacion;

use App\Services\IncentiveCompatibilityService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * UNI-T I5 F3: cada combinación de la matriz de compatibilidad de incentivos + caso default (= hoy).
 * Sin base de datos: el servicio solo lee config(), así que usa un contenedor mínimo (megaisp_test no hace falta).
 */
class IncentiveCompatibilityServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $c = new Container();
        $c->instance('config', new Repository(['unificacion' => ['compatibilidad_incentivos' => ['activo' => false, 'matriz' => [], 'campanas' => []]]]));
        Container::setInstance($c);
    }

    private function svc(bool $activo, array $matriz = [], array $campanas = []): IncentiveCompatibilityService
    {
        config([
            'unificacion.compatibilidad_incentivos.activo' => $activo,
            'unificacion.compatibilidad_incentivos.matriz' => array_merge([
                'vendedor_talento' => 'compatible',
                'vendedor_embajador' => 'compatible',
                'talento_embajador' => 'compatible',
            ], $matriz),
            'unificacion.compatibilidad_incentivos.campanas' => $campanas,
        ]);
        return new IncentiveCompatibilityService();
    }

    public function test_default_apagado_config_real_siempre_aplica(): void
    {
        $s = new IncentiveCompatibilityService();
        foreach (['talento' => ['vendedor'], 'embajador' => ['vendedor', 'talento']] as $inc => $otros) {
            $this->assertTrue($s->aplicable($inc, $otros));
        }
    }

    public function test_apagado_ignora_matriz_excluyente(): void
    {
        $s = $this->svc(false, ['vendedor_talento' => 'excluyente', 'vendedor_embajador' => 'excluyente', 'talento_embajador' => 'excluyente']);
        $this->assertTrue($s->aplicable('talento', ['vendedor']));
        $this->assertSame([], $s->otorgadosPrevios('embajador', 1, '2026-01-01', '2026-01-31'));
    }

    public function test_activo_todo_compatible_aplica(): void
    {
        $s = $this->svc(true);
        $this->assertTrue($s->aplicable('talento', ['vendedor']));
        $this->assertTrue($s->aplicable('embajador', ['vendedor', 'talento']));
    }

    /** @dataProvider combinaciones */
    public function test_excluyente_por_combinacion(string $clave, string $inc, string $otro): void
    {
        $s = $this->svc(true, [$clave => 'excluyente']);
        $this->assertSame('excluyente', $s->modo($inc, $otro));
        $this->assertSame('excluyente', $s->modo($otro, $inc));
        $this->assertFalse($s->aplicable($inc, [$otro]));
        $this->assertTrue($s->aplicable($inc, []));
    }

    public static function combinaciones(): array
    {
        return [
            'vendedor_talento' => ['vendedor_talento', 'talento', 'vendedor'],
            'vendedor_embajador' => ['vendedor_embajador', 'embajador', 'vendedor'],
            'talento_embajador' => ['talento_embajador', 'embajador', 'talento'],
        ];
    }

    public function test_excluyente_de_una_combinacion_no_afecta_a_otra(): void
    {
        $s = $this->svc(true, ['vendedor_talento' => 'excluyente']);
        $this->assertTrue($s->aplicable('embajador', ['vendedor']));
        $this->assertTrue($s->aplicable('embajador', ['talento']));
    }

    public function test_por_campana(): void
    {
        $s = $this->svc(true, ['vendedor_talento' => 'por_campana'], ['navidad']);
        $this->assertFalse($s->aplicable('talento', ['vendedor']));
        $this->assertFalse($s->aplicable('talento', ['vendedor'], 'otra'));
        $this->assertTrue($s->aplicable('talento', ['vendedor'], 'navidad'));
    }

    public function test_mismo_tipo_no_se_excluye_a_si_mismo(): void
    {
        $s = $this->svc(true, ['vendedor_talento' => 'excluyente']);
        $this->assertTrue($s->aplicable('talento', ['talento']));
    }
}
