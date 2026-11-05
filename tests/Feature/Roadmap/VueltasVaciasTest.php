<?php

namespace Tests\Feature\Roadmap;

use App\Models\User;
use App\Modules\Addons\Roadmap\Console\DestrabarCommand;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\JarvisService;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use App\Modules\Addons\Roadmap\Services\TorreAutomationPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\CreatesApplication;

/**
 * #1078 — una vuelta que termina sin cambio ya no devuelve el item a la cola al minuto siguiente,
 * y el pool espera 1 h / 4 h / 24 h antes de volver a darlo si algo automático lo re-aprueba.
 */
class VueltasVaciasTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::fake();   // ningún gancho del modelo debe llegar a un proveedor de IA
    }

    private function item(array $attrs = []): RoadmapItem
    {
        $i = RoadmapItem::create($attrs + [
            'title' => 'Item de ejemplo ' . uniqid(),
            'status' => 'pending',
            'estado_aprobacion' => 'en_progreso',
            'worker_sid' => 'wt-prueba',
            'estado_previo_claim' => 'aprobado_irving',
        ]);
        // Columnas que pone el circuito (no están en $fillable): directo, como las escribe el reclamo.
        $i->forceFill(array_intersect_key($attrs + ['estado_previo_claim' => 'aprobado_irving'],
            array_flip(['estado_previo_claim', 'vueltas_vacias', 'ultima_vuelta_vacia_at'])))->saveQuietly();

        return $i->fresh();
    }

    private function elegible(RoadmapItem $i): bool
    {
        return RoadmapItem::query()->whereKey($i->id)
            ->where(fn ($q) => RoadmapItem::sqlElegibleParaPool($q))->exists();
    }

    public function test_primera_vuelta_vacia_vuelve_a_la_cola_tras_la_espera_creciente(): void
    {
        $i = $this->item();

        Artisan::call('circuito:cerrar-vuelta', ['item' => $i->id, '--sid' => 'wt-prueba', '--rc' => '0', '--inicio' => time() - 60]);

        $f = $i->fresh();
        $this->assertSame('aprobado_irving', $f->estado_aprobacion);
        $this->assertSame(1, (int) $f->vueltas_vacias);
        $this->assertNull($f->worker_sid);
        $this->assertSame('vuelta_vacia_reintento', collect($f->log)->last()['evento']);
        $this->assertFalse($this->elegible($f), 'la espera creciente (1 h) no se salta');
    }

    public function test_segunda_vuelta_vacia_pasa_a_esperan_tu_decision_y_no_se_reintenta(): void
    {
        $i = $this->item(['vueltas_vacias' => 1, 'ultima_vuelta_vacia_at' => now()->subHours(2)]);

        Artisan::call('circuito:cerrar-vuelta', ['item' => $i->id, '--sid' => 'wt-prueba', '--rc' => '0', '--inicio' => time() - 60]);

        $f = $i->fresh();
        $this->assertSame('requiere_irving', $f->estado_aprobacion);
        $this->assertSame('decision', $f->motivo_espera);
        $this->assertSame(2, (int) $f->vueltas_vacias);
        $this->assertSame('vuelta_sin_cambio', collect($f->log)->last()['evento']);
    }

    public function test_fin_anormal_u_otra_terminal_no_se_tocan(): void
    {
        $a = $this->item();
        Artisan::call('circuito:cerrar-vuelta', ['item' => $a->id, '--sid' => 'wt-prueba', '--rc' => '124']);
        $this->assertSame('en_progreso', $a->fresh()->estado_aprobacion);

        $b = $this->item();
        Artisan::call('circuito:cerrar-vuelta', ['item' => $b->id, '--sid' => 'wt-otra', '--rc' => '0']);
        $this->assertSame('en_progreso', $b->fresh()->estado_aprobacion);
    }

    public function test_item_descompuesto_queda_como_paraguas(): void
    {
        $padre = $this->item();
        RoadmapItem::create([
            'title' => 'Sub-item de ejemplo ' . uniqid(), 'status' => 'pending',
            'estado_aprobacion' => 'aprobado_revisor', 'origen_item_id' => $padre->id,
        ]);

        Artisan::call('circuito:cerrar-vuelta', ['item' => $padre->id, '--sid' => 'wt-prueba', '--rc' => '0']);

        $f = $padre->fresh();
        $this->assertSame('aprobado_irving', $f->estado_aprobacion);
        $this->assertTrue((bool) $f->excluir_pool_automatico);
        $this->assertSame(0, (int) $f->vueltas_vacias);
    }

    public function test_espera_creciente_en_el_pool(): void
    {
        $i = $this->item(['estado_aprobacion' => 'aprobado_revisor', 'worker_sid' => null]);
        $this->assertTrue($this->elegible($i));

        $casos = [
            [1, 30, false], [1, 61, true],
            [2, 61, false], [2, 241, true],
            [3, 241, false], [5, 23 * 60, false], [5, 24 * 60 + 1, true],
        ];
        foreach ($casos as [$n, $minutos, $esperado]) {
            $i->forceFill(['vueltas_vacias' => $n, 'ultima_vuelta_vacia_at' => now()->subMinutes($minutos)])->saveQuietly();
            $this->assertSame($esperado, $this->elegible($i), "{$n} vueltas vacías hace {$minutos} min");
            $this->assertSame($esperado, $i->fresh()->esperaVueltasVaciasHasta() === null);
        }
    }

    public function test_solo_una_persona_reinicia_la_espera(): void
    {
        $i = $this->item(['estado_aprobacion' => 'requiere_irving', 'worker_sid' => null]);
        $i->forceFill(['vueltas_vacias' => 2, 'ultima_vuelta_vacia_at' => now()])->saveQuietly();

        // Un proceso del circuito (sin sesión) la re-aprueba: la espera sigue.
        $i->fresh()->update(['estado_aprobacion' => 'aprobado_revisor']);
        $this->assertSame(2, (int) $i->fresh()->vueltas_vacias);

        // Una persona la re-aprueba: se reinicia.
        $i->fresh()->update(['estado_aprobacion' => 'requiere_irving']);
        $this->actingAs(User::query()->firstOrFail());
        $i->fresh()->update(['estado_aprobacion' => 'aprobado_irving']);
        $this->assertSame(0, (int) $i->fresh()->vueltas_vacias);
        $this->assertTrue($this->elegible($i->fresh()));
    }

    public function test_lo_que_salio_vacio_dos_veces_no_lo_reaprueba_ningun_carril_automatico(): void
    {
        // #1000 (31-oct): pasó a «Esperan tu decisión» por la 2.ª vuelta vacía y el carril mecánico
        // lo devolvió a la cola cinco minutos después.
        $i = $this->item(['title' => 'Limpiar código muerto de ejemplo', 'nivel_riesgo' => 'B',
            'estado_aprobacion' => 'requiere_irving', 'worker_sid' => null]);
        $i->forceFill(['vueltas_vacias' => 2, 'ultima_vuelta_vacia_at' => now()->subMinutes(5)])->saveQuietly();
        $policy = app(TorreAutomationPolicy::class);

        $this->assertSame('requiere_irving', $policy->estadoInicial($i->fresh(), 'jarvis.mecanico'));
        $r = app(JarvisService::class)->aprobarMecanico($i->fresh());
        $this->assertFalse($r['aprobado']);
        $this->assertStringContainsString('[VUELTAS VACÍAS]', $r['motivo']);
        $this->assertSame('requiere_irving', $i->fresh()->estado_aprobacion);

        // Control: con una sola vuelta vacía la misma política sí lo deja pasar.
        $i->forceFill(['vueltas_vacias' => 1])->saveQuietly();
        $this->assertSame('aprobado_revisor', $policy->estadoInicial($i->fresh(), 'jarvis.mecanico'));
    }

    public function test_el_destrabe_con_columnas_recortadas_tambien_respeta_la_regla_d(): void
    {
        // #1000 (31-oct, 23:12): `circuito:destrabar-bandeja` trae los items con columnas recortadas;
        // sin `vueltas_vacias` el contador llegaba en 0 y el carril mecánico lo re-aprobaba cada 6 min.
        $i = $this->item(['title' => 'Limpiar código muerto de ejemplo', 'nivel_riesgo' => 'B',
            'estado_aprobacion' => 'requiere_irving', 'worker_sid' => null]);
        $i->forceFill(['vueltas_vacias' => 2, 'ultima_vuelta_vacia_at' => now()->subMinutes(5)])->saveQuietly();

        $columnas = (new \ReflectionClassConstant(DestrabarCommand::class, 'COLUMNAS_NECESARIAS'))->getValue();
        $sinContador = array_values(array_diff($columnas, ['vueltas_vacias']));

        foreach ([$columnas, $sinContador] as $select) {
            $r = app(JarvisService::class)->aprobarMecanico(RoadmapItem::select($select)->find($i->id));
            $this->assertFalse($r['aprobado']);
            $this->assertStringContainsString('[VUELTAS VACÍAS]', $r['motivo']);
        }
        $this->assertSame('requiere_irving', $i->fresh()->estado_aprobacion);
    }

    public function test_cierre_con_codigo_sin_merge_va_a_la_fila_del_merge(): void
    {
        // Rama de prueba con un commit propio sobre main (mismo árbol; solo el ref, sin checkout).
        $git  = 'git -C ' . escapeshellarg(base_path());
        $rama = 'prueba/cierre-sin-merge-' . uniqid();
        $sha  = trim((string) shell_exec("{$git} commit-tree 'main^{tree}' -p main -m prueba 2>/dev/null"));
        $this->assertNotSame('', $sha);
        shell_exec("{$git} update-ref " . escapeshellarg("refs/heads/{$rama}") . ' ' . escapeshellarg($sha));

        try {
            $svc = app(RoadmapCircuitoService::class);
            $svc->setModoIntegracion('auto-merge');
            $i = $this->item(['branch' => $rama, 'nivel_riesgo' => 'B']);
            $i->estado_aprobacion = 'completado';   // la terminal lo cierra sin integrarlo
            $i->save();
            $this->assertTrue($i->fresh()->excluir_pool_automatico, 'el candado de cierre lo aparta');

            Artisan::call('circuito:cerrar-vuelta', ['item' => $i->id, '--sid' => 'wt-prueba', '--rc' => '0', '--inicio' => time() - 60]);

            $this->assertContains($i->id, array_map(fn ($e) => (int) $e['item_id'], $svc->mergeQueue()), 'nivel B → fila del merge');
            $this->assertContains('cierre_sin_merge_a_integracion', collect($i->fresh()->log)->pluck('evento')->all());
        } finally {
            shell_exec("{$git} update-ref -d " . escapeshellarg("refs/heads/{$rama}"));
        }
    }
}
