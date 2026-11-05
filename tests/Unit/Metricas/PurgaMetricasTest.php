<?php

namespace Tests\Unit\Metricas;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/** Item #988: qué se conserva y qué se borra del monitoreo de dedicados, y que promedie antes de borrar. */
class PurgaMetricasTest extends TablasTemporalesTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-04 12:07:30');
        $this->temporal('cliente_metricas_5min', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('servicio_id');
            $t->unsignedBigInteger('cliente_id');
            $t->dateTime('ts');
            $t->unsignedTinyInteger('muestras');
            $t->unsignedBigInteger('bajada_bps')->nullable();
            $t->unsignedBigInteger('bajada_bps_max')->nullable();
            $t->unsignedBigInteger('subida_bps')->nullable();
            $t->unsignedBigInteger('subida_bps_max')->nullable();
            $t->decimal('lat_avg_ms', 9, 3)->nullable();
            $t->decimal('lat_max_ms', 9, 3)->nullable();
            $t->decimal('loss_pct', 5, 2)->nullable();
            $t->decimal('loss_max_pct', 5, 2)->nullable();
            $t->unsignedTinyInteger('minutos_en_linea')->default(0);
            $t->unsignedTinyInteger('minutos_degradado')->default(0);
            $t->unsignedTinyInteger('minutos_sin_respuesta')->default(0);
            $t->string('origen', 16)->default('mikrotik');
            $t->unique(['servicio_id', 'ts']);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_detalle_30_dias_promedios_13_meses_y_latidos_con_su_retencion(): void
    {
        $this->lectura('2026-10-05 12:00:00'); // 30 días y unos minutos: vencida
        $this->lectura('2026-10-05 12:10:00'); // menos de 30 días: se queda
        $this->ping('2026-10-05 12:00:00');
        $this->ping('2026-10-05 12:10:00');
        $this->promedio('2025-10-01 00:00:00'); // más de 13 meses
        $this->promedio('2025-12-01 00:00:00'); // menos de 13 meses
        $this->latido('2026-10-01 00:00:00');
        $this->latido('2026-10-20 00:00:00');
        config(['metricas.retencion.latidos_dias' => 20]);

        $this->artisan('metricas:purgar')->assertExitCode(0);

        $this->assertSame(['2026-10-05 12:10:00'], DB::table('cliente_metricas')->pluck('ts')->all());
        $this->assertSame(['2026-10-05 12:10:00'], DB::table('cliente_metricas_ping')->pluck('ts')->all());
        $this->assertFalse(DB::table('cliente_metricas_5min')->where('ts', '2025-10-01 00:00:00')->exists());
        $this->assertTrue(DB::table('cliente_metricas_5min')->where('ts', '2025-12-01 00:00:00')->exists());
        $this->assertSame(['2026-10-20 00:00:00'], DB::table('metricas_colector_latidos')->pluck('ts')->all());
    }

    public function test_promedia_antes_de_borrar_lo_vencido(): void
    {
        $this->lectura('2026-10-05 12:00:00', 1_000, 0.2);
        $this->lectura('2026-10-05 12:01:00', 3_000, 0.4);

        $this->artisan('metricas:purgar')->assertExitCode(0);

        $this->assertSame(0, DB::table('cliente_metricas')->count());
        $b = DB::table('cliente_metricas_5min')->where('ts', '2026-10-05 12:00:00')->first();
        $this->assertSame(2, (int) $b->muestras);
        $this->assertSame(2_000, (int) $b->bajada_bps);
        $this->assertSame(3_000, (int) $b->bajada_bps_max);
        $this->assertEqualsWithDelta(0.3, (float) $b->lat_avg_ms, 0.001);
        $this->assertSame(2, (int) $b->minutos_en_linea);
    }

    public function test_no_promedia_el_bloque_que_sigue_abierto_y_correr_dos_veces_da_lo_mismo(): void
    {
        $this->lectura('2026-11-04 12:00:00', 1_000);
        $this->lectura('2026-11-04 12:04:00', 3_000);
        $this->lectura('2026-11-04 12:05:00', 9_000); // bloque 12:05, abierto a las 12:07

        $this->artisan('metricas:purgar')->assertExitCode(0);
        $this->artisan('metricas:purgar')->assertExitCode(0);

        $this->assertSame(['2026-11-04 12:00:00'], DB::table('cliente_metricas_5min')->pluck('ts')->all());
        $this->assertSame(2_000, (int) DB::table('cliente_metricas_5min')->value('bajada_bps'));
        $this->assertSame(3, DB::table('cliente_metricas')->count(), 'el detalle reciente no se toca');
    }

    public function test_el_dry_run_no_promedia_ni_borra(): void
    {
        $this->lectura('2026-10-01 12:00:00');

        $this->artisan('metricas:purgar', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(1, DB::table('cliente_metricas')->count());
        $this->assertSame(0, DB::table('cliente_metricas_5min')->count());
    }

    private function lectura(string $ts, int $bajada = 1_000, float $lat = 0.3): void
    {
        DB::table('cliente_metricas')->insert(['servicio_id' => 1, 'cliente_id' => 1, 'ts' => $ts, 'bajada_bps' => $bajada, 'subida_bps' => 100,
            'estado' => 'ok', 'lat_avg_ms' => $lat, 'lat_max_ms' => $lat, 'loss_pct' => 0, 'estado_ping' => 'en_linea']);
    }

    private function ping(string $ts): void
    {
        DB::table('cliente_metricas_ping')->insert(['servicio_id' => 1, 'cliente_id' => 1, 'ip' => '192.0.2.10', 'ts' => $ts, 'enviados' => 3, 'recibidos' => 3, 'estado' => 'responde']);
    }

    private function promedio(string $ts): void
    {
        DB::table('cliente_metricas_5min')->insert(['servicio_id' => 2, 'cliente_id' => 2, 'ts' => $ts, 'muestras' => 5]);
    }

    private function latido(string $ts): void
    {
        DB::table('metricas_colector_latidos')->insert(['ts' => $ts, 'driver' => 'simulado']);
    }
}
