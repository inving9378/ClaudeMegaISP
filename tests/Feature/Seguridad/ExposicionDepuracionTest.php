<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Route;
use Tests\CreatesApplication;

/**
 * Seguridad V1.64.1: producción declara APP_ENV=local, así que lo que se protegía «solo fuera de
 * local» quedaba abierto allá. La ruta que vaciaba la base ya no existe, y las herramientas de
 * depuración nacen apagadas en un servidor que recibe versiones aunque APP_DEBUG siga en true.
 * Sin base de datos.
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

    public function test_ignition_no_ejecuta_soluciones_si_el_env_no_lo_pide(): void
    {
        $config = $this->conEnv(['IGNITION_ENABLE_RUNNABLE_SOLUTIONS' => null], fn () => require base_path('config/ignition.php'));

        $this->assertFalse($config['enable_runnable_solutions']);
    }

    public function test_la_barra_de_depuracion_nace_apagada_en_produccion_y_sin_cambios_en_dev(): void
    {
        $prod = $this->conEnv(['DEBUGBAR_ENABLED' => null, 'GITHUB_UPDATES_ENABLED' => 'true'], fn () => require base_path('config/debugbar.php'));
        $dev = $this->conEnv(['DEBUGBAR_ENABLED' => null, 'GITHUB_UPDATES_ENABLED' => null], fn () => require base_path('config/debugbar.php'));
        $explicita = $this->conEnv(['DEBUGBAR_ENABLED' => 'true', 'GITHUB_UPDATES_ENABLED' => 'true'], fn () => require base_path('config/debugbar.php'));

        $this->assertFalse($prod['enabled']);
        $this->assertNull($dev['enabled'], 'En dev sigue la regla de antes: la decide APP_DEBUG.');
        $this->assertTrue($explicita['enabled']);
    }

    /** Corre $fn con esas variables de entorno (null = ausente) y deja las originales como estaban. */
    private function conEnv(array $vars, callable $fn): mixed
    {
        $repo = Env::getRepository();
        $antes = [];
        foreach ($vars as $nombre => $valor) {
            $antes[$nombre] = $repo->get($nombre);
            $valor === null ? $repo->clear($nombre) : $repo->set($nombre, $valor);
        }

        try {
            return $fn();
        } finally {
            foreach ($antes as $nombre => $valor) {
                $valor === null ? $repo->clear($nombre) : $repo->set($nombre, $valor);
            }
        }
    }
}
