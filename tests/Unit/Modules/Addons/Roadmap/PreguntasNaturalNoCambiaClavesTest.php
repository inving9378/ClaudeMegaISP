<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO #652 pieza 2 — LA TRADUCCIÓN NO PUEDE CAMBIAR LO QUE IRVING AUTORIZA.
 *
 * La identidad de una opción del brief ES SU TEXTO: `RoadmapItem::claveOpcion()` devuelve
 * `substr(sha1(texto_normalizado), 0, 16)`, y la Torre responde mandando esa clave
 * (`TorreControl.vue` → POST /circuito/elegir-opcion con `opcion: op.clave`).
 *
 * Si la traducción se escribiera DENTRO de `preguntas`, cada opción cambiaría de clave: una
 * respuesta ya guardada dejaría de corresponder con ninguna opción y, peor, al regenerar el
 * brief lo que Irving autorizó apuntaría a otra cosa. No es un riesgo de estilo: es corrupción
 * silenciosa de una decisión.
 *
 * Por eso la traducción vive en la columna `preguntas_natural`, indexada por la clave ORIGINAL.
 * Este candado impide que alguien "simplifique" eso más adelante.
 *
 * Inspección de fuente — mismo patrón que `RevisorAlcanceNoSeLlamaFronteraDuraTest`.
 */
class PreguntasNaturalNoCambiaClavesTest extends TestCase
{
    private function fuente(): string
    {
        $f = dirname(__DIR__, 5) . '/app/Modules/Addons/Roadmap/Services/ResumenNaturalService.php';
        $this->assertFileExists($f, 'Se movió/renombró ResumenNaturalService: actualiza este candado.');

        return file_get_contents($f);
    }

    /** El servicio NO puede escribir en `preguntas`: ahí viven las claves. */
    public function test_el_servicio_nunca_escribe_en_preguntas(): void
    {
        $src = $this->fuente();

        // Sólo ESCRITURAS al atributo del modelo. `'preguntas' => $payload` (el cuerpo que se
        // manda al modelo) es legítimo y no debe disparar este candado.
        foreach (['->preguntas =', "->preguntas['", '->preguntas[]'] as $patron) {
            $this->assertStringNotContainsString(
                $patron,
                $src,
                "REGRESIÓN: el traductor escribe en `preguntas`. Cambiar el texto de una opción le "
                . 'cambia la clave (sha1 del texto) y la decisión guardada apunta a otra opción. '
                . 'La traducción va en `preguntas_natural`, indexada por la clave ORIGINAL.'
            );
        }
    }

    /** La clave tiene que derivarse del texto ORIGINAL, nunca del traducido. */
    public function test_la_clave_sale_del_texto_original(): void
    {
        $src = $this->fuente();

        $this->assertMatchesRegularExpression(
            '/claveOpcion\(\(string\)\s*\(\$o\[.texto.\]/',
            $src,
            'REGRESIÓN: la clave dejó de derivarse de $o["texto"] (el original). Si se deriva del '
            . 'texto traducido, el mapa no casa con lo que la Torre envía al responder.'
        );

        $this->assertStringContainsString(
            'preguntas_natural',
            $src,
            'El traductor ya no menciona `preguntas_natural`: revisa dónde está guardando.'
        );
    }

    /** El casador tiene que RECHAZAR si el modelo perdió, inventó o fusionó algo. */
    public function test_rechaza_si_no_cuadran_las_cantidades(): void
    {
        $src = $this->fuente();

        $ini = strpos($src, 'private function casarPreguntas');
        $this->assertNotFalse($ini, 'Desapareció casarPreguntas(): era el candado del lado del código.');
        $cuerpo = substr($src, $ini, 3000);

        $this->assertStringContainsString(
            'count($datos) !== count($preguntas)',
            $cuerpo,
            'REGRESIÓN: ya no se verifica que venga la MISMA cantidad de preguntas.'
        );
        $this->assertStringContainsString(
            'count($opsTrad) !== count($opsOrig)',
            $cuerpo,
            'REGRESIÓN: ya no se verifica que cada pregunta conserve TODAS sus opciones. '
            . 'Un brief con una opción de menos es un brief que decide por Irving.'
        );
    }
}
