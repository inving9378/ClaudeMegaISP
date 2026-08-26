<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO: NO bootea Laravel, NO toca BD.

/**
 * CANDADO DEL FRENO DE MANO (#170).
 *
 * ⚠️ TestCase PURO de PHPUnit a propósito: `Tests\TestCase` corre `migrate:fresh --seed`, y este
 * repo comparte base con el entorno de trabajo. El candado se pone sobre el CÓDIGO FUENTE, que es
 * el mismo patrón que ya usan `PoolGuardCoherenceTest` y `TorreTechosCoherentesTest`.
 *
 * Las cuatro propiedades que aquí se fijan son exactamente las que, al faltar, dejaron el circuito
 * sin forma de detenerse. No son estilo: cada una tiene un incidente detrás.
 */
class FrenoFueraDeLaBaseTest extends TestCase
{
    private const RAIZ = __DIR__ . '/../../../../..';

    private function fuente(string $rel): string
    {
        $ruta = self::RAIZ . '/' . $rel;
        $this->assertFileExists($ruta, "Falta {$rel}: el freno de #170 no está donde debería.");

        return (string) file_get_contents($ruta);
    }

    /** Quita comentarios de bloque y de línea: los candados se ponen sobre el código, no sobre su prosa. */
    private function sinComentarios(string $php): string
    {
        $out = '';
        foreach (token_get_all($php) as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($t) ? $t[1] : $t;
        }

        return $out;
    }

    /**
     * 1 — `isPaused()` es FAIL-CLOSED. Con la base caída tiene que decir FRENADO, no reventar ni
     * decir "suelto". Un falso "suelto" son seis terminales con permiso de escritura trabajando a
     * ciegas contra una base que no responde.
     */
    public function test_is_paused_falla_hacia_frenado(): void
    {
        $src = $this->fuente('app/Modules/Addons/Roadmap/Services/RoadmapCircuitoService.php');

        $this->assertMatchesRegularExpression(
            '/public function isPaused\(\).*?catch \(\\\\?Throwable \$e\) \{.*?return true;/s',
            $src,
            '`isPaused()` perdió su catch fail-closed. Con la base caída volvería a lanzar (o a '
            . 'devolver false), que es el agujero exacto que abrió #170.'
        );

        $this->assertMatchesRegularExpression(
            '/public function isPaused\(\).*?FrenoCircuito::activo\(\).*?settings/s',
            $src,
            'El centinela en archivo debe consultarse ANTES que `settings`: existe justamente para '
            . 'funcionar cuando la base no contesta.'
        );
    }

    /**
     * 2 — El centinela vive en una ruta ABSOLUTA, nunca en `storage_path()`.
     *
     * `vuelta.sh` hace `cd` al worktree del slot y cada worktree tiene su propio `storage/` real:
     * con `storage_path()` cada una de las seis terminales tendría su freno privado, y un freno que
     * detiene a una de seis no es un freno.
     */
    public function test_la_ruta_del_centinela_es_absoluta(): void
    {
        // Se mira el CÓDIGO, no los comentarios: el docblock de la clase explica precisamente por
        // qué NO se usa `storage_path()`, y esa explicación no puede hacer fallar al candado.
        $src = $this->sinComentarios($this->fuente('app/Modules/Addons/Roadmap/Support/FrenoCircuito.php'));

        $this->assertStringNotContainsString('storage_path(', $src,
            '`storage_path()` resuelve al storage DEL WORKTREE: daría un freno por terminal.');

        $config = $this->fuente('config/circuito.php');
        $this->assertMatchesRegularExpression(
            "/'centinela'\s*=>\s*env\([^,]+,\s*'\/[^']+'\)/",
            $config,
            'El default de `circuito.freno.centinela` tiene que ser una ruta absoluta literal.'
        );
    }

    /**
     * 3 — EL CHEQUEO DENTRO DEL LAZO. Ésta es la propiedad que costó el huérfano de 1 d 21 h:
     * el freno se miraba sólo al arrancar la vuelta, así que una vez dentro del pool continuo el
     * worker seguía reclamando items pasara lo que pasara. Pausar el cron no lo detuvo porque el
     * lazo ya no volvía a preguntar.
     */
    public function test_vuelta_sh_consulta_el_centinela_dentro_del_lazo(): void
    {
        $sh = $this->fuente('deploy/circuito/vuelta.sh');

        $this->assertMatchesRegularExpression('/^frenado\(\)\s*\{[^}]*-e\s+"\$CENTINELA"/m', $sh,
            '`frenado()` debe resolverse con `test -e` sobre el centinela: sin php y sin base.');

        // El cuerpo del `while true` del pool continuo tiene que preguntar por el freno.
        $lazo = null;
        if (preg_match('/while true; do(.*?)\n  done/s', $sh, $m)) {
            $lazo = $m[1];
        }
        $this->assertNotNull($lazo, 'No se encontró el lazo `while true` del pool continuo en vuelta.sh.');
        $this->assertStringContainsString('frenado', $lazo,
            'El lazo del pool continuo dejó de consultar el freno en cada iteración. Ése es el '
            . 'defecto de #170: un worker huérfano seguiría secando la cola con el freno puesto.');
    }

    /**
     * 4 — Poner y soltar NO son simétricos. Poner funciona siempre (dirección segura); soltar exige
     * que la base responda Y que exista `migrations` — si esa tabla falta, la base que contesta no
     * es la del sistema, y arrancar seis terminales contra ella es peor que dejarlas paradas.
     */
    public function test_reanudar_exige_salud_y_pausar_no(): void
    {
        $reanudar = $this->fuente('app/Modules/Addons/Roadmap/Console/ReanudarCommand.php');
        $this->assertStringContainsString("hasTable('migrations')", $reanudar,
            '`circuito:reanudar` debe negarse si falta la tabla `migrations`.');
        $this->assertStringContainsString('SELECT 1', $reanudar,
            '`circuito:reanudar` debe comprobar que la base responde antes de soltar el freno.');

        $pausar = $this->fuente('app/Modules/Addons/Roadmap/Console/PausarCommand.php');
        foreach (['DB::', 'hasTable'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $pausar,
                "`circuito:pausar` no puede tocar la base ({$prohibido}): tiene que poder frenar "
                . 'el circuito precisamente cuando MySQL es el problema.');
        }
    }
}
