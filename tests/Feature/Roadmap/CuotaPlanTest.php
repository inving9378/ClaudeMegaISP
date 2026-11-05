<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Services\CuotaPlanService;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/**
 * #1068 — lectura de la cuota del plan que deja la línea de estado de Claude Code. Sin base de
 * datos: solo un archivo temporal con la forma que escribe deploy/circuito/cuota-plan-statusline.sh.
 */
class CuotaPlanTest extends TestCase
{
    use CreatesApplication;

    private string $archivo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->archivo = sys_get_temp_dir() . '/cuota-plan-prueba-' . uniqid() . '.json';
        config(['circuito.cuota_plan.archivo' => $this->archivo]);
    }

    protected function tearDown(): void
    {
        @unlink($this->archivo);
        parent::tearDown();
    }

    public function test_sin_archivo_no_hay_medicion(): void
    {
        $this->assertNull(app(CuotaPlanService::class)->leer());
    }

    public function test_lee_porcentajes_reinicio_y_edad(): void
    {
        $this->escribir(now()->subMinutes(5)->timestamp, [
            'five_hour' => ['used_percentage' => 34.6, 'resets_at' => now()->addHours(2)->timestamp],
            'seven_day' => ['used_percentage' => 61.2, 'resets_at' => now()->addDays(3)->timestamp],
        ]);
        $c = app(CuotaPlanService::class)->leer();

        $this->assertSame(34, $c['cinco_horas']['pct']);
        $this->assertSame(61, $c['semana']['pct']);
        $this->assertStringContainsString(' a las ', $c['cinco_horas']['reinicia_texto']);
        $this->assertFalse($c['vieja']);
    }

    public function test_ventana_reiniciada_y_dato_viejo(): void
    {
        $this->escribir(now()->subHours(6)->timestamp, [
            'five_hour' => ['used_percentage' => 95, 'resets_at' => now()->subHour()->timestamp],
        ]);
        $c = app(CuotaPlanService::class)->leer();

        $this->assertTrue($c['cinco_horas']['reiniciada']);
        $this->assertNull($c['cinco_horas']['pct']);
        $this->assertNull($c['semana']);
        $this->assertTrue($c['vieja']);
    }

    private function escribir(int $medido, array $limites): void
    {
        file_put_contents($this->archivo, json_encode(['medido_at' => $medido, 'rate_limits' => $limites]));
    }
}
