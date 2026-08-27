<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\DiagnosticoItemService;
use App\Modules\Addons\Roadmap\Services\TorreAutomationPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\CreatesApplication;

/**
 * #980 — DiagnosticoItemService: un test por causa, con items EN MEMORIA (nunca `->save()`),
 * verificando que la causa correcta se detecte y que las demás NO disparen en falso.
 *
 * DECISIÓN (registrada con `circuito:reportar --tipo=decision`, item #980): NO extiende
 * `Tests\TestCase` — su `setUp()` corre `migrate:fresh --seed` y `phpunit.xml` apunta
 * `DB_DATABASE` a `megaisp` (la BD compartida de dev, NO una BD de test separada) — correrlo
 * aquí borraría el trabajo en vivo de Irving y de las demás terminales del circuito. Este archivo
 * usa `CreatesApplication` directo (bootea el contenedor de Laravel para `app()`/`config()`, que
 * `DiagnosticoItemService` necesita) SIN `RefreshDatabase` ni migración alguna. Los servicios que
 * toca (`TorreAutomationPolicy::politicaBase()`, `RoadmapCircuitoService::slotLibre()`) solo LEEN
 * — coherente con que el propio servicio es solo-lectura.
 */
class DiagnosticoItemServiceTest extends BaseTestCase
{
    use CreatesApplication;

    private function item(array $attrs = []): RoadmapItem
    {
        $i = new RoadmapItem();
        foreach ($attrs as $k => $v) {
            $i->{$k} = $v;
        }

        return $i;
    }

    public function test_espera_resolucion_para_item_en_requiere_irving(): void
    {
        $i = $this->item([
            'id'                => 999901,
            'estado_aprobacion' => 'requiere_irving',
            'revisado_at'       => Carbon::now()->subDays(5),
        ]);

        $d = DiagnosticoItemService::para($i);

        $this->assertSame('espera_resolucion', $d['causa']);
        $this->assertSame('resolver', $d['accion']);
        $this->assertStringContainsString('5 días', $d['explicacion']);
    }

    public function test_aprobado_no_despachable_cuando_motivo_es_generico(): void
    {
        // `$esDespachable=false` forzado a propósito: evita la consulta a `despachable()` y aísla
        // la sola lectura de `motivoNoDespachable()` sobre un item en memoria sin flags de freno.
        $i = $this->item([
            'id'                => 999902,
            'estado_aprobacion' => 'aprobado_revisor',
            'nivel_riesgo'      => 'C',
            'status'            => 'pending',
        ]);

        $d = DiagnosticoItemService::para($i, false);

        $this->assertSame('aprobado_no_despachable', $d['causa']);
        $this->assertSame('dar_override', $d['accion']);
        $this->assertStringContainsString('nivel C', $d['explicacion']);
    }

    public function test_aprobado_con_freno_humano_no_es_aprobado_no_despachable(): void
    {
        // Un `aprobado_*` bloqueado por un freno HUMANO (no por nivel/política) no tiene causa 1:1
        // en la tabla de #877: cae a `no_determinado` con la explicación real de
        // `motivoNoDespachable()` — decisión registrada del item #980 (ver docblock de la clase).
        $i = $this->item([
            'id'                 => 999903,
            'estado_aprobacion'  => 'aprobado_irving',
            'nivel_riesgo'       => 'B',
            'status'             => 'pending',
            'excluir_pool_automatico' => true,
            'motivo_bloqueo'     => 'prueba',
        ]);

        $d = DiagnosticoItemService::para($i, false);

        $this->assertSame('no_determinado', $d['causa']);
        $this->assertNotSame('aprobado_no_despachable', $d['causa']);
    }

    public function test_reclamo_huerfano_cuando_el_slot_esta_libre(): void
    {
        $i = $this->item([
            'id'                 => 999904,
            'estado_aprobacion'  => 'en_progreso',
            'en_desarrollo_humano' => false,
            'worker_sid'         => 'wt-999',
            'claimed_at'         => Carbon::now()->subMinutes(30),
        ]);

        $d = DiagnosticoItemService::para($i);

        $this->assertSame('reclamo_huerfano', $d['causa']);
        $this->assertStringContainsString('wt-999', $d['explicacion']);
    }

    public function test_en_progreso_reciente_no_es_reclamo_huerfano(): void
    {
        // Dentro de la ventana de gracia (#334): el scheduler pudo reclamarlo apenas, antes de que
        // vuelta.sh tomara su flock — no debe leerse como huérfano.
        $i = $this->item([
            'id'                   => 999905,
            'estado_aprobacion'    => 'en_progreso',
            'en_desarrollo_humano' => false,
            'worker_sid'           => 'wt-999',
            'claimed_at'           => Carbon::now()->subSeconds(10),
        ]);

        $d = DiagnosticoItemService::para($i);

        $this->assertNotSame('reclamo_huerfano', $d['causa']);
    }

    public function test_tope_duro_cuando_toca_una_frontera_dura(): void
    {
        $i = $this->item([
            'id'                => 999906,
            'estado_aprobacion' => 'pendiente_revision',
            'title'             => 'Rotar api key y contraseña de un servicio externo',
            'description'       => 'cambiar un secreto',
            'frontera_valvula'  => null,
        ]);

        $d = DiagnosticoItemService::para($i);

        $this->assertSame('tope_duro', $d['causa']);
        $this->assertNull($d['accion']);
    }

    /**
     * ⚠️ LA SEMÁNTICA DE ESTE CASO SE INVIRTIÓ EL 2026-08-27 (#648, decisión de Irving).
     *
     * ANTES: `frontera_valvula==='mencion'` hacía DESAPARECER la frontera dura para ese item — y
     * para siempre, porque el sello queda guardado en la fila. O sea: el único control por
     * contenido que no depende de la autodeclaración de un modelo tenía un interruptor de apagado,
     * y el interruptor lo accionaba un modelo. Ese camino dejó exento al #182 mientras implementaba
     * control de acceso real con Spatie, sobre una pregunta mal formada.
     *
     * AHORA (modo `ablandar`, el de fábrica): una «mención» baja la frontera a «requiere Irving»,
     * NUNCA a «pasa». El modelo puede seguir diciendo «sólo lo menciona» y el efecto es que Irving
     * LO VE, no que el control se apague solo. Sigue siendo `tope_duro`.
     *
     * El comportamiento anterior no se borró: es el modo `apagar`, seleccionable desde la pestaña
     * «Configuración» → Fronteras → Válvula. Este test fija el DE FÁBRICA, que es el que corre si
     * nadie toca nada — incluida la lectura fallida de la config, que cae a `ablandar` a propósito.
     */
    public function test_una_mencion_ablanda_la_frontera_pero_no_la_apaga(): void
    {
        $i = $this->item([
            'id'                => 999907,
            'estado_aprobacion' => 'pendiente_revision',
            'title'             => 'Rotar api key y contraseña de un servicio externo',
            'description'       => 'cambiar un secreto',
            'frontera_valvula'  => 'mencion',
        ]);

        $d = DiagnosticoItemService::para($i);

        $this->assertSame('tope_duro', $d['causa'],
            'Una «mención» volvió a APAGAR la frontera dura. El modo de fábrica es `ablandar`: '
            . 'baja a «requiere Irving», nunca a «pasa».');
    }

    public function test_ninguna_causa_cubierta_devuelve_no_determinado_honesto(): void
    {
        $i = $this->item([
            'id'                => 999908,
            'estado_aprobacion' => 'aprobado_irving',
            'nivel_riesgo'      => 'B',
            'status'            => 'pending',
            'title'             => 'Item normal sin bloqueos',
        ]);

        $d = DiagnosticoItemService::para($i, true);

        $this->assertSame('no_determinado', $d['causa']);
        $this->assertNotEmpty($d['explicacion']);
    }

    public function test_orden_de_evaluacion_espera_resolucion_gana_sobre_tope_duro(): void
    {
        // `espera_resolucion` es la causa MÁS específica de la tabla de #877 y va primero: un item
        // en `requiere_irving` que ADEMÁS toca una frontera dura debe reportar espera_resolucion.
        $i = $this->item([
            'id'                => 999909,
            'estado_aprobacion' => 'requiere_irving',
            'revisado_at'       => Carbon::now()->subDay(),
            'title'             => 'Rotar api key y contraseña de un servicio externo',
            'description'       => 'cambiar un secreto',
        ]);

        $d = DiagnosticoItemService::para($i);

        $this->assertSame('espera_resolucion', $d['causa']);
    }

    public function test_procedencia_siempre_viene_poblada(): void
    {
        foreach ([
            ['estado_aprobacion' => 'requiere_irving', 'revisado_at' => Carbon::now()],
            ['estado_aprobacion' => 'pendiente_revision'],
        ] as $i => $attrs) {
            $item = $this->item(array_merge(['id' => 999910 + $i], $attrs));
            $d    = DiagnosticoItemService::para($item, true);
            $this->assertNotEmpty($d['procedencia'], 'procedencia vacía para causa ' . $d['causa']);
        }
    }
}
