<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Services\JarvisService;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.
                                 // (`tests/TestCase.php` corre `migrate:fresh` contra la BD
                                 // compartida de dev — inaceptable para un test de este carril).

/**
 * CANDADO DEL FIX #710 — «bloqueado_por_bucle» retenía a Irving, pero `jarvis-ya-decidido` lo
 * desbloqueaba solo con ver el brief contestado, sin chequear si la contradicción que causó la
 * escalación seguía vigente. Bug real en #626: un agente escala, Thomas marca
 * `bloqueado_por_bucle=true` (misma causa 3+ veces seguidas), y ~1 minuto después
 * `jarvis-ya-decidido` lo revertía a `aprobado_revisor` porque q1..q4 tenían `opcion_elegida` —
 * sin notar que "contestado" era justo la causa repetida, no una respuesta nueva. El scheduler
 * re-despachaba, otro worker re-investigaba, encontraba EXACTAMENTE el mismo hallazgo y volvía a
 * escalar: 9 vueltas entre 2026-08-28 15:13 y 16:59 (wt-4, wt-2, wt-3, wt-6, wt-1 x2), mismo
 * `escalaciones_fingerprint.count=9`.
 *
 * `JarvisService::bloqueoBucleSigueVigente()` es el núcleo puro del fix (solo compara dos
 * strings, sin BD ni contenedor) — se prueba aquí. El guard que lo consume dentro de
 * `evaluarYaDecidido()` SÍ necesita un `RoadmapItem` de verdad para evaluarse completo (mismo
 * patrón que `EvaluarYaDecididoEscalarTest`), así que aquí se verifica por inspección de fuente
 * que sigue en su lugar, ANTES del resto del brief.
 */
class BloqueoBucleVigenteTest extends TestCase
{
    /** Caso 1/4 — mismo fingerprint sellado que el actual: la causa no cambió → SIGUE vigente. */
    public function test_mismo_fingerprint_sigue_vigente(): void
    {
        $fp = str_repeat('a', 40);

        $this->assertTrue(JarvisService::bloqueoBucleSigueVigente($fp, $fp));
    }

    /** Caso 2/4 — fingerprint distinto (algo material cambió: rama/opción/nivel/preguntas) → YA NO vigente. */
    public function test_fingerprint_distinto_ya_no_vigente(): void
    {
        $sellado = str_repeat('a', 40);
        $actual  = str_repeat('b', 40);

        $this->assertFalse(JarvisService::bloqueoBucleSigueVigente($sellado, $actual));
    }

    /** Caso 3/4 — sin fingerprint sellado (dato legacy/corrupto, nunca se guardó uno): no se puede
     *  afirmar que es la misma causa → no vigente por esta vía (falla al lado de "evalúa normal",
     *  no al lado de "bloquea para siempre por un dato que nunca existió"). */
    public function test_sin_fingerprint_sellado_no_vigente(): void
    {
        $this->assertFalse(JarvisService::bloqueoBucleSigueVigente(null, str_repeat('a', 40)));
    }

    /** Caso 4/4 — cadenas vacías (dato corrupto) no se tratan como "iguales por vacío" salvo que
     *  literalmente ambas lo sean; el guard es una comparación estricta de strings, no especial. */
    public function test_ambos_vacios_se_consideran_iguales_por_comparacion_estricta(): void
    {
        $this->assertTrue(JarvisService::bloqueoBucleSigueVigente('', ''));
    }

    /**
     * El guard de #710 dentro de `evaluarYaDecidido()` sigue en su lugar: ANTES del resto del
     * brief (frontera dura / negocio / preguntas), igual que `tieneFrenoHumano()` y
     * `requiere_sesion_supervisada`. Se verifica por fuente (no por invocación) porque evaluarlo
     * de verdad requiere un `RoadmapItem` con `JarvisService` completo
     * (`RoadmapCircuitoService::isPaused()` toca la BD compartida).
     */
    public function test_guard_bloqueado_por_bucle_frena_antes_del_resto_del_brief(): void
    {
        $f = dirname(__DIR__, 5) . '/app/Modules/Addons/Roadmap/Services/JarvisService.php';
        $this->assertFileExists($f);
        $src = file_get_contents($f);

        $pos = strpos($src, 'function evaluarYaDecidido(');
        $this->assertNotFalse($pos, 'No encontré `evaluarYaDecidido()` — ¿se renombró?');

        $cuerpo = substr($src, $pos, (int) strpos($src, 'public const APROBADOR_YA_DECIDIDO') - $pos);

        $this->assertStringContainsString('bloqueado_por_bucle', $cuerpo,
            'Se quitó (o se movió fuera de `evaluarYaDecidido`) el guard de `bloqueado_por_bucle`. '
            . 'Sin él, "brief contestado" vuelve a desbloquear un item cuya causa de escalación '
            . 'sigue exactamente igual (#710 — 9 escalaciones idénticas en #626).');

        $this->assertStringContainsString('bloqueoBucleSigueVigente', $cuerpo,
            'El guard de `bloqueado_por_bucle` ya no consulta `bloqueoBucleSigueVigente()`: '
            . 'sin comparar el fingerprint, no hay forma de distinguir "misma causa repetida" de '
            . '"cambió algo material" — sería o un bloqueo eterno o el bug original, según cómo '
            . 'se haya reescrito.');
    }

    /**
     * `escalaciones_fingerprint` tiene que viajar en el `select()` del pool de destrabe: si no,
     * `$item->escalaciones_fingerprint` llega `null` en silencio (Eloquent no revienta por una
     * columna real omitida del select) y el guard de #710 nunca frenaría nada en producción —
     * pasaría el `php -l` y los tests unitarios, pero sería un no-op contra la BD real.
     */
    public function test_columna_escalaciones_fingerprint_viaja_en_el_select_del_destrabe(): void
    {
        $f = dirname(__DIR__, 5) . '/app/Modules/Addons/Roadmap/Console/DestrabarCommand.php';
        $this->assertFileExists($f);
        $src = file_get_contents($f);

        $pos = strpos($src, 'COLUMNAS_NECESARIAS');
        $this->assertNotFalse($pos, 'No encontré `COLUMNAS_NECESARIAS` — ¿se renombró?');

        $this->assertStringContainsString("'escalaciones_fingerprint'", $src,
            'Falta `escalaciones_fingerprint` en `COLUMNAS_NECESARIAS`: el guard de #710 en '
            . '`evaluarYaDecidido()` leería siempre `null` ahí y nunca bloquearía nada por esta vía.');
    }
}
