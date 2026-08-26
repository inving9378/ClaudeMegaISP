<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

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

        $app->make(Kernel::class)->bootstrap();

        // EL CANDADO DE LA BASE DE PRUEBAS. Va aquí, y no en Tests\TestCase, porque éste es el
        // ÚNICO punto por el que pasan las dos familias de tests del repo: las que extienden
        // Tests\TestCase (cuyo setUp corre `migrate:fresh --seed`) y las que extienden la
        // TestCase de Illuminate usando este trait (varias de ésas usan RefreshDatabase, que
        // hace exactamente lo mismo). Ponerlo en la clase base habría dejado fuera a la mitad.
        //
        // Y va DESPUÉS del bootstrap y ANTES de devolver la app: aquí el nombre de la base ya es
        // el definitivo, y todavía no corrió `setUpTraits()`, que es donde RefreshDatabase
        // dispara el migrate. Ver tests/GuardBaseDePruebas.php.
        GuardBaseDePruebas::verificarApp($app);

        return $app;
    }
}
