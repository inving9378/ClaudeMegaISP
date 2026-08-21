<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Services\ThomasService;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.
                                 // (`tests/TestCase.php` corre `migrate:fresh` contra la BD
                                 // compartida de dev — inaceptable para un test de este carril).

/**
 * CANDADO DEL FIX #893 — «brief 100% contestado» ≠ «decisión a favor del pool».
 *
 * `ThomasService::evaluarYaDecidido()` aprobaba items cuya pregunta MAESTRA estaba "contestada"
 * aunque la opción elegida fuera literalmente «Opción 3: Escalar a Irving…» (la recomendada del
 * Revisor cuando no puede resolver algo solo). Eso causó las 12 escalaciones idénticas de #186
 * entre 2026-07-15 y 2026-08-20: el carril veía «nada pendiente» y despachaba el item, que volvía
 * a escalar por la misma razón en la siguiente pasada.
 *
 * `ThomasService::opcionElegidaEsEscalar()` es el núcleo puro de ese fix (solo arrays, sin BD ni
 * contenedor) — se prueba aquí en los 4 casos que importan. El guard hermano
 * (`requiere_sesion_supervisada`) SÍ necesita un `RoadmapItem` de verdad para evaluarse dentro de
 * `evaluarYaDecidido()`, así que aquí se verifica por inspección de fuente (mismo patrón que
 * `DestrabeNoRecibeAutoAprobadosTest`), no por invocación.
 */
class EvaluarYaDecididoEscalarTest extends TestCase
{
    private function pregunta(?string $opcionElegida, array $opciones): array
    {
        return ['id' => 'q1', 'opcion_elegida' => $opcionElegida, 'opciones' => $opciones];
    }

    private function opcion(string $texto): array
    {
        return ['clave' => \App\Modules\Addons\Roadmap\Models\RoadmapItem::claveOpcion($texto), 'texto' => $texto];
    }

    /** Caso 1/4 — opción elegida = "escalar a Irving" (el bug real de #186) → true. */
    public function test_opcion_elegida_escalar_a_irving_se_detecta(): void
    {
        $autorizar = $this->opcion('Opción 1: Autorizar en DEV — RECOMENDADA');
        $escalar   = $this->opcion('Opción 3: Escalar a Irving por ser cambio de infraestructura VoIP');

        $p = $this->pregunta($escalar['clave'], [$autorizar, $escalar]);

        $this->assertTrue(ThomasService::opcionElegidaEsEscalar($p));
    }

    /** Caso 2/4 — opción elegida autoriza ejecución directa (NO menciona "escalar") → false. */
    public function test_opcion_elegida_que_autoriza_no_se_confunde_con_escalar(): void
    {
        $autorizar = $this->opcion('Opción 1: Autorizar en DEV con bind 127.0.0.1 — RECOMENDADA');
        $escalar   = $this->opcion('Opción 3: Escalar a Irving por ser cambio de infraestructura VoIP');

        $p = $this->pregunta($autorizar['clave'], [$autorizar, $escalar]);

        $this->assertFalse(ThomasService::opcionElegidaEsEscalar($p));
    }

    /** Caso 3/4 — sin opción elegida (pregunta sin contestar): no es "escalar", es "sin responder". */
    public function test_sin_opcion_elegida_no_es_escalar(): void
    {
        $autorizar = $this->opcion('Opción 1: Autorizar en DEV');
        $escalar   = $this->opcion('Opción 3: Escalar a Irving');

        $p = $this->pregunta(null, [$autorizar, $escalar]);

        $this->assertFalse(ThomasService::opcionElegidaEsEscalar($p));
    }

    /**
     * Caso 4/4 — mencionar "escalar a Irving" en una opción que NO es la elegida no debe
     * disparar el guard (falso positivo real visto en #463 q4: "Rollback + escalar a Irving"
     * como plan de contingencia secundario, sin ser la decisión tomada).
     */
    public function test_mencionar_escalar_en_opcion_no_elegida_no_dispara(): void
    {
        $autorizar         = $this->opcion('Opción 1: Autorizar y ejecutar');
        $rollbackYEscalar  = $this->opcion('Opción 2: Rollback + escalar a Irving si algo falla');

        $p = $this->pregunta($autorizar['clave'], [$autorizar, $rollbackYEscalar]);

        $this->assertFalse(ThomasService::opcionElegidaEsEscalar($p));
    }

    /** Clave inexistente entre las opciones (dato corrupto/legacy): no revienta, responde false. */
    public function test_clave_elegida_sin_opcion_correspondiente_no_revienta(): void
    {
        $autorizar = $this->opcion('Opción 1: Autorizar');
        $p = $this->pregunta('clave-que-no-existe', [$autorizar]);

        $this->assertFalse(ThomasService::opcionElegidaEsEscalar($p));
    }

    /**
     * El guard hermano de #893, `requiere_sesion_supervisada`, sigue en su sitio dentro de
     * `evaluarYaDecidido()` — ANTES de leer el brief, igual que `tieneFrenoHumano()`. Se verifica
     * por fuente (no por invocación) porque evaluarlo de verdad requiere un `RoadmapItem` con
     * `ThomasService` completo (`RoadmapCircuitoService::isPaused()` toca la BD compartida).
     */
    public function test_requiere_sesion_supervisada_frena_antes_del_brief(): void
    {
        $f   = dirname(__DIR__, 5) . '/app/Modules/Addons/Roadmap/Services/ThomasService.php';
        $this->assertFileExists($f);
        $src = file_get_contents($f);

        $pos = strpos($src, 'function evaluarYaDecidido(');
        $this->assertNotFalse($pos, 'No encontré `evaluarYaDecidido()` — ¿se renombró?');

        $cuerpo = substr($src, $pos, (int) strpos($src, 'public const APROBADOR_YA_DECIDIDO') - $pos);

        $this->assertStringContainsString('requiere_sesion_supervisada', $cuerpo,
            'Se quitó (o se movió fuera de `evaluarYaDecidido`) el guard de `requiere_sesion_supervisada`. '
            . 'Sin él, el carril "ya decidido" vuelve a ignorar el flag que Irving fija explícitamente '
            . 'para decir "esto no se auto-despacha, necesito estar presente".');
    }
}
