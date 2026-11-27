<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\CobranzaBlaster\Services\AudienciaCampana as Audiencia;
use PHPUnit\Framework\TestCase;

/**
 * «A quién se le llama» (27-nov): estados del cliente, zona y caja de su nomenclatura, y la lista
 * para revisarla. Datos inventados (el repo es público).
 */
class AudienciaCampanasTest extends TestCase
{
    private function cliente(int $id, string $estado, ?string $tel, ?string $nom, float $monto = 0): object
    {
        return (object) ['client_id' => $id, 'estado' => $estado, 'phone' => $tel, 'nombre' => "Cliente prueba {$id}", 'nomenclatura' => $nom, 'monto' => $monto];
    }

    private function candidatos(): \Illuminate\Support\Collection
    {
        return collect([
            $this->cliente(1, 'Activo', '5512345601', 'D1Z54C7:3', 120),
            $this->cliente(2, 'Activo', '55 1234 5602', 'D1Z54C7:5'),
            $this->cliente(3, 'Bloqueado', '5512345603', 'D1Z54C8:1', 300),
            $this->cliente(4, 'Inactivo', '5512345604', 'D2Z21C1:2'),
            $this->cliente(5, 'Activo', '5512345605', null),            // solo nomenclatura vieja
            $this->cliente(6, 'Activo', '123', 'D1Z21C2:1'),            // sin teléfono que se pueda marcar
            $this->cliente(7, 'Bloqueado', '5512345607', 'd1z21c2:4'),  // minúsculas
        ]);
    }

    public function test_la_nomenclatura_dice_distrito_zona_y_caja(): void
    {
        $this->assertSame(['zona' => 'D1Z54', 'caja' => 'D1Z54C7', 'distrito' => 1, 'letra' => 'Z', 'z' => 54, 'c' => 7], Audiencia::leerNomenclatura('D1Z54C7:3'));
        // WiFi: otra zona aunque tenga el mismo número
        $this->assertSame('D1W54', Audiencia::leerNomenclatura('D1W54C7:3')['zona']);
        $this->assertSame('D1Z5C70', Audiencia::leerNomenclatura(' d01z05c070 ')['caja']);
        $this->assertNull(Audiencia::leerNomenclatura('NSIZ1P6C5:1'));
        $this->assertNull(Audiencia::leerNomenclatura(null));
    }

    public function test_un_filtro_que_no_se_entiende_no_ensancha_la_lista(): void
    {
        $this->assertSame(['estados' => ['Activo', 'Bloqueado'], 'zona' => null, 'caja' => null], Audiencia::normalizar(null));
        $this->assertSame([], Audiencia::normalizar(['estados' => ['Cancelado']])['estados']);
        $this->assertSame(['estados' => ['Inactivo'], 'zona' => 'D1Z54', 'caja' => 'D1Z54C7'],
            Audiencia::normalizar(['estados' => ['Inactivo'], 'caja' => 'd1z54c7']));
        // Una caja de otra zona: se queda la zona elegida.
        $this->assertSame(['zona' => 'D1Z54', 'caja' => null],
            array_intersect_key(Audiencia::normalizar(['zona' => 'D1Z54', 'caja' => 'D2Z21C1']), ['zona' => 1, 'caja' => 1]));
        $this->assertNull(Audiencia::normalizar(['zona' => 'Z54'])['zona']);
        $this->assertSame('D1W10C1', Audiencia::normalizar(['caja' => 'd1w10c1'])['caja']);
    }

    public function test_sin_zona_entran_todos_los_del_estado_con_telefono(): void
    {
        $r = Audiencia::resumir($this->candidatos(), ['estados' => ['Activo', 'Bloqueado']], true);

        $this->assertSame([1, 2, 3, 5, 7], array_column($r['clientes'], 'id'));
        $this->assertSame(5, $r['clientes_n']);
        $this->assertSame(1, $r['sin_telefono']);
        $this->assertSame(1, $r['sin_caja']);
        $this->assertSame('5512345602', $r['clientes'][1]['telefono']);
        // Cada estado cuenta aunque no esté marcado: así se sabe cuántos entrarían.
        $this->assertSame(['Activo' => 3, 'Bloqueado' => 2, 'Inactivo' => 1], $r['por_estado']);
    }

    public function test_por_zona_y_por_caja(): void
    {
        $zona = Audiencia::resumir($this->candidatos(), ['estados' => ['Activo', 'Bloqueado', 'Inactivo'], 'zona' => 'D1Z54'], true);
        $this->assertSame([1, 2, 3], array_column($zona['clientes'], 'id'));
        // El 5 no tiene nomenclatura nueva: el filtro por zona no lo puede encontrar, y se avisa.
        $this->assertSame(1, $zona['sin_caja']);

        $caja = Audiencia::resumir($this->candidatos(), ['estados' => ['Activo', 'Bloqueado'], 'caja' => 'D1Z54C7'], true);
        $this->assertSame([1, 2], array_column($caja['clientes'], 'id'));
        $this->assertSame(['Activo' => 2, 'Bloqueado' => 0, 'Inactivo' => 0], $caja['por_estado']);

        // La misma zona en otro distrito es otra zona.
        $d1 = Audiencia::resumir($this->candidatos(), ['estados' => ['Activo', 'Bloqueado', 'Inactivo'], 'zona' => 'D1Z21'], true);
        $this->assertSame([7], array_column($d1['clientes'], 'id'));
        $this->assertSame(1, $d1['sin_telefono']);
    }

    public function test_las_zonas_y_cajas_cuentan_solo_a_quien_se_puede_llamar(): void
    {
        $r = Audiencia::resumir($this->candidatos(), ['estados' => ['Activo', 'Bloqueado'], 'zona' => 'D1Z54'], false);

        $this->assertArrayNotHasKey('clientes', $r);
        // Las zonas no dependen de la zona elegida, sí de los estados (el 4 es Inactivo).
        $this->assertSame(['D1Z21', 'D1Z54'], array_column($r['zonas'], 'clave'));
        $z54 = $r['zonas'][1];
        $this->assertSame(3, $z54['clientes']);
        $this->assertSame([['clave' => 'D1Z54C7', 'caja' => 7, 'clientes' => 2], ['clave' => 'D1Z54C8', 'caja' => 8, 'clientes' => 1]], $z54['cajas']);
        $this->assertSame('Z', $z54['letra']);
        // En D1Z21 el 6 no tiene teléfono: no cuenta.
        $this->assertSame(1, $r['zonas'][0]['clientes']);
    }

    public function test_el_adeudo_se_suma_de_los_que_entran(): void
    {
        $r = Audiencia::resumir($this->candidatos(), ['estados' => ['Activo', 'Bloqueado']], false);
        $this->assertSame(420.0, $r['monto']);
    }

    public function test_sin_estados_no_se_llama_a_nadie(): void
    {
        $r = Audiencia::resumir($this->candidatos(), ['estados' => []], true);
        $this->assertSame([], $r['clientes']);
        $this->assertSame(0, $r['clientes_n']);
        $this->assertSame([], $r['zonas']);
    }

    public function test_un_estado_escrito_distinto_no_tumba_la_lista(): void
    {
        $r = Audiencia::resumir(collect([
            $this->cliente(1, 'activo ', '5512345601', 'D1Z54C7:3'),
            $this->cliente(2, 'BLOQUEADO', '5512345602', null),
            $this->cliente(3, 'Cancelado', '5512345603', null),
        ]), ['estados' => ['Activo', 'Bloqueado']], true);

        $this->assertSame([1, 2], array_column($r['clientes'], 'id'));
        $this->assertSame(['Activo', 'Bloqueado'], array_column($r['clientes'], 'estado'));
    }
}
