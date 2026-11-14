<?php

namespace Tests\Feature\Marketing;

use App\Modules\Addons\Marketing\Support\EquipoMarketing;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base TestCase for Marketing tests.
 * Uses DatabaseTransactions instead of migrate:fresh — safe to run against the
 * development DB since every test rolls back its changes automatically.
 */
abstract class MarketingTestCase extends BaseTestCase
{
    use CreatesApplication, DatabaseTransactions;

    /**
     * Acceso por ROL (decisión de Irving, 2026-11-14; config `marketing.roles_con_acceso`). Las pruebas de las Fases 0–2
     * que usan estos escenarios prueban el camino OPCIONAL por equipo (filas en marketing_empresa_usuario) y la
     * separación entre empresas: sus usuarios «de otra empresa» o «sin fila» llevan super-administrator solo para tener
     * permisos, y con el acceso por rol entrarían por ese rol. Por eso, en ellas, el acceso por rol se apaga. El acceso
     * por rol (la regla vigente) se prueba en AccesoPorRolesTest y en las pruebas reescritas de super-administrador.
     */
    private const ESCENARIOS_POR_EQUIPO = [
        \Tests\Feature\Marketing\Fase0\Qa\Concerns\EscenarioEmpresas::class,
        \Tests\Feature\Marketing\Fase0\Backend\Concerns\EscenarioBackend::class,
        \Tests\Feature\Marketing\Fase1\Qa\Concerns\EscenarioFase1::class,
        \Tests\Feature\Marketing\Fase1\Backend\Concerns\EscenarioBackendFase1::class,
        \Tests\Feature\Marketing\Fase2\Qa\Concerns\EscenarioFase2::class,
        \Tests\Feature\Marketing\Fase2\Backend\Concerns\EscenarioBackendFase2::class,
    ];

    /** Pruebas de pantallas que, sin usar esos escenarios, también prueban el camino por equipo (mismo motivo). */
    private const CLASES_POR_EQUIPO = [
        \Tests\Feature\Marketing\Fase1\Frontend\PantallasMarketingTest::class,
        \Tests\Feature\Marketing\Fase2\Frontend\PantallasCampanas360Test::class,
    ];

    /** Roles con acceso de la regla vigente, para las pruebas que la vuelven a encender. */
    protected const ROLES_CON_ACCESO = ['super-administrator', 'Administrador', 'DESARROLLADOR', 'Mostrador'];

    /**
     * Marketing Fase 1 · §2.6.2: sin la marca `marketing_settings.equipo_fijado_at` el middleware del módulo niega
     * a todos. Las pruebas parten del estado «equipo ya fijado» (como queda la base tras la migración 2026_11_13_100200);
     * la marca se crea dentro de la transacción de cada prueba y se deshace al terminar. Las pruebas del candado la
     * quitan con EquipoMarketing::quitarMarca().
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (array_intersect(self::ESCENARIOS_POR_EQUIPO, class_uses_recursive($this)) !== []
            || in_array(static::class, self::CLASES_POR_EQUIPO, true)) {
            config(['marketing.roles_con_acceso' => []]);
        }

        if (Schema::hasTable('marketing_settings') && ! EquipoMarketing::hayMarca()) {
            EquipoMarketing::marcar();
        }
    }
}
