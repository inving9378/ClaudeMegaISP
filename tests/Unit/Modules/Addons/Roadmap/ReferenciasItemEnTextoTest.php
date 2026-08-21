<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Services\ThomasService;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.
                                 // (`tests/TestCase.php` corre `migrate:fresh` contra la BD
                                 // compartida de dev — inaceptable para un test de este carril).

/**
 * CANDADO DEL FIX #967 — «pregunta maestra contestada» ≠ «la acción física que implica ya ocurrió».
 *
 * Caso real #463↔#308: Irving eligió 9+ veces "mergear #308 a main" en la pregunta maestra de
 * #463, pero mergear es un botón manual suyo en la Torre — `ThomasService::evaluarYaDecidido()`
 * no lo verificaba, así que cada re-aprobación reabría el pool para descubrir el mismo bloqueo
 * ("#308 sigue sin mergear a main") otra vez, generando 22+ confirmaciones idénticas.
 *
 * `ThomasService::referenciasItemEnTexto()` es el núcleo puro (solo regex, sin BD) que extrae los
 * "#N" del texto de la pregunta maestra — se prueba aquí igual que `opcionElegidaEsEscalar()`
 * (candado del #893 hermano, ver `EvaluarYaDecididoEscalarTest`). La resolución de si esa
 * dependencia sigue sin resolver (nivel C + rama + sin merge_commit) vive en `evaluarYaDecidido()`
 * y se verifica por inspección de fuente, mismo patrón que el guard `requiere_sesion_supervisada`.
 */
class ReferenciasItemEnTextoTest extends TestCase
{
    /** Caso real #463: "depende de que #308 (registro module_contracts...) se mergee a main." */
    public function test_extrae_referencia_real_de_463(): void
    {
        $texto = '#463 esta BLOQUEADO desde 2026-07-14: depende de que #308 '
            . '(registro module_contracts/module_contract_consumers) se mergee a main.';

        $this->assertSame([463, 308], ThomasService::referenciasItemEnTexto($texto));
    }

    public function test_multiples_referencias_unicas_en_orden_de_aparicion(): void
    {
        $texto = 'Ver #100, luego #200, y otra vez #100.';

        $this->assertSame([100, 200], ThomasService::referenciasItemEnTexto($texto));
    }

    public function test_texto_sin_referencias_devuelve_vacio(): void
    {
        $this->assertSame([], ThomasService::referenciasItemEnTexto('Sin ninguna referencia aquí.'));
    }

    public function test_texto_vacio_no_revienta(): void
    {
        $this->assertSame([], ThomasService::referenciasItemEnTexto(''));
    }

    /**
     * El guard de dependencia física vive en `evaluarYaDecidido()`, ANTES del bucle que recorre
     * las preguntas — así aplica incluso cuando el brief está 100% contestado (que es justo el
     * caso del bucle #463↔#308: nada queda "sin responder", el problema es que la acción que la
     * respuesta implica no ocurrió). Se verifica por fuente (no por invocación) porque evaluarlo
     * de verdad requiere un `RoadmapItem` real con dependencias en BD — mismo patrón que el test
     * hermano de `requiere_sesion_supervisada` en `EvaluarYaDecididoEscalarTest`.
     */
    public function test_guard_de_dependencia_sin_merge_esta_antes_del_bucle_de_preguntas(): void
    {
        $f = dirname(__DIR__, 5) . '/app/Modules/Addons/Roadmap/Services/ThomasService.php';
        $this->assertFileExists($f);
        $src = file_get_contents($f);

        $posMetodo = strpos($src, 'function evaluarYaDecidido(');
        $this->assertNotFalse($posMetodo, 'No encontré `evaluarYaDecidido()` — ¿se renombró?');

        $posGuard = strpos($src, 'referenciasItemEnTexto', $posMetodo);
        $posBucle = strpos($src, 'foreach ($preguntas as $idx => $p)', $posMetodo);

        $this->assertNotFalse($posGuard, 'Se quitó el guard #967 (dependencia sin mergear) de '
            . '`evaluarYaDecidido()` — el bucle #463↔#308 vuelve a ser posible.');
        $this->assertNotFalse($posBucle);
        $this->assertLessThan($posBucle, $posGuard,
            'El guard #967 debe evaluarse ANTES del bucle de preguntas: el bucle #463↔#308 ocurre '
            . 'justo cuando el brief está 100% contestado (nada "sin responder"), así que un guard '
            . 'colocado después del bucle nunca se alcanzaría en ese caso.');
    }
}
