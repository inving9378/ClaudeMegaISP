<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Support\DependenciaItems;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO — dependencias entre items (MR-36, #9990332).
 *
 * El incidente que lo origina: el 2026-09-04 el pool reclamó MR-04, MR-05 y MR-06 en la MISMA
 * pasada. MR-05 («copia de datos legacy → mapared_*») arrancó contra un esquema que MR-04 aún no
 * había creado, y hubo que cortar dos vueltas a mano. El detector de colisiones no lo vio porque
 * compara ARCHIVOS, y esos items tocan archivos distintos.
 *
 * Lo que este candado fija:
 *   · sin dependencias → se despacha (el comportamiento de siempre, que no debe cambiar);
 *   · con una dependencia abierta → NO se despacha;
 *   · `completado` sin merge en main NO cuenta como cerrada (el caso real de MR-03, que estuvo
 *     completado con su módulo entero fuera de `main`);
 *   · un id desconocido cuenta como NO cerrado (falla-segura).
 */
class DependenciaItemsTest extends TestCase
{
    public function test_sin_dependencias_se_despacha_igual_que_siempre(): void
    {
        foreach ([null, []] as $sin) {
            $r = DependenciaItems::evaluar($sin, []);
            $this->assertTrue($r['puede'], 'Un item sin dependencias debe seguir siendo despachable.');
            $this->assertSame([], $r['faltan']);
        }
    }

    public function test_una_dependencia_abierta_frena_el_despacho(): void
    {
        $r = DependenciaItems::evaluar([940], [940 => false]);

        $this->assertFalse($r['puede'], 'MR-05 no debe despacharse con MR-04 abierto.');
        $this->assertSame([940], $r['faltan'], 'debe decir QUÉ falta, para poder mostrarlo en la Torre');
    }

    public function test_todas_cerradas_libera_el_despacho(): void
    {
        $this->assertTrue(DependenciaItems::evaluar([940, 941], [940 => true, 941 => true])['puede']);
    }

    /** El caso real de MR-03: `completado` pero con el código fuera de main. */
    public function test_completado_sin_merge_no_cuenta_como_cerrada(): void
    {
        // El mapa lo construye el llamador con (completado && merge_commit); aquí se fija que un
        // `false` —sea cual sea su origen— frena. El candado del criterio vive en el llamador.
        $this->assertFalse(DependenciaItems::evaluar([9990081], [9990081 => false])['puede']);
    }

    /** FALLA-SEGURA: un id que no está en el mapa (item borrado, dato corrupto) NO abre la puerta. */
    public function test_dependencia_desconocida_cuenta_como_no_cerrada(): void
    {
        $r = DependenciaItems::evaluar([12345], []);

        $this->assertFalse($r['puede'], 'Ante un dato que no se puede resolver, no se despacha.');
        $this->assertSame([12345], $r['faltan']);
    }

    public function test_detecta_ciclos(): void
    {
        $this->assertTrue(
            DependenciaItems::tieneCiclo(1, [1 => [2], 2 => [3], 3 => [1]]),
            'un ciclo deja items que nunca se despachan y nadie sabe por qué'
        );
        $this->assertFalse(DependenciaItems::tieneCiclo(1, [1 => [2], 2 => [3], 3 => []]));
    }
}
