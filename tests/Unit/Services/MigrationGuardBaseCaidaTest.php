<?php

namespace Tests\Unit\Services;

use App\Services\MigrationGuardService;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;
use RuntimeException;
use Tests\CreatesApplication;

/**
 * EL ESCENARIO DEL 2026-08-25, FIJADO EN UNA PRUEBA.
 *
 * `migrate:fresh` borró las 500 tablas de dev y el `migrate` de vuelta no reconstruyó nada: el
 * guardrail no podía leer la tabla `migrations` —la que ese mismo comando acababa de borrar— y
 * bloqueó. La base quedó en 0 tablas en lugar de 500. El freno no evitó el daño: impidió la
 * reparación.
 *
 * Lo que estas pruebas fijan es el comportamiento correcto en ambas formas del mismo escenario:
 * sin poder leer el estado aplicado, el guardrail RESPONDE (no lanza) y PERMITE reconstruir.
 *
 * No extiende `Tests\TestCase` ni toca la base: tiene que poder correr precisamente cuando la
 * base no está.
 */
class MigrationGuardBaseCaidaTest extends BaseTestCase
{
    use CreatesApplication;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Forma 1: la tabla `migrations` no existe (base recién vaciada). */
    private function migratorSinTablaMigrations(): Migrator
    {
        $repository = Mockery::mock(MigrationRepositoryInterface::class);
        $repository->shouldReceive('repositoryExists')->andReturn(false);
        $repository->shouldReceive('getRan')
            ->andThrow(new RuntimeException("Table 'megaisp.migrations' doesn't exist"));

        $migrator = Mockery::mock(Migrator::class);
        $migrator->shouldReceive('paths')->andReturn([]);
        $migrator->shouldReceive('getMigrationFiles')->andReturn([]);
        $migrator->shouldReceive('getRepository')->andReturn($repository);

        return $migrator;
    }

    /** Forma 2: la conexión entera no responde (MySQL caído). */
    private function migratorConConexionCaida(): Migrator
    {
        $repository = Mockery::mock(MigrationRepositoryInterface::class);
        $repository->shouldReceive('repositoryExists')
            ->andThrow(new RuntimeException('SQLSTATE[HY000] [2002] Connection refused'));

        $migrator = Mockery::mock(Migrator::class);
        $migrator->shouldReceive('paths')->andReturn([]);
        $migrator->shouldReceive('getMigrationFiles')->andReturn([]);
        $migrator->shouldReceive('getRepository')->andReturn($repository);

        return $migrator;
    }

    public function test_sin_tabla_migrations_el_guardrail_responde_y_permite_reconstruir(): void
    {
        $service = new MigrationGuardService($this->migratorSinTablaMigrations());

        $this->assertFalse($service->estadoAplicadoLegible());
        $this->assertSame([], $service->checkPending(), 'debe permitir el migrate de reconstrucción');
        $this->assertSame([], $service->checkDestructive(), 'debe permitir el migrate de reconstrucción');
    }

    public function test_con_la_conexion_caida_el_guardrail_responde_sin_excepcion(): void
    {
        $service = new MigrationGuardService($this->migratorConConexionCaida());

        // Lo que se fija aquí es que RESPONDE. Un guardrail que lanza deja la decisión en manos
        // de quien atrape la excepción, y eso ya no es un guardrail.
        $this->assertFalse($service->estadoAplicadoLegible());
        $this->assertIsArray($service->checkPending());
        $this->assertIsArray($service->checkDestructive());
        $this->assertSame([], $service->checkPending());
    }

    /** Con el estado aplicado legible, el guardrail sigue haciendo su trabajo de siempre. */
    public function test_con_la_base_sana_el_guardrail_sigue_evaluando(): void
    {
        $repository = Mockery::mock(MigrationRepositoryInterface::class);
        $repository->shouldReceive('repositoryExists')->andReturn(true);
        $repository->shouldReceive('getRan')->andReturn([]);

        $migrator = Mockery::mock(Migrator::class);
        $migrator->shouldReceive('paths')->andReturn([]);
        $migrator->shouldReceive('getMigrationFiles')->andReturn([
            '2026_01_01_000000_inventada' => '/tmp/no-existe-2026_01_01_000000_inventada.php',
        ]);
        $migrator->shouldReceive('getRepository')->andReturn($repository);

        $service = new MigrationGuardService($migrator);

        $this->assertTrue($service->estadoAplicadoLegible());
        // El archivo no está trackeado en git → sigue habiendo violación. El guardrail no se
        // ablandó: sólo dejó de confundir "no hay nada que proteger" con "no sé".
        $this->assertNotSame([], $service->checkPending());
    }
}
