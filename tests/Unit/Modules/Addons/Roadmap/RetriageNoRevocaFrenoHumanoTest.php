<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * FASE 2A.4 — CANDADO DE LA REGLA ASIMÉTRICA.
 *
 *   · Freno del CLASIFICADOR → caduca solo (es un consejo automático).
 *   · Freno HUMANO           → **NUNCA caduca.** Es una decisión de Irving y el sistema no la
 *                              revoca por antigüedad; se RESURFACEA en el digest.
 *
 * La regla está escrita en tres sitios y éste es el tercero: la config (por AUSENCIA de la clave),
 * el fail-closed de `RetriageFrenosCommand::handle()` y este test. Tres, porque la mitad de esta
 * fase existe por reglas que vivían en un solo lugar y se cayeron sin que nadie se enterara.
 *
 * Un caducado automático les revocaría a Irving 33 decisiones a la mala. El test no puede probar
 * intenciones, pero sí puede probar que el camino del freno humano NO escribe.
 */
class RetriageNoRevocaFrenoHumanoTest extends TestCase
{
    private function raiz(): string
    {
        return dirname(__DIR__, 5);
    }

    private function comando(): string
    {
        $f = $this->raiz() . '/app/Modules/Addons/Roadmap/Console/RetriageFrenosCommand.php';
        $this->assertFileExists($f, 'Se movió/renombró el re-triage: actualiza este candado en el mismo commit.');

        return file_get_contents($f);
    }

    /** El caducado sólo puede alcanzar filas del CLASIFICADOR: su query lo filtra explícitamente. */
    public function test_el_caducado_solo_alcanza_al_clasificador(): void
    {
        $cuerpo = $this->metodo($this->comando(), 'caducarClasificador');

        $this->assertStringContainsString("->where('origen_bloqueo', 'clasificador')", $cuerpo,
            'El caducado dejó de filtrar por `origen_bloqueo = clasificador`: tal como está podría '
            . 'alcanzar frenos HUMANOS, que no caducan nunca.');

        $this->assertStringNotContainsString("'humano'", $cuerpo,
            'El caducado menciona el freno humano. Ese camino no debe poder verlo siquiera.');
    }

    /** El camino del freno humano es de SÓLO LECTURA. Ni un save, ni un update, ni un delete. */
    public function test_el_camino_del_freno_humano_no_escribe(): void
    {
        $src = $this->comando();

        foreach (['resurfacearHumanos', 'frenosHumanos'] as $metodo) {
            $cuerpo = $this->metodo($src, $metodo);
            foreach (['->save(', '->update(', '->delete(', '->forceDelete(', 'DB::update', 'DB::statement'] as $escritura) {
                $this->assertStringNotContainsString($escritura, $cuerpo,
                    "`{$metodo}()` escribe (`{$escritura}`). El freno humano se RESURFACEA, no se "
                    . 'toca: el sistema no revoca una decisión de Irving por antigüedad.');
            }
        }
    }

    /**
     * La config NO puede tener una caducidad para el freno humano. Su ausencia ES la decisión —
     * si alguien la agrega "para tener paridad con el clasificador", este test lo para antes de que
     * 33 decisiones se revoquen solas.
     */
    public function test_la_config_no_declara_caducidad_para_el_freno_humano(): void
    {
        $config = file_get_contents($this->raiz() . '/config/circuito.php');

        foreach (['humano_caduca', 'humano_caduca_dias', 'caduca_humano'] as $clave) {
            $this->assertStringNotContainsString("'{$clave}'", $config,
                "`config/circuito.php` declara `{$clave}`. El freno humano NO caduca: la ausencia de "
                . 'esa clave es la decisión, no un olvido. (El comando además falla-cerrado si aparece.)');
        }
    }

    /** El fail-closed sigue en su sitio: si la clave apareciera, el comando aborta en vez de correr. */
    public function test_el_comando_falla_cerrado_si_alguien_agrega_la_clave(): void
    {
        $handle = $this->metodo($this->comando(), 'handle');

        $this->assertStringContainsString('humano_caduca', $handle,
            'Se quitó el fail-closed del `handle()`: sin él, agregar la clave a la config empezaría '
            . 'a revocar frenos humanos en silencio.');
        $this->assertStringContainsString('self::FAILURE', $handle,
            'El fail-closed ya no aborta el comando.');
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
