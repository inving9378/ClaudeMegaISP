<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Support\CarrilSeguridad;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO DE REGRESIÓN — carril AUTO/BANDEJA del priorizador de riesgo (#9990060, wiring de #918).
 *
 * `CarrilSeguridad::calcular()` es la pieza determinista que `RevisorService::briefarSeguridad()`
 * usa para decidir si un candidato de SEGURIDAD puede pasar por `TorreAutomationPolicy` en vez de
 * ir directo a `requiere_irving` (`PriorizarSeguridadCommand::aplicar()`). BANDEJA es el default
 * restrictivo (#918 punto 3): nunca se invierte la carga de prueba.
 *
 * Este test falla si CUALQUIER término de la lista BANDEJA (`bandeja_excepciones` +
 * `bandeja_terminos_existentes` de `config/circuito_hardening.php` — la fuente REAL que usa el
 * wiring, no una copia aparte) resulta en carril='auto'. También ejercita el camino positivo (un
 * `auto_termino` real → 'auto') para que el candado no quede vacuamente en verde.
 */
class CarrilSeguridadAutoRegresionTest extends TestCase
{
    private function config(): array
    {
        $config = require dirname(__DIR__, 5) . '/config/circuito_hardening.php';
        $this->assertIsArray($config);

        return $config;
    }

    public function test_categoria_distinta_de_seguridad_no_tiene_carril(): void
    {
        $cfg = $this->config();

        foreach (['dinero', 'negocio', 'prod', 'no_aplica'] as $cat) {
            $v = ['categoria' => $cat, 'subcat' => 'guards_null', 'texto_brief' => 'sanitizar_validar_entrada'];
            $this->assertNull(CarrilSeguridad::calcular($v, $cfg),
                "categoria={$cat} no debe tener carril — fuera de alcance del wiring (#9990060).");
        }
    }

    /**
     * Ningún término de BANDEJA (excepciones + lista existente) puede colar un item a AUTO, ya sea
     * mencionado en `subcat` o en el `texto_brief` que ya escribió Opus.
     */
    public function test_ningun_termino_de_bandeja_dispara_carril_auto(): void
    {
        $cfg = $this->config();
        $terminos = array_merge($cfg['bandeja_excepciones'], $cfg['bandeja_terminos_existentes']);
        $this->assertNotEmpty($terminos);

        foreach ($terminos as $t) {
            $vSubcat = ['categoria' => 'seguridad', 'subcat' => $t, 'texto_brief' => 'Fix: revisar el flujo por completo.'];
            $this->assertNotSame('auto', CarrilSeguridad::calcular($vSubcat, $cfg),
                "El término bandeja '{$t}' en subcat coló el item a carril=auto.");

            $vBrief = ['categoria' => 'seguridad', 'subcat' => '', 'texto_brief' => "Fix: revisar y corregir {$t} antes de continuar."];
            $this->assertNotSame('auto', CarrilSeguridad::calcular($vBrief, $cfg),
                "El término bandeja '{$t}' en texto_brief coló el item a carril=auto.");
        }
    }

    /**
     * Camino positivo: un `auto_termino` real, mencionado literalmente en el brief y SIN ningún
     * término de bandeja, sí debe producir carril='auto'. Sin este caso el candado solo prueba
     * negativos y no ejercitaría nunca el camino que habilita el pool.
     */
    public function test_termino_de_auto_terminos_sin_bandeja_dispara_carril_auto(): void
    {
        $cfg = $this->config();

        foreach ($cfg['auto_terminos'] as $t) {
            $v = [
                'categoria'   => 'seguridad',
                'subcat'      => 'hardening',
                'texto_brief' => "**Fix**: aplicar {$t} en el controlador antes de procesar la petición.",
            ];
            $this->assertSame('auto', CarrilSeguridad::calcular($v, $cfg),
                "El auto_termino '{$t}' mencionado literalmente en el brief no disparó carril=auto.");
        }
    }

    /** BANDEJA gana siempre, aunque el brief también mencione un auto_termino (#918 punto 3). */
    public function test_bandeja_gana_sobre_auto_terminos_si_ambos_aparecen(): void
    {
        $cfg = $this->config();
        $autoTermino = $cfg['auto_terminos'][0];

        $v = [
            'categoria'   => 'seguridad',
            'subcat'      => 'passwords',
            'texto_brief' => "**Fix**: aplicar {$autoTermino} sobre el campo password antes de guardarlo.",
        ];

        $this->assertSame('bandeja', CarrilSeguridad::calcular($v, $cfg),
            'BANDEJA debe ganar siempre — la carga de prueba nunca se invierte (#918 punto 3).');
    }

    /** Sin ningún término (ni bandeja ni auto) el default restrictivo es bandeja, nunca auto. */
    public function test_sin_ningun_termino_conocido_el_default_es_bandeja(): void
    {
        $v = ['categoria' => 'seguridad', 'subcat' => 'otra_cosa', 'texto_brief' => 'Fix: revisar el flujo completo del módulo.'];

        $this->assertSame('bandeja', CarrilSeguridad::calcular($v, $this->config()));
    }
}
