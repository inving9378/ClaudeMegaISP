<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD ni git real.

/**
 * #9990004 (q2+q3 de #915) — detección de colisión de esquema por TABLA entre ramas en vuelo.
 *
 * POR QUÉ ES PURO. `detectarColisionesEnVuelo()` en sí mismo lee `roadmap_items` (BD compartida
 * con dev, ver `FrenoFueraDeLaBaseTest`/`PoolGuardCoherenceTest`) y corre `git` contra el
 * repositorio real. Igual que esos candados, este test prueba el NÚCLEO puro que se extrajo para
 * hacerlo posible: `tablasEnMigracion()` (parseo), `ganadorPerdedor()` (desempate) y
 * `decidirColisiones()` (pareo + pausa), los tres sin BD ni proceso `git`. El test de estrés (q3)
 * simula N ramas concurrentes con datos sintéticos y verifica que el resultado es determinístico y
 * nunca dispensa una colisión real.
 */
class ColisionPorTablaMigracionTest extends TestCase
{
    private RoadmapCircuitoService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new RoadmapCircuitoService();
    }

    // ── tablasEnMigracion() — parseo ────────────────────────────────────────────────────────

    public function test_detecta_schema_create(): void
    {
        $php = "Schema::create('auditoria_senales', function (Blueprint \$table) {\n\$table->id();\n});";
        $this->assertSame(['auditoria_senales'], $this->svc->tablasEnMigracion($php));
    }

    public function test_detecta_schema_table(): void
    {
        $php = "Schema::table('clients', function (Blueprint \$table) {\n\$table->string('foo');\n});";
        $this->assertSame(['clients'], $this->svc->tablasEnMigracion($php));
    }

    public function test_detecta_drop_if_exists_y_drop(): void
    {
        $this->assertSame(['foo'], $this->svc->tablasEnMigracion("Schema::dropIfExists('foo');"));
        $this->assertSame(['bar'], $this->svc->tablasEnMigracion("Schema::drop('bar');"));
    }

    public function test_detecta_rename_ambos_lados(): void
    {
        $php = "Schema::rename('old_name', 'new_name');";
        $this->assertSame(['old_name', 'new_name'], $this->svc->tablasEnMigracion($php));
    }

    public function test_soporta_comillas_dobles(): void
    {
        $php = 'Schema::table("plan_bundles", function (Blueprint $table) {});';
        $this->assertSame(['plan_bundles'], $this->svc->tablasEnMigracion($php));
    }

    public function test_migracion_con_up_y_down_no_duplica_ni_pierde_tablas(): void
    {
        $php = <<<'PHP'
        public function up(): void
        {
            Schema::create('reported_payments', function (Blueprint $table) {
                $table->id();
            });
        }

        public function down(): void
        {
            Schema::dropIfExists('reported_payments');
        }
        PHP;
        $this->assertSame(['reported_payments'], $this->svc->tablasEnMigracion($php));
    }

    public function test_migracion_con_varias_tablas_las_junta_todas(): void
    {
        $php = <<<'PHP'
        Schema::table('users', function (Blueprint $table) { $table->boolean('is_system')->default(false); });
        Schema::table('roles', function (Blueprint $table) { $table->string('color')->nullable(); });
        PHP;
        $this->assertEqualsCanonicalizing(['users', 'roles'], $this->svc->tablasEnMigracion($php));
    }

    public function test_migracion_sin_llamadas_schema_devuelve_vacio(): void
    {
        $this->assertSame([], $this->svc->tablasEnMigracion('<?php // migración vacía, solo comentarios'));
    }

    public function test_no_confunde_nombre_de_variable_con_nombre_de_tabla(): void
    {
        // El primer argumento de Schema::table SIEMPRE es el string literal; una variable ahí no
        // matchea el regex (a propósito: no hay forma segura de resolverla sin ejecutar el código).
        $php = 'Schema::table($tabla, function (Blueprint $table) {});';
        $this->assertSame([], $this->svc->tablasEnMigracion($php));
    }

    // ── ganadorPerdedor() — desempate determinístico ────────────────────────────────────────

    public function test_pierde_el_que_reclamo_mas_tarde(): void
    {
        [$ganador, $perdedor] = RoadmapCircuitoService::ganadorPerdedor(
            1, '2026-09-03 10:00:00',
            2, '2026-09-03 10:05:00', // reclamó 5 min después → pierde
        );
        $this->assertSame(1, $ganador);
        $this->assertSame(2, $perdedor);
    }

    public function test_empate_exacto_pierde_el_de_mayor_id(): void
    {
        [$ganador, $perdedor] = RoadmapCircuitoService::ganadorPerdedor(
            5, '2026-09-03 10:00:00',
            9, '2026-09-03 10:00:00',
        );
        $this->assertSame(5, $ganador);
        $this->assertSame(9, $perdedor);
    }

    public function test_ganador_perdedor_es_simetrico_sin_importar_el_orden_de_los_argumentos(): void
    {
        $r1 = RoadmapCircuitoService::ganadorPerdedor(3, '2026-09-03 10:00:00', 7, '2026-09-03 09:00:00');
        $r2 = RoadmapCircuitoService::ganadorPerdedor(7, '2026-09-03 09:00:00', 3, '2026-09-03 10:00:00');
        $this->assertSame($r1, $r2);
    }

    // ── decidirColisiones() — pareo + estrés con ramas sintéticas ───────────────────────────

    /**
     * El caso exacto que este item cierra: dos ramas tocan la MISMA tabla desde migraciones con
     * nombres de archivo DISTINTOS → el diff de archivos (footprint) no las ve comunes, pero la
     * colisión por tabla sí debe dispararse.
     */
    public function test_colision_por_tabla_con_archivos_de_nombre_distinto(): void
    {
        $rows = [
            ['id' => 1, 'updated_at' => '2026-09-03 10:00:00'],
            ['id' => 2, 'updated_at' => '2026-09-03 10:05:00'],
        ];
        $footprints = [
            1 => ['database/migrations/2026_09_03_100000_add_foo_to_clients.php'],
            2 => ['database/migrations/2026_09_03_100500_add_bar_to_clients.php'],
        ];
        $tablas = [
            1 => ['clients'],
            2 => ['clients'],
        ];

        $detectadas = $this->svc->decidirColisiones($rows, $footprints, $tablas);

        $this->assertCount(1, $detectadas);
        $this->assertSame(1, $detectadas[0]['ganador']);
        $this->assertSame(2, $detectadas[0]['perdedor']);
        $this->assertContains('tabla:clients', $detectadas[0]['comunes']);
    }

    public function test_sin_tabla_ni_archivo_en_comun_no_hay_colision(): void
    {
        $rows = [
            ['id' => 1, 'updated_at' => '2026-09-03 10:00:00'],
            ['id' => 2, 'updated_at' => '2026-09-03 10:05:00'],
        ];
        $footprints = [
            1 => ['database/migrations/2026_09_03_100000_add_foo_to_clients.php'],
            2 => ['database/migrations/2026_09_03_100500_add_bar_to_invoices.php'],
        ];
        $tablas = [
            1 => ['clients'],
            2 => ['invoices'],
        ];

        $this->assertSame([], $this->svc->decidirColisiones($rows, $footprints, $tablas));
    }

    public function test_footprint_desconocido_colisiona_aunque_las_tablas_sean_disjuntas(): void
    {
        $rows = [
            ['id' => 1, 'updated_at' => '2026-09-03 10:00:00'],
            ['id' => 2, 'updated_at' => '2026-09-03 10:05:00'],
        ];
        $footprints = [1 => [], 2 => []];
        $tablas     = [1 => ['clients'], 2 => ['invoices']]; // disjuntas...
        $desconocidos = [2 => true]; // ...pero el árbol en vivo del item 2 no se pudo leer

        $detectadas = $this->svc->decidirColisiones($rows, $footprints, $tablas, $desconocidos);

        $this->assertCount(1, $detectadas);
        $this->assertSame('(footprint en vivo desconocido)', $detectadas[0]['comunes'][0]);
    }

    /**
     * Test de estrés (q3): simula 12 ramas concurrentes con un mix de colisiones por tabla, por
     * archivo, y ninguna colisión — y confirma que el resultado es DETERMINÍSTICO (mismo input,
     * mismo output en repetidas corridas) y que ninguna colisión real se dispensa: para cada par de
     * ramas con al menos una tabla en común, siempre hay exactamente una decisión ganador/perdedor.
     */
    public function test_estres_doce_ramas_concurrentes_resultado_deterministico_y_sin_colisiones_dispensadas(): void
    {
        // Grupo A (1,2,3): las tres tocan 'clients' desde archivos distintos → 3 pares en colisión.
        // Grupo B (4,5): tocan 'invoices' vía Schema::table + Schema::rename → colisión.
        // Grupo C (6..10): cada una toca su propia tabla exclusiva → ninguna colisión entre ellas.
        // Item 11: footprint en vivo desconocido → colisiona con TODOS los demás.
        // Item 12: mismo archivo (no solo tabla) que el item 6 → colisión por archivo, no por tabla.
        $rows = [];
        $footprints = [];
        $tablas = [];
        for ($i = 1; $i <= 12; $i++) {
            $rows[] = ['id' => $i, 'updated_at' => sprintf('2026-09-03 10:%02d:00', $i)];
        }

        $footprints[1] = ['database/migrations/2026_09_03_100001_a.php'];
        $footprints[2] = ['database/migrations/2026_09_03_100002_b.php'];
        $footprints[3] = ['database/migrations/2026_09_03_100003_c.php'];
        $tablas[1] = ['clients'];
        $tablas[2] = ['clients'];
        $tablas[3] = ['clients'];

        $footprints[4] = ['database/migrations/2026_09_03_100004_d.php'];
        $footprints[5] = ['database/migrations/2026_09_03_100005_e.php'];
        $tablas[4] = ['invoices'];
        $tablas[5] = ['invoices_old', 'invoices']; // rename: toca ambos nombres

        for ($i = 6; $i <= 10; $i++) {
            $footprints[$i] = [sprintf('database/migrations/2026_09_03_1001%02d_solo%d.php', $i, $i)];
            $tablas[$i] = ["tabla_exclusiva_{$i}"];
        }

        $footprints[11] = [];
        $tablas[11] = ['tabla_exclusiva_6']; // aunque coincidiera, desconocido pesa más
        $desconocidos = [11 => true];

        $footprints[12] = $footprints[6]; // MISMO archivo que el item 6 (colisión por archivo)
        $tablas[12] = ['otra_tabla_distinta'];

        // Corre dos veces con el MISMO input: debe dar exactamente el mismo resultado (determinismo).
        $r1 = $this->svc->decidirColisiones($rows, $footprints, $tablas, $desconocidos);
        $r2 = $this->svc->decidirColisiones($rows, $footprints, $tablas, $desconocidos);
        $this->assertSame($r1, $r2, 'decidirColisiones() no es determinístico con el mismo input.');

        // Índice de pares detectados para las aserciones de abajo.
        $pares = [];
        foreach ($r1 as $d) {
            $par = $d['ganador'] < $d['perdedor'] ? "{$d['ganador']}-{$d['perdedor']}" : "{$d['perdedor']}-{$d['ganador']}";
            $pares[$par] = $d;
        }

        // Grupo A: los 3 pares (1,2)(1,3)(2,3) deben estar, todas por tabla 'clients'.
        foreach ([[1, 2], [1, 3], [2, 3]] as [$x, $y]) {
            $this->assertArrayHasKey("{$x}-{$y}", $pares, "Colisión por tabla no detectada entre {$x} y {$y}.");
            $this->assertContains('tabla:clients', $pares["{$x}-{$y}"]['comunes']);
        }

        // Grupo B: (4,5) en colisión por 'invoices' (vía el rename del 5).
        $this->assertArrayHasKey('4-5', $pares);
        $this->assertContains('tabla:invoices', $pares['4-5']['comunes']);

        // Grupo C: ninguna colisión entre 6..10 entre sí (tablas exclusivas y disjuntas).
        for ($x = 6; $x <= 10; $x++) {
            for ($y = $x + 1; $y <= 10; $y++) {
                $this->assertArrayNotHasKey("{$x}-{$y}", $pares, "Colisión falsa entre {$x} y {$y} (tablas disjuntas).");
            }
        }

        // Item 11 (desconocido): colisiona con TODOS los demás 11 items — nunca se dispensa.
        for ($otro = 1; $otro <= 10; $otro++) {
            $clave = "{$otro}-11";
            $this->assertArrayHasKey($clave, $pares, "El item 11 (footprint desconocido) debió colisionar con {$otro}.");
        }
        $this->assertArrayHasKey('11-12', $pares);

        // Item 12: colisiona con el 6 por ARCHIVO (mismo path), aunque las tablas sean distintas.
        $this->assertArrayHasKey('6-12', $pares);
        $this->assertContains('database/migrations/2026_09_03_100106_solo6.php', $pares['6-12']['comunes']);

        // El desempate siempre respeta ganadorPerdedor(): en cada par, pierde quien reclamó más
        // tarde (updated_at mayor, que aquí coincide con el id mayor por construcción del fixture).
        foreach ($pares as $par => $d) {
            $this->assertGreaterThan($d['ganador'], $d['perdedor'],
                "Par {$par}: el perdedor debería ser el id mayor (reclamó más tarde) en este fixture.");
        }
    }

    /**
     * Nadie puede colisionar consigo mismo, y cada par se evalúa una sola vez (sin duplicar el
     * mismo par en ambos sentidos).
     */
    public function test_no_hay_autocolision_ni_pares_duplicados(): void
    {
        $rows = [
            ['id' => 1, 'updated_at' => '2026-09-03 10:00:00'],
            ['id' => 2, 'updated_at' => '2026-09-03 10:01:00'],
        ];
        $footprints = [1 => ['x.php'], 2 => ['x.php']];
        $tablas = [1 => [], 2 => []];

        $detectadas = $this->svc->decidirColisiones($rows, $footprints, $tablas);

        $this->assertCount(1, $detectadas, 'Un solo par (1,2) debe producir una sola decisión.');
    }
}
