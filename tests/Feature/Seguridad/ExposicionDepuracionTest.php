<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Route;
use Tests\CreatesApplication;

/**
 * Seguridad V1.65: producción declara APP_ENV=local, así que el candado «solo en local» de la ruta
 * que vaciaba la base no la protegía allá. La ruta ya no existe. Sin base de datos.
 */
class ExposicionDepuracionTest extends TestCase
{
    use CreatesApplication;

    public function test_ya_no_existe_la_ruta_que_vaciaba_la_base(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map->uri();

        $this->assertNotContains('reload_db', $uris->all());
        $this->assertFileDoesNotExist(base_path('routes/script_db.php'));
    }
}
