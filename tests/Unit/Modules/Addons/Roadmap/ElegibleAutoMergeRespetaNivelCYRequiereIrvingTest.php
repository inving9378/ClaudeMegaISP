<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.
                                 // (`tests/TestCase.php` corre `migrate:fresh` contra la BD
                                 // compartida de dev — inaceptable para un test de este carril).

/**
 * CANDADO DEL FIX #756 — `JarvisService::elegibleAutoMerge()` decidía la elegibilidad de
 * auto-merge SOLO mirando config/pausa/freno-humano/bucle/branch/frontera-dura-por-texto/diff,
 * pero NUNCA `$item->nivel_riesgo` ni `preguntas[].requiere_irving`. Evidencia real: el item #753
 * (nivel C, con una pregunta `requiere_irving=true`) fue marcado "elegible" y se encoló su
 * auto-merge, saltándose el parqueo que `IntegrarItemCommand::parquearEsperandoMerge()` ya le
 * había puesto — el merge no llegó a completarse esa vez por una causa AJENA, no porque el guard
 * lo hubiera detenido.
 *
 * Evaluar `elegibleAutoMerge()` de verdad requiere Laravel arrancado (config()/Log:: son fachadas,
 * y `RoadmapItem` es un Eloquent model) — este archivo es PHPUnit puro a propósito (mismo motivo
 * que `ElegibleAutoMergeRespetaBloqueoBucleTest`), así que se verifica por inspección de fuente:
 * que los dos guards existen y que van ANTES de cualquier chequeo basado en el diff (rutas
 * sensibles / migraciones), que es justo la garantía que pide el item: "SIEMPRE, sin importar
 * qué tan limpio esté el diff".
 */
class ElegibleAutoMergeRespetaNivelCYRequiereIrvingTest extends TestCase
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

    public function test_guard_nivel_riesgo_c_esta_presente(): void
    {
        $cuerpo = $this->cuerpoDeElegibleAutoMerge();

        $this->assertStringContainsString("nivel_riesgo === 'C'", $cuerpo,
            'Se quitó (o se reformuló irreconociblemente) el guard de `nivel_riesgo === \'C\'` de '
            . '`elegibleAutoMerge()`: sin él, un item de nivel C se puede auto-mergear igual que uno '
            . 'A/B, contradiciendo el propio comentario de `IntegrarItemCommand` ("nivel C: nunca '
            . 'auto-integra, solo Irving con botón/--force") y CLAUDE.md #507.');
    }

    public function test_guard_requiere_irving_esta_presente(): void
    {
        $cuerpo = $this->cuerpoDeElegibleAutoMerge();

        $this->assertStringContainsString('requiere_irving', $cuerpo,
            'Se quitó el guard de `preguntas[].requiere_irving` de `elegibleAutoMerge()`: sin él, un '
            . 'item con una pregunta escalada a Irving (aunque ya tenga `opcion_elegida`) se puede '
            . 'auto-mergear como si nunca se hubiera escalado — el bypass real detectado en #753.');
    }

    /**
     * Los dos guards nuevos deben evaluarse ANTES de que el diff entre en juego (rutas sensibles /
     * migraciones): si quedaran después, un item C con diff "limpio" pasaría de largo. El motivo
     * de colocarlos temprano es el mismo que el guard hermano de `bloqueado_por_bucle`: la
     * garantía tiene que ser incondicional, no depender de qué tan aseado esté el cambio.
     */
    public function test_guards_van_antes_del_chequeo_de_diff(): void
    {
        $cuerpo = $this->cuerpoDeElegibleAutoMerge();

        $posNivelC   = strpos($cuerpo, "nivel_riesgo === 'C'");
        $posIrving   = strpos($cuerpo, 'requiere_irving');
        $posDiff     = strpos($cuerpo, 'archivosDeRama');

        $this->assertNotFalse($posNivelC);
        $this->assertNotFalse($posIrving);
        $this->assertNotFalse($posDiff, 'No encontré el chequeo de diff (`archivosDeRama`) para comparar posiciones.');

        $this->assertLessThan($posDiff, $posNivelC,
            'El guard de `nivel_riesgo === \'C\'` debe ir ANTES del chequeo de diff: la garantía es '
            . '"SIEMPRE, sin importar qué tan limpio esté el diff".');
        $this->assertLessThan($posDiff, $posIrving,
            'El guard de `requiere_irving` debe ir ANTES del chequeo de diff: la garantía es '
            . '"SIEMPRE, sin importar qué tan limpio esté el diff".');
    }

    /**
     * El rechazo debe quedar auditado (brief del fix: "loggear el motivo del rechazo"), con un
     * canal distinguible del log de éxito (`jarvis-automerge`, que ya existe en `autoMergear()`).
     */
    public function test_guards_loggean_el_bloqueo_para_auditoria(): void
    {
        $cuerpo = $this->cuerpoDeElegibleAutoMerge();

        $this->assertStringContainsString('jarvis-automerge-bloqueado', $cuerpo,
            'El bloqueo por nivel C / requiere_irving debe quedar logueado (auditoría del bypass '
            . 'histórico #753) con una clave propia, distinta del log de éxito `jarvis-automerge`.');
    }
}
