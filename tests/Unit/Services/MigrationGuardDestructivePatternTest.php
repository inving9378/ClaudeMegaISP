<?php

namespace Tests\Unit\Services;

use App\Services\MigrationGuardService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use ReflectionMethod;
use Tests\CreatesApplication;

/**
 * Item #1018 — un test por causa (patrón destructivo) + bordes, contra la regex real de
 * MigrationGuardService::destructiveMatchesIn(). Mismo criterio que #980
 * (DiagnosticoItemServiceTest): NO extiende Tests\TestCase (su setUp() corre
 * `migrate:fresh --seed` contra la BD compartida de dev) — aquí ni siquiera hace falta,
 * el método bajo prueba es puro (lee un archivo temporal, sin BD ni git).
 */
class MigrationGuardDestructivePatternTest extends BaseTestCase
{
    use CreatesApplication;

    private function matchesFor(string $migrationBody): array
    {
        $path = tempnam(sys_get_temp_dir(), 'mig_guard_test_');
        file_put_contents($path, $migrationBody);

        $service = app(MigrationGuardService::class);
        $method = new ReflectionMethod(MigrationGuardService::class, 'destructiveMatchesIn');
        $method->setAccessible(true);

        try {
            return $method->invoke($service, $path);
        } finally {
            unlink($path);
        }
    }

    public function test_detecta_drop_column(): void
    {
        $matches = $this->matchesFor("Schema::table('clients', function (Blueprint \$t) {\n    \$t->dropColumn('legacy_field');\n});");

        $this->assertCount(1, $matches);
        $this->assertSame(2, $matches[0]['linea']);
    }

    public function test_detecta_schema_drop(): void
    {
        $matches = $this->matchesFor("Schema::drop('tabla_vieja');");

        $this->assertCount(1, $matches);
    }

    public function test_detecta_schema_drop_if_exists(): void
    {
        $matches = $this->matchesFor("Schema::dropIfExists('tabla_vieja');");

        $this->assertCount(1, $matches);
    }

    public function test_schema_drop_no_duplica_sobre_drop_if_exists(): void
    {
        // Schema::drop( con look-ahead negativo no debe matchear también dropIfExists en la misma línea.
        $matches = $this->matchesFor("Schema::dropIfExists('tabla_vieja');");

        $this->assertCount(1, $matches, 'dropIfExists no debe contarse dos veces (una por cada patrón)');
    }

    public function test_detecta_rename_column(): void
    {
        $matches = $this->matchesFor("\$t->renameColumn('old_name', 'new_name');");

        $this->assertCount(1, $matches);
    }

    public function test_detecta_truncate_metodo(): void
    {
        $matches = $this->matchesFor("DB::table('cache')->truncate();");

        $this->assertCount(1, $matches);
    }

    public function test_detecta_truncate_sql_crudo(): void
    {
        $matches = $this->matchesFor("DB::statement('TRUNCATE TABLE cache');");

        $this->assertCount(1, $matches);
    }

    public function test_detecta_change_como_sospechoso(): void
    {
        $matches = $this->matchesFor("\$t->string('name', 50)->change();");

        $this->assertCount(1, $matches);
    }

    public function test_migracion_expansiva_no_dispara_nada(): void
    {
        $body = "Schema::table('clients', function (Blueprint \$t) {\n"
            . "    \$t->string('nuevo_campo')->nullable();\n"
            . "});\n";

        $matches = $this->matchesFor($body);

        $this->assertSame([], $matches);
    }

    public function test_dropcolumns_plural_tambien_se_detecta(): void
    {
        $matches = $this->matchesFor("\$t->dropColumns(['a', 'b']);");

        $this->assertCount(1, $matches);
    }

    /**
     * Item #1020 — regresión encontrada al implementar #1020: toda migración `create_table`
     * estándar de Laravel trae `Schema::dropIfExists()` en down() (reversa lo que up() creó,
     * no pierde datos de nadie) y el guard la marcaba como destructiva, bloqueando el `migrate`
     * de cualquier migración nueva que creara una tabla. El guard es sobre up() ("disciplina
     * expansiva"); down() queda fuera del escaneo.
     */
    public function test_drop_if_exists_en_down_no_dispara_nada(): void
    {
        $body = "public function up(): void\n{\n"
            . "    Schema::create('tabla_nueva', function (Blueprint \$t) {\n"
            . "        \$t->id();\n"
            . "    });\n"
            . "}\n\n"
            . "public function down(): void\n{\n"
            . "    Schema::dropIfExists('tabla_nueva');\n"
            . "}\n";

        $matches = $this->matchesFor($body);

        $this->assertSame([], $matches);
    }

    public function test_operacion_destructiva_en_up_si_se_detecta_aunque_haya_down(): void
    {
        $body = "public function up(): void\n{\n"
            . "    Schema::table('clients', function (Blueprint \$t) {\n"
            . "        \$t->dropColumn('legacy_field');\n"
            . "    });\n"
            . "}\n\n"
            . "public function down(): void\n{\n"
            . "    Schema::table('clients', function (Blueprint \$t) {\n"
            . "        \$t->string('legacy_field')->nullable();\n"
            . "    });\n"
            . "}\n";

        $matches = $this->matchesFor($body);

        $this->assertCount(1, $matches);
        $this->assertSame(4, $matches[0]['linea']);
    }
}
