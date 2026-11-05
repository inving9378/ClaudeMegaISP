<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Las pruebas que RECREAN la base (RefreshDatabase / DatabaseMigrations) van a su propia base:
        // `migrate:fresh` borraba los datos de catálogo de `megaisp_test`, de los que dependen las
        // pruebas con DatabaseTransactions (#1054). Si esa base no existe, fallan sin tocar nada.
        $recreaLaBase = array_intersect(
            [\Illuminate\Foundation\Testing\RefreshDatabase::class, \Illuminate\Foundation\Testing\DatabaseMigrations::class],
            class_uses_recursive($this)
        ) !== [];

        // Primera revisión, en cuanto se lee la configuración y antes de registrar los
        // providers: ninguno alcanza a abrir una conexión a una base que no es de pruebas.
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($recreaLaBase) {
            if ($recreaLaBase) {
                $conexion = (string) $app['config']->get('database.default');
                $app['config']->set("database.connections.{$conexion}.database", GuardBaseDePruebas::BASE_QUE_SE_RECREA);
            }
            GuardBaseDePruebas::verificarApp($app);
        });

        $app->make(Kernel::class)->bootstrap();

        // EL CANDADO DE LA BASE DE PRUEBAS. Va aquí, y no en Tests\TestCase, porque éste es el
        // ÚNICO punto por el que pasan las dos familias de tests del repo: las que extienden
        // Tests\TestCase (cuyo setUp corre `migrate:fresh --seed`) y las que extienden la
        // TestCase de Illuminate usando este trait (varias de ésas usan RefreshDatabase, que
        // hace exactamente lo mismo). Ponerlo en la clase base habría dejado fuera a la mitad.
        //
        // Segunda revisión, DESPUÉS del bootstrap y ANTES de devolver la app: cubre un provider
        // que cambie la conexión al arrancar, y todavía no corrió `setUpTraits()`, que es donde
        // RefreshDatabase dispara el migrate. Ver tests/GuardBaseDePruebas.php.
        GuardBaseDePruebas::verificarApp($app);

        return $app;
    }
}
