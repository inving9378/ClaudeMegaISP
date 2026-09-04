<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Support\AblandamientoFrontera;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO — item #9990246 (Fase 3 de #9990210): «¿cuándo corresponde registrar un evento
 * `ablandamiento_paso` en `torre_frontera_dura_eventos`, y con qué datos?».
 *
 * `AblandamientoFrontera::evento()` es la parte PURA que `TorreAutomationPolicy::estadoInicial()`
 * consulta con el mismo `$det` que devuelve `JarvisService::fronteraDuraDeItemDetalle()`. Este
 * test la ejercita directo, sin bootear Laravel — la escritura real (el `INSERT`, la dedup por BD)
 * vive en `TorreAutomationPolicy` y queda fuera de este candado a propósito: la base de tests de
 * este worktree resuelve a la de dev (ver `tests/GuardBaseDePruebas.php`), así que la parte con
 * efectos no es probable aquí sin ese riesgo.
 */
class AblandamientoFronteraTest extends TestCase
{
    /** El caso que motiva el item: la válvula ablandó la mención y `categoria` salió null. */
    public function test_registra_cuando_la_valvula_ablando_y_categoria_salio_null(): void
    {
        $det = [
            'categoria'           => null,
            'categoria_detectada' => 'negocio',
            'termino'             => 'producción',
            'ablandada'           => true,
        ];

        $evento = AblandamientoFrontera::evento($det);

        $this->assertSame([
            'categoria' => 'negocio',
            'termino'   => 'producción',
            'veredicto' => 'ablandamiento_paso',
        ], $evento);
    }

    /** El veredicto nuevo tiene que caber en el `string(20)` de la migración. */
    public function test_el_veredicto_cabe_en_la_columna(): void
    {
        $this->assertLessThanOrEqual(20, strlen(AblandamientoFrontera::VEREDICTO));
    }

    /** Si `categoria` NO es null, el item quedó retenido de verdad: eso lo cubre el otro camino. */
    public function test_no_registra_cuando_categoria_no_es_null(): void
    {
        $det = [
            'categoria'           => 'dinero',
            'categoria_detectada' => 'dinero',
            'termino'             => 'pago',
            'ablandada'           => true,
        ];

        $this->assertNull(AblandamientoFrontera::evento($det));
    }

    /** Sin `ablandada=true` no fue la válvula la que dejó pasar el item. */
    public function test_no_registra_sin_ablandada(): void
    {
        $det = [
            'categoria'           => null,
            'categoria_detectada' => null,
            'termino'             => null,
            'ablandada'           => false,
        ];

        $this->assertNull(AblandamientoFrontera::evento($det));
    }

    /** Efecto `avisar`: dispara y no retiene, pero NO es ablandamiento — no debe registrar. */
    public function test_no_registra_en_efecto_avisar(): void
    {
        $det = [
            'categoria'           => null,
            'categoria_detectada' => 'negocio',
            'termino'             => 'venta',
            'ablandada'           => false, // fronteraDuraDeItemDetalle() no marca ablandada en el camino "avisar"
        ];

        $this->assertNull(AblandamientoFrontera::evento($det));
    }

    /** Sin categoría o término detectado (blindaje defensivo) no hay nada que registrar. */
    public function test_no_registra_sin_categoria_detectada_ni_termino(): void
    {
        $this->assertNull(AblandamientoFrontera::evento([
            'categoria' => null, 'categoria_detectada' => null, 'termino' => 'algo', 'ablandada' => true,
        ]));

        $this->assertNull(AblandamientoFrontera::evento([
            'categoria' => null, 'categoria_detectada' => 'dinero', 'termino' => '', 'ablandada' => true,
        ]));
    }
}
