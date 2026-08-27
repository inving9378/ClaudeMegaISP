<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Services\ValvulaContextoService;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO — «una válvula sólo puede aflojar sobre algo que está ahí».
 *
 * QUÉ PASÓ. La válvula de contexto es el interruptor que puede APAGAR la frontera dura, el único
 * control por contenido del circuito que no depende de la autodeclaración de un modelo. A ese
 * modelo se le pasaba la CATEGORÍA de la frontera ('dinero', 'credenciales') bajo la etiqueta
 * «TÉRMINO QUE DISPARÓ», y el término real —el que `DetectorTerminos::dispara` hizo saltar— nunca
 * llegaba. En los DOS items que alcanzaron a sellarse, 2 de 2, la palabra de la categoría ni
 * siquiera aparecía en el texto:
 *
 *   · #182 — disparó «permiso» (categoría `credenciales`). Se preguntó por «credenciales».
 *     El modelo contestó, textualmente: «El término 'credenciales' no aparece en el texto» → y de
 *     esa AUSENCIA concluyó «mención», abriendo la frontera. El item implementaba control de acceso
 *     real con Spatie.
 *   · #191 — disparó «contratar» (categoría `dinero`). Se preguntó por «dinero», palabra ausente
 *     del texto. El item llevaba dentro CURP, RFC, NSS y sueldos.
 *
 * Preguntar «¿este item TOCA "dinero"?» sobre un documento donde esa palabra no existe no es un
 * error de criterio del modelo: es otra pregunta, y «mención» es su respuesta estructuralmente
 * esperable. Arreglar qué se le pasa (commit 474b6adf) corrige el caso; esta guarda cierra la CLASE,
 * y sin involucrar a ningún modelo: si el término no está en el texto, la pregunta está mal formada
 * y no se pregunta.
 *
 * Este archivo fija el predicado puro. Los dos casos son los REALES, no inventados.
 */
class ValvulaGuardaTerminoPresenteTest extends TestCase
{
    /** Fragmento real del #182 («Torre de Control: listado vacío … panel de ajustes bajo engrane»). */
    private const TEXTO_182 = <<<'TXT'
        Torre de Control: listado vacío en Hoja de ruta y panel de ajustes bajo engrane
        Core / Permisos
        El engrane debe abrir un panel de ajustes. Sólo los roles super-administrator y
        DESARROLLADOR tienen permiso para verlo; el resto no debe ver el engrane siquiera.
        TXT;

    /** Fragmento real del #191 («Expediente digital del personal»). */
    private const TEXTO_191 = <<<'TXT'
        Expediente digital del personal: plantillas por puesto y generación al alta
        Talento
        Al contratar a alguien se genera su expediente con las plantillas de su puesto:
        CURP, RFC, NSS y el desglose de sueldo del contrato.
        TXT;

    /**
     * EL CASO #182, invertido: preguntar por la categoría queda atajado; preguntar por el término
     * real deja pasar la consulta, que es lo correcto — ahí el modelo sí tiene algo que juzgar.
     */
    public function test_la_categoria_ausente_del_texto_no_pasa_la_guarda(): void
    {
        $this->assertFalse(
            ValvulaContextoService::terminoPresente(self::TEXTO_182, 'credenciales'),
            'La palabra «credenciales» no está en el #182: preguntar por ella es una pregunta mal formada.'
        );

        $this->assertTrue(
            ValvulaContextoService::terminoPresente(self::TEXTO_182, 'permiso'),
            'El término REAL que disparó sí está en el texto: la consulta al modelo es legítima.'
        );
    }

    /** El mismo patrón en el #191, para no depender de un solo caso. */
    public function test_el_caso_191_repite_el_patron(): void
    {
        $this->assertFalse(ValvulaContextoService::terminoPresente(self::TEXTO_191, 'dinero'));
        $this->assertTrue(ValvulaContextoService::terminoPresente(self::TEXTO_191, 'contratar'));
    }

    /**
     * La guarda mide PRESENCIA, no disparo: un término NEGADO sigue estando en el texto, así que la
     * pregunta está bien formada y le toca al modelo juzgarla. Si la guarda usara `dispara()` se
     * comería justo los casos que la válvula existe para resolver (los #874-#877, que salieron
     * nivel C por su propio bloque de guardrails).
     */
    public function test_un_termino_negado_sigue_estando_presente(): void
    {
        $this->assertTrue(
            ValvulaContextoService::terminoPresente('Este item no toca producción en absoluto.', 'producción'),
            'Negado o no, la palabra está en el texto: la pregunta al modelo es legítima.'
        );
    }

    /** Anclado al INICIO de palabra: 'rol' no debe darse por presente dentro de 'control'. */
    public function test_no_se_da_por_presente_dentro_de_otra_palabra(): void
    {
        $this->assertFalse(ValvulaContextoService::terminoPresente('Panel de control de la Torre.', 'rol'));
        $this->assertTrue(ValvulaContextoService::terminoPresente('Se asigna el rol al usuario.', 'rol'));
    }

    /** Admite flexión hacia adelante: 'factura' cubre 'facturación', igual que el detector. */
    public function test_admite_flexion_como_el_detector(): void
    {
        $this->assertTrue(ValvulaContextoService::terminoPresente('Cambios en la facturación mensual.', 'factura'));
    }

    /** Mayúsculas, acentos del texto y espacios sobrantes no deben producir un falso «ausente». */
    public function test_no_depende_de_mayusculas_ni_espacios(): void
    {
        $this->assertTrue(ValvulaContextoService::terminoPresente("Toca   PRODUCCIÓN\tdirecto.", 'producción'));
        $this->assertTrue(ValvulaContextoService::terminoPresente('Toca producción.', '  producción  '));
    }
}
