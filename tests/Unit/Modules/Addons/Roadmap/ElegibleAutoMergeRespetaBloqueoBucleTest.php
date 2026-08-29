<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.
                                 // (`tests/TestCase.php` corre `migrate:fresh` contra la BD
                                 // compartida de dev — inaceptable para un test de este carril).

/**
 * CANDADO DEL FIX #637 — `JarvisService::elegibleAutoMerge()` no respetaba `bloqueado_por_bucle`:
 * un item con conflicto real de merge (caso real #57) escalaba, `RoadmapItem::saving()` revertía
 * el estado a `aprobado_irving` por venir de un humano, y en el siguiente tick del scheduler
 * `elegibleAutoMerge()` lo volvía a ver "elegible" (seguía con `branch` sin `merge_commit`) y
 * reencolaba el mismo merge fallido — 7+ intentos idénticos en menos de 1 hora, sin que el propio
 * anti-bucle (que SÍ alcanzaba a marcar `bloqueado_por_bucle=true` tras 3 escalaciones) lograra
 * frenarlo por esta vía.
 *
 * Evaluar `elegibleAutoMerge()` de verdad requiere un `RoadmapItem` con `JarvisService` completo
 * (`RoadmapCircuitoService::isPaused()` toca la BD compartida), así que aquí se verifica por
 * inspección de fuente que el guard sigue en su lugar — mismo patrón que
 * `BloqueoBucleVigenteTest::test_guard_bloqueado_por_bucle_frena_antes_del_resto_del_brief` y
 * `EvaluarYaDecididoEscalarTest::test_requiere_sesion_supervisada_frena_antes_del_brief`.
 */
class ElegibleAutoMergeRespetaBloqueoBucleTest extends TestCase
{
    private function cuerpoDeElegibleAutoMerge(): string
    {
        $f = dirname(__DIR__, 5) . '/app/Modules/Addons/Roadmap/Services/JarvisService.php';
        $this->assertFileExists($f);
        $src = file_get_contents($f);

        $pos = strpos($src, 'function elegibleAutoMerge(');
        $this->assertNotFalse($pos, 'No encontré `elegibleAutoMerge()` — ¿se renombró?');

        $fin = strpos($src, 'function autoMergear(', $pos);
        $this->assertNotFalse($fin, 'No encontré el fin de `elegibleAutoMerge()` (`autoMergear()` que le sigue).');

        return substr($src, $pos, $fin - $pos);
    }

    public function test_guard_bloqueado_por_bucle_esta_presente(): void
    {
        $cuerpo = $this->cuerpoDeElegibleAutoMerge();

        $this->assertStringContainsString('bloqueado_por_bucle', $cuerpo,
            'Se quitó el guard de `bloqueado_por_bucle` de `elegibleAutoMerge()`: sin él, un item '
            . 'sellado por el anti-bucle (3+ escalaciones idénticas) vuelve a verse "elegible" en '
            . 'el siguiente tick y el auto-merge reintenta el mismo merge fallido indefinidamente '
            . '(caso real #57, 7+ intentos en <1h).');
    }

    /**
     * El guard tiene que estar ANTES de los chequeos de `branch`/`merge_commit`/frontera dura:
     * si quedara después, un item bloqueado con branch vacío devolvería "no tiene rama" en vez
     * de "bloqueado por anti-bucle" — el motivo importa para quien lea el log, y colocarlo antes
     * es lo que garantiza que SIEMPRE se evalúa (los checks de branch hacen `return` temprano).
     */
    public function test_guard_bloqueado_por_bucle_va_antes_del_check_de_branch(): void
    {
        $cuerpo = $this->cuerpoDeElegibleAutoMerge();

        $posBloqueo = strpos($cuerpo, 'bloqueado_por_bucle');
        $posBranch  = strpos($cuerpo, "empty(\$item->branch)");

        $this->assertNotFalse($posBloqueo);
        $this->assertNotFalse($posBranch);
        $this->assertLessThan($posBranch, $posBloqueo,
            'El guard de `bloqueado_por_bucle` debe evaluarse ANTES del check de `branch` vacío, '
            . 'para que un item bloqueado siempre reporte el motivo correcto sin importar su estado '
            . 'de branch/merge_commit.');
    }
}
