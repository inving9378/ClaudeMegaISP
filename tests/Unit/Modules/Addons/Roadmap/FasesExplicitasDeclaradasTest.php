<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Services\ThomasService;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD
                                 // (mismo motivo que EvaluarYaDecididoEscalarTest: `tests/TestCase.php`
                                 // corre `migrate:fresh` contra la BD compartida de dev).

/**
 * #193 — señal 2 de `ThomasService::caberEnVuelta()`: el propio spec se enumera a sí mismo en
 * varias partes con la misma etiqueta (`--- HIJO A ... ---`, `--- HIJO B ... ---`, …).
 *
 * `fasesExplicitasDeclaradas()` es el núcleo puro (solo regex, sin BD ni contenedor) — se prueba
 * aquí con el texto REAL del item #191 (la medición que originó #193: "cinco fases explícitas")
 * y con el boilerplate fijo (`FUERA DE ALCANCE`, `CRITERIOS DE ACEPTACIÓN`, …) que NO debe
 * confundirse con una enumeración.
 */
class FasesExplicitasDeclaradasTest extends TestCase
{
    /** Caso 1/6 — texto vacío no revienta, responde 0. */
    public function test_texto_vacio_devuelve_cero(): void
    {
        $this->assertSame(0, ThomasService::fasesExplicitasDeclaradas(''));
    }

    /** Caso 2/6 — sin ningún encabezado enumerado, responde 0. */
    public function test_texto_sin_encabezados_devuelve_cero(): void
    {
        $texto = "Agregar un botón nuevo en la pantalla de facturación.\nSin fases, es trabajo simple.";

        $this->assertSame(0, ThomasService::fasesExplicitasDeclaradas($texto));
    }

    /**
     * Caso 3/6 — el boilerplate fijo de cierre de item (FUERA DE ALCANCE / CRITERIOS DE
     * ACEPTACIÓN / QUÉ VALIDAR CON SCREENSHOT) NO es una enumeración de fases: ninguna de esas
     * etiquetas trae un enumerador de una sola letra/dígito pegado al delimitador.
     */
    public function test_boilerplate_de_cierre_no_cuenta_como_fase(): void
    {
        $texto = "--- FUERA DE ALCANCE ---\n- No se toca producción.\n"
            . "--- CRITERIOS DE ACEPTACIÓN ---\n- Algo pasa.\n"
            . "--- QUÉ VALIDAR CON SCREENSHOT ---\n- La pantalla X.";

        $this->assertSame(0, ThomasService::fasesExplicitasDeclaradas($texto));
    }

    /** Caso 4/6 — dos enumeradores de la misma etiqueta, por debajo del umbral de 3: se cuentan igual (el umbral lo aplica caberEnVuelta, no este método). */
    public function test_dos_enumeradores_se_cuentan(): void
    {
        $texto = "--- ENTREGABLE A · lo primero ---\ndetalle\n--- ENTREGABLE B · lo segundo ---\ndetalle";

        $this->assertSame(2, ThomasService::fasesExplicitasDeclaradas($texto));
    }

    /**
     * Caso 5/6 — el texto REAL del item #191 (recortado a los encabezados) trae 5 "HIJO"
     * distintos (A-E): es la medición que #193 documenta como "cinco fases explícitas".
     */
    public function test_spec_real_item_191_cinco_hijos(): void
    {
        $texto = <<<'TXT'
            --- HIJO A · Modelo de datos del expediente ---
            Migración incremental que agregue los campos que las plantillas piden.

            --- HIJO B · Motor de plantillas ---
            Las plantillas viven como filas en `document_templates`.

            --- HIJO C · Las 11 plantillas convertidas ---
            Convertir cada .docx a fila de `document_templates`.

            --- HIJO D · Paquetes por puesto y generación al alta ---
            Pantalla para definir qué documentos le tocan a cada puesto.

            --- HIJO E · La pestaña Documentos ---
            Nueva pestaña en la fila superior de ambos perfiles.

            --- FUERA DE ALCANCE ---
            - No se toca producción.

            --- CRITERIOS DE ACEPTACIÓN ---
            - Alta de un técnico nuevo.
            TXT;

        $this->assertSame(5, ThomasService::fasesExplicitasDeclaradas($texto));
    }

    /** Caso 6/6 — etiquetas DISTINTAS (2 "HIJO" + 3 "PIEZA") no se suman entre sí: manda el máximo por etiqueta, no el total. */
    public function test_etiquetas_distintas_no_se_suman(): void
    {
        $texto = "--- HIJO A · x ---\n--- HIJO B · x ---\n"
            . "--- PIEZA 1 · x ---\n--- PIEZA 2 · x ---\n--- PIEZA 3 · x ---";

        $this->assertSame(3, ThomasService::fasesExplicitasDeclaradas($texto));
    }
}
