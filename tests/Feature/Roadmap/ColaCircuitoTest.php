<?php

namespace Tests\Feature\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\ColaCircuitoService;
use App\Modules\Addons\Roadmap\Services\RitmoCircuitoService;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\CreatesApplication;

/**
 * Limpieza de la Hoja de ruta — una sola definición de la cola (tomable / bloqueado con causa / en
 * curso), el orden de toma, el ritmo según la cuota del plan y el cierre justificado sin merge.
 */
class ColaCircuitoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private string $cuota;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::fake();
        Cache::forget('circuito.cola_foto');
        Cache::forget('circuito.que_sigue');
        $this->cuota = sys_get_temp_dir() . '/cuota-cola-prueba-' . uniqid() . '.json';
        config(['circuito.cuota_plan.archivo' => $this->cuota, 'circuito.cuota_plan.logs_vueltas' => '']);
    }

    protected function tearDown(): void
    {
        @unlink($this->cuota);
        parent::tearDown();
    }

    private function item(array $campos = []): RoadmapItem
    {
        $i = RoadmapItem::create(['title' => 'Pendiente de ejemplo ' . uniqid(), 'status' => 'pending',
            'estado_aprobacion' => 'aprobado_irving', 'priority' => 'media', 'nivel_riesgo' => 'A', 'modulo' => 'Modulo de ejemplo']);
        if ($campos) {
            DB::table('roadmap_items')->where('id', $i->id)->update($campos);
        }

        return $i->fresh();
    }

    public function test_causas_salen_de_las_reglas_del_despacho(): void
    {
        $svc = app(ColaCircuitoService::class);

        $base     = $this->item();
        $espera   = $this->item(['depende_de' => json_encode([$base->id])]);
        $merge    = $this->item(['esperando_merge_irving' => 1, 'nivel_riesgo' => 'C']);
        $apartado = $this->item(['excluir_pool_automatico' => 1]);
        $decision = $this->item(['estado_aprobacion' => 'requiere_irving', 'motivo_espera' => 'decision']);
        $paraguas = $this->item();
        $this->item(['origen_item_id' => $paraguas->id]);

        $this->assertSame('dependencia', $svc->causa($espera));
        $this->assertSame('espera_merge', $svc->causa($merge));
        $this->assertSame('apartado', $svc->causa($apartado));
        $this->assertSame('decision', $svc->causa($decision));
        $this->assertSame('paraguas', $svc->causa($paraguas->fresh()));
    }

    public function test_cierre_justificado_sin_merge_libera_a_sus_dependientes(): void
    {
        $cerrado = $this->item(['estado_aprobacion' => 'completado', 'sin_merge_esperado' => 1,
            'cierre_sin_codigo_motivo' => 'Sustituido por otra pantalla ya integrada.']);
        $espera = $this->item(['depende_de' => json_encode([$cerrado->id])]);

        $this->assertNull(app(RoadmapCircuitoService::class)->esperandoDependencias($espera->id));

        $sinMotivo = $this->item(['estado_aprobacion' => 'completado']);
        $otro = $this->item(['depende_de' => json_encode([$sinMotivo->id])]);
        $this->assertNotNull(app(RoadmapCircuitoService::class)->esperandoDependencias($otro->id), 'completado sin merge ni motivo sigue frenando');
    }

    public function test_lo_que_pide_a_irving_cuenta_las_secciones_que_esperan_por_posicion(): void
    {
        // Una fase con 3 secciones: la 2 espera a la 1 y la 3 espera a la 2. La 1 pide un insumo a
        // Irving, así que libera a las otras dos aunque ninguna la nombre en `depende_de`.
        $fase = $this->item();
        $s1 = $this->item(['origen_item_id' => $fase->id, 'position' => 1, 'estado_aprobacion' => 'requiere_irving',
            'motivo_espera' => 'adjunto_faltante', 'nivel_riesgo' => 'C']);
        $this->item(['origen_item_id' => $fase->id, 'position' => 2, 'descomposicion' => json_encode(['depende_de' => [1]])]);
        $this->item(['origen_item_id' => $fase->id, 'position' => 3, 'descomposicion' => json_encode(['depende_de' => [2]])]);

        $pide = collect(app(ColaCircuitoService::class)->foto(true)['requiere_irving'])->firstWhere('id', $s1->id);

        $this->assertNotNull($pide, 'la sección que pide el insumo aparece en la lista de Irving');
        // Las dos secciones que esperan por posición, más la fase (paraguas) que espera a sus partes.
        $this->assertSame(3, $pide['destraba']);
    }

    public function test_orden_urgentes_luego_los_que_desbloquean_luego_nivel(): void
    {
        $c        = $this->item(['nivel_riesgo' => 'C']);
        $a        = $this->item(['nivel_riesgo' => 'A']);
        $libera   = $this->item(['nivel_riesgo' => 'B']);
        $this->item(['depende_de' => json_encode([$libera->id]), 'estado_aprobacion' => 'requiere_irving']);
        $urgente  = $this->item(['nivel_riesgo' => 'C', 'urgente' => 1]);

        $orden = RoadmapItem::query()->whereIn('id', [$c->id, $a->id, $libera->id, $urgente->id])->ordenCola()->pluck('id')->all();

        $this->assertSame([$urgente->id, $libera->id, $a->id, $c->id], $orden);
    }

    public function test_ritmo_segun_la_cuota_semanal(): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'circuito_paralelismo'], ['value' => '6']);
        $ritmo = app(RitmoCircuitoService::class);

        $sinDato = $ritmo->limite();
        $this->assertSame(4, $sinDato['max']);
        $this->assertSame('sin_medicion', $sinDato['motivo']);

        $this->escribirCuota(30);
        $this->assertSame(6, $ritmo->limite()['max']);

        $this->escribirCuota(85);
        $alta = $ritmo->limite();
        $this->assertSame(0, $alta['max']);
        $this->assertSame('cuota_alta', $alta['motivo']);

        // El aviso llega a la bandeja una sola vez y se cierra solo al bajar la cuota.
        $ritmo->evaluar();
        $ritmo->evaluar();
        $avisos = fn () => RoadmapItem::where('title', 'like', RitmoCircuitoService::AVISO_TITULO . '%')
            ->where('estado_aprobacion', 'requiere_irving')->count();
        $this->assertSame(1, $avisos());
        $this->escribirCuota(40);
        $ritmo->evaluar();
        $this->assertSame(0, $avisos());
    }

    private function escribirCuota(float $semana): void
    {
        file_put_contents($this->cuota, json_encode(['medido_at' => time(), 'rate_limits' => [
            'five_hour' => ['used_percentage' => 10, 'resets_at' => time() + 3600],
            'seven_day' => ['used_percentage' => $semana, 'resets_at' => time() + 86400 * 3],
        ]]));
    }
}
