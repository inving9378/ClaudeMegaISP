<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO del cierre unificado de `vuelta.sh` (#927/#929): CUALQUIER forma en que muera
 * `ejecutar_una()` debe soltar el claim del item y decidir su destino, sin enumerar códigos.
 *
 * Dos capas independientes verificadas aquí (ambas ya viven en main desde 604b8bd2):
 *
 *   1) `ejecutar_una()` cierra POR RESULTADO: cualquier `$RC -ne 0` (no una lista de códigos
 *      específicos) invoca a `circuito:parquear-timeout` distinguiendo la causa real
 *      (timeout/max_turns/error) sólo para el motivo del log.
 *   2) RED DE ÚLTIMO RECURSO: un `trap ... EXIT` cubre la muerte del SCRIPT entero (SIGKILL,
 *      OOM, freno a media vuelta) invocando `circuito:soltar-claim`, que sólo actúa si el item
 *      sigue `en_progreso` Y el `worker_sid` es exactamente el suyo.
 *
 * Verificación end-to-end (item de prueba real, RC inyectado, limpiado tras usarse) documentada
 * en el cierre de #929 — este test es la relectura de forma que evita que alguien reintroduzca
 * la enumeración de códigos (el agujero original: sólo RC=124 llamaba al decisor).
 */
class CierreUnificadoVueltaShTest extends TestCase
{
    private function raiz(): string
    {
        return dirname(__DIR__, 5);
    }

    private function vueltaSh(): string
    {
        $f = $this->raiz() . '/deploy/circuito/vuelta.sh';
        $this->assertFileExists($f, 'Se movió/renombró el script: actualiza este candado en el mismo commit.');

        return file_get_contents($f);
    }

    private function parquearTimeout(): string
    {
        $f = $this->raiz() . '/app/Modules/Addons/Roadmap/Console/ParquearTimeoutCommand.php';
        $this->assertFileExists($f);

        return file_get_contents($f);
    }

    private function soltarClaim(): string
    {
        $f = $this->raiz() . '/app/Modules/Addons/Roadmap/Console/SoltarClaimCommand.php';
        $this->assertFileExists($f);

        return file_get_contents($f);
    }

    /** El cierre debe ser por RESULTADO (cualquier RC≠0), no una enumeración de códigos puntuales. */
    public function test_ejecutar_una_cierra_por_resultado_no_enumera_codigos(): void
    {
        $sh = $this->vueltaSh();

        $this->assertStringContainsString('[ "$RC" -ne 0 ]', $sh,
            'El cierre dejó de ser "cualquier RC != 0" — si alguien volvió a enumerar códigos '
            . '(sólo 124, sólo 1...) reintrodujo el agujero original: una vuelta que muere por una '
            . 'causa no listada se queda `en_progreso` con el worker_sid pegado.');

        $this->assertStringContainsString('circuito:parquear-timeout', $sh);
        $this->assertStringContainsString('--causa="$CAUSA"', $sh,
            'La causa real dejó de viajar al decisor — el motivo del log volvería a mentir '
            . '("se cortó a los Ns" aunque haya sido max-turns o un error distinto).');
    }

    /** El trap EXIT es la red de último recurso para cuando muere el script completo. */
    public function test_trap_exit_cubre_la_muerte_del_script_entero(): void
    {
        $sh = $this->vueltaSh();

        $this->assertStringContainsString('trap limpiar_al_salir EXIT', $sh,
            'Sin el trap EXIT, un SIGKILL/OOM/freno a media vuelta deja el item `en_progreso` '
            . 'con el worker_sid pegado hasta que el reaper lo note por antigüedad, sin motivo real.');

        $this->assertStringContainsString('circuito:soltar-claim', $sh);
    }

    /** `--causa` debe seguir existiendo y alimentando el `motivo` real en AMBOS destinos. */
    public function test_parquear_timeout_declara_causa_y_la_escribe_en_ambos_destinos(): void
    {
        $php = $this->parquearTimeout();

        $this->assertStringContainsString('--causa=timeout', $php,
            'La opción --causa desapareció de la firma del comando.');

        $this->assertMatchesRegularExpression('/max_turns.*=>.*agot[oó]/su', $php,
            'La causa max_turns dejó de traducirse a un motivo humano legible ("agotó sus turnos").');

        // La causa debe quedar escrita en el array de log tanto si reanuda como si va a la bandeja
        // (dos entradas 'causa' => $causa en el log, más la lectura de la option: 3 en total).
        $this->assertSame(3, substr_count($php, "'causa'"),
            "El campo 'causa' debe escribirse en el log de AMBOS caminos (reanuda y bandeja). "
            . 'Si aparece menos veces, uno de los dos volvió a perder la causa real.');
    }

    /** `soltar-claim` nunca debe tocar el reclamo de un worker distinto al que lo invoca. */
    public function test_soltar_claim_solo_actua_si_el_sid_coincide_y_sigue_en_progreso(): void
    {
        $php = $this->soltarClaim();

        $this->assertStringContainsString("estado_aprobacion !== 'en_progreso'", $php,
            'Sin este guard, el trap podría "soltar" un item que ya cerró bien o que ya fue '
            . 'parqueado por el camino normal.');
        $this->assertStringContainsString('worker_sid !== $sid', $php,
            'Sin este guard, el trap de una vuelta podría soltar el reclamo de OTRA terminal que ya '
            . 'tomó el item — justo lo que el candado UN ITEM = UN DUEÑO prohíbe.');
    }
}
