<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO DE #9990365 (verificado live vía tinker con mocks en #9990371, ver
 * docs/roadmap-auditor-hambruna-item-9990371-verificacion.md).
 *
 * `AuditorService::debeCorrer()` ganó una PIEZA 2: la sequía (intervalo alargado por
 * `rachaSeca()`) CEDE cuando hay hambruna real (slots libres > ejecutables). El candado real
 * no es "que hambruna funcione" (ya verificado en vivo con Mockery en la sesión de #9990371) —
 * es que los DOS kill-switches (`auditor_activo` de Torre y `circuito_pausado` global) sigan
 * evaluándose ANTES de la cesión por hambruna. Si alguien mueve la pieza de hambruna por encima
 * de esos guards (p.ej. al refactorizar), el motor generaría trabajo con el kill-switch activo
 * — justo lo que el item prohíbe ("el apagado duro de sequía Nivel 2 #712 sigue evaluándose
 * ANTES e intacto").
 *
 * ⚠️ TestCase PURO a propósito, igual que `RenovarLeaseFiltraPorItemTest`/`PoolGuardCoherenceTest`
 * de este mismo directorio: `Tests\TestCase` corre `migrate:fresh --seed` contra la base
 * compartida de dev. El fix es de orden/composición de guards y se verifica leyendo el código
 * fuente, sin necesidad de tocar BD ni el kill switch real (#342 es solo-lectura para el
 * ejecutor on-box).
 */
class AuditorHambrunaCedeSequiaGuardsTest extends TestCase
{
    private function raiz(): string
    {
        return dirname(__DIR__, 5);
    }

    private function auditorServicio(): string
    {
        $f = $this->raiz() . '/app/Modules/Addons/Roadmap/Services/AuditorService.php';
        $this->assertFileExists($f, 'Se movió/renombró el servicio: actualiza este candado en el mismo commit.');

        return file_get_contents($f);
    }

    private function schedulerComando(): string
    {
        $f = $this->raiz() . '/app/Modules/Addons/Roadmap/Console/SchedulerCommand.php';
        $this->assertFileExists($f, 'Se movió/renombró el comando: actualiza este candado en el mismo commit.');

        return file_get_contents($f);
    }

    /**
     * PIEZA 2 — el motor APAGADO (`auditor_activo=false`) y el circuito en PAUSA
     * (`circuito_pausado`) deben seguir ganándole a la hambruna: sus `return corre:false`
     * tienen que aparecer ANTES, en el cuerpo de `debeCorrer()`, del punto donde se calcula la
     * cesión por hambruna. Si esto se invierte, el generador crearía trabajo con el
     * kill-switch encendido.
     */
    public function test_apagado_y_pausa_siguen_evaluandose_antes_de_ceder_por_hambruna(): void
    {
        $cuerpo = $this->metodo($this->auditorServicio(), 'debeCorrer');

        $posApagado = strpos($cuerpo, 'Motor APAGADO');
        $posPausa   = strpos($cuerpo, 'Circuito en PAUSA');
        $posHambruna = strpos($cuerpo, "\$hambruna       = \$this->hambruna(\$slots);");

        $this->assertNotFalse($posApagado, 'debeCorrer() ya no devuelve el motivo "Motor APAGADO" — ¿se reescribió el guard de auditor_activo?');
        $this->assertNotFalse($posPausa, 'debeCorrer() ya no devuelve el motivo "Circuito en PAUSA" — ¿se reescribió el guard del kill switch global?');
        $this->assertNotFalse($posHambruna, 'debeCorrer() ya no calcula $hambruna con ese nombre — actualiza este candado si se renombró.');

        $this->assertLessThan($posHambruna, $posApagado,
            'El guard "Motor APAGADO" (auditor_activo=false) quedó DESPUÉS de calcular la hambruna: '
            . 'la sequía podría ceder con el motor apagado. Debe evaluarse antes, como apagado duro.');
        $this->assertLessThan($posHambruna, $posPausa,
            'El guard "Circuito en PAUSA" (kill switch #342) quedó DESPUÉS de calcular la hambruna: '
            . 'el generador podría crear trabajo con el circuito pausado. Debe evaluarse antes.');
    }

    /**
     * PIEZA 2 — dentro del bloque "escaneado hace poco" (intervalo de sequía sin vencer), si hay
     * hambruna real el método debe devolver `corre:true` con el motivo explícito de cesión, en
     * vez de dejar la flota ociosa esperando el intervalo alargado.
     */
    public function test_hambruna_real_cede_el_intervalo_alargado_por_sequia(): void
    {
        $cuerpo = $this->metodo($this->auditorServicio(), 'debeCorrer');

        $this->assertMatchesRegularExpression(
            '/\(time\(\)\s*-\s*\$ultima\)\s*<\s*\$intervalo\s*\*\s*60\)\s*\{.*?'
            . 'if\s*\(\$hambruna\[\'hambruna\'\]\)\s*\{.*?'
            . "'corre'\s*=>\s*true.*?la sequ.a cede.*?aunque falte el intervalo/su",
            $cuerpo,
            'debeCorrer() ya no cede el intervalo alargado por sequía cuando hay hambruna real dentro '
            . 'del gate "escaneado hace poco" — la flota podría volver a quedarse ociosa hasta 2h '
            . '(el bug original que #9990365 midió).'
        );
    }

    /**
     * `hambruna()` debe reusar `ejecutablesParalelo()` con `modulosEnVuelo()` — la MISMA puerta
     * módulo-disjunta del scheduler — y no un cálculo paralelo que ignore qué módulos ya están
     * en vuelo (eso rompería el paralelismo por-módulo del pool, no solo la cesión de sequía).
     */
    public function test_hambruna_reusa_ejecutables_paralelo_modulo_disjunto(): void
    {
        $cuerpo = $this->metodo($this->auditorServicio(), 'hambruna');

        $this->assertStringContainsString(
            '$this->circuito->ejecutablesParalelo($this->circuito->modulosEnVuelo(), $slots)',
            $cuerpo,
            'hambruna() dejó de reusar ejecutablesParalelo()+modulosEnVuelo() del scheduler: podría '
            . 'contar como "hambruna" una cola con módulos ya en vuelo, rompiendo el paralelismo '
            . 'módulo-disjunto del pool (N slots).'
        );
    }

    /**
     * PIEZA 4 — `escalarCuelloBotellaSiAplica()` (la escalación a la bandeja de Irving) también
     * debe respetar el kill switch global: con el circuito pausado, no debe diagnosticar ni crear
     * el item de "flota parada".
     */
    public function test_escalar_cuello_botella_respeta_el_kill_switch_global(): void
    {
        $cuerpo = $this->metodo($this->auditorServicio(), 'escalarCuelloBotellaSiAplica');

        $this->assertMatchesRegularExpression(
            '/^\s*if\s*\(\$this->circuito->isPaused\(\)\)\s*\{\s*return null;/m',
            $cuerpo,
            'escalarCuelloBotellaSiAplica() ya no corta de inmediato con el circuito pausado: '
            . 'podría escalar un item a la bandeja de Irving con el kill switch activo.'
        );
    }

    /**
     * `diagnosticoCuelloBotellaDependencias()` no debe disparar mientras haya cola reclamable
     * real (`profundidadCola() > 0`) — verificado también en vivo en #9990371 (cola=1 →
     * diagnóstico=null). Sin este guard, el auditor escalaría "flota parada" con trabajo
     * disponible sin tomar.
     */
    public function test_diagnostico_cuello_botella_no_dispara_con_cola_real(): void
    {
        $cuerpo = $this->metodo($this->auditorServicio(), 'diagnosticoCuelloBotellaDependencias');

        $this->assertMatchesRegularExpression(
            '/^\s*if\s*\(\$this->profundidadCola\(\)\s*>\s*0\)\s*\{\s*return null;/m',
            $cuerpo,
            'diagnosticoCuelloBotellaDependencias() ya no corta cuando hay cola reclamable real: '
            . 'escalaría "flota parada" a Irving con trabajo disponible sin tomar.'
        );
    }

    /**
     * PIEZA 3 — el scheduler debe seguir disparando `ciclo(true)` (el barrido que crea items)
     * exactamente cuando `debeCorrer()['corre']` es true, y la escalación de cuello de botella
     * (PIEZA 4) debe correr DESPUÉS de ese barrido, no antes ni en su lugar — el barrido es lo
     * que primero intenta destrabar la hambruna; escalar es el último recurso.
     */
    public function test_scheduler_encadena_ciclo_y_escalacion_cuello_botella_en_orden(): void
    {
        $src = $this->schedulerComando();

        $posDebeCorrer = strpos($src, "\$auditor->debeCorrer()['corre']");
        $posCiclo      = strpos($src, '$auditor->ciclo(true)');
        $posEscalar    = strpos($src, '$auditor->escalarCuelloBotellaSiAplica()');

        $this->assertNotFalse($posDebeCorrer, 'SchedulerCommand ya no gatea el auditor con debeCorrer()[\'corre\'].');
        $this->assertNotFalse($posCiclo, 'SchedulerCommand ya no llama a $auditor->ciclo(true) (PIEZA 3).');
        $this->assertNotFalse($posEscalar, 'SchedulerCommand ya no llama a $auditor->escalarCuelloBotellaSiAplica() (PIEZA 4).');

        $this->assertLessThan($posCiclo, $posDebeCorrer,
            'El gate debeCorrer() debe evaluarse ANTES de disparar ciclo(true).');
        $this->assertLessThan($posEscalar, $posCiclo,
            'escalarCuelloBotellaSiAplica() debe correr DESPUÉS del barrido de ciclo(true), no antes: '
            . 'el barrido es el primer intento de destrabar la hambruna antes de escalar a Irving.');
    }

    /** Cuerpo fuente de un método, balanceando llaves. */
    private function metodo(string $src, string $nombre): string
    {
        $pos = strpos($src, "function {$nombre}(");
        $this->assertNotFalse($pos, "No encontré `{$nombre}()` — ¿se renombró?");

        $inicio = strpos($src, '{', $pos);
        $depth  = 0;
        for ($i = $inicio, $len = strlen($src); $i < $len; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}' && --$depth === 0) {
                return substr($src, $inicio, $i - $inicio + 1);
            }
        }

        $this->fail("No pude delimitar `{$nombre}()`.");
    }
}
