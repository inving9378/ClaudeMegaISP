<?php

namespace Tests\Unit\Services;

use App\Services\Finance\Billing\ReactivacionEnvios as R;
use PHPUnit\Framework\TestCase;

/** FAC-08 (item #515): compuerta de reactivación gradual. PURA: config y ubicación inyectadas. */
class ReactivacionEnviosTest extends TestCase
{
    private function r(array $cfg): R
    {
        $ubicaciones = [10 => ['colonia' => 501, 'municipio' => 9], 11 => ['colonia' => 777, 'municipio' => 9], 12 => ['colonia' => null, 'municipio' => null]];

        return new R(array_merge(['limite_por_corrida' => 20, 'limite_diario' => 100, 'max_errores_por_corrida' => 3], $cfg),
            fn (int $id) => $ubicaciones[$id] ?? ['colonia' => null, 'municipio' => null]);
    }

    public function test_por_defecto_cerrado_y_etapa_desconocida_tambien(): void
    {
        $this->assertSame(R::CERRADO, $this->r([])->etapa());
        $this->assertSame(R::CERRADO, $this->r(['etapa' => 'abierto'])->etapa());
        $this->assertFalse($this->r([])->permiteCliente(10)[0]);
    }

    public function test_piloto_con_lista_vacia_permite_cero(): void
    {
        $r = $this->r(['etapa' => 'piloto', 'clientes_piloto' => '']);
        $this->assertFalse($r->permiteCliente(10)[0]);
        $this->assertFalse($r->permiteCliente(null)[0]);
    }

    public function test_piloto_solo_los_listados(): void
    {
        $r = $this->r(['etapa' => 'piloto', 'clientes_piloto' => '10, 99 ,abc,-5']);
        $this->assertTrue($r->permiteCliente(10)[0]);
        $this->assertFalse($r->permiteCliente(11)[0]);
        $this->assertSame([10, 99], R::ids('10, 99 ,abc,-5'));
    }

    public function test_zona_suma_piloto_colonias_y_municipios(): void
    {
        $porColonia = $this->r(['etapa' => 'zona', 'clientes_piloto' => '12', 'colonias' => '501']);
        $this->assertSame([true, 'colonia de la zona'], $porColonia->permiteCliente(10));
        $this->assertFalse($porColonia->permiteCliente(11)[0]);
        $this->assertSame([true, 'cliente piloto'], $porColonia->permiteCliente(12));

        $porMunicipio = $this->r(['etapa' => 'zona', 'municipios' => '9']);
        $this->assertTrue($porMunicipio->permiteCliente(11)[0]);
        $this->assertFalse($porMunicipio->permiteCliente(12)[0], 'sin ubicación no entra por zona');
    }

    public function test_todos(): void
    {
        $this->assertTrue($this->r(['etapa' => 'todos'])->permiteCliente(12)[0]);
    }

    public function test_cupo_es_el_menor_entre_corrida_y_dia(): void
    {
        $r = $this->r(['etapa' => 'todos', 'limite_por_corrida' => 20, 'limite_diario' => 100]);
        $this->assertSame(20, $r->cupo(0, 0));
        $this->assertSame(5, $r->cupo(15, 15));
        $this->assertSame(3, $r->cupo(0, 97));
        $this->assertSame(0, $r->cupo(0, 120));
    }

    public function test_parada_por_errores(): void
    {
        $r = $this->r(['max_errores_por_corrida' => 3]);
        $this->assertFalse($r->pararPorErrores(2));
        $this->assertTrue($r->pararPorErrores(3));
        $this->assertTrue($this->r(['max_errores_por_corrida' => 0])->pararPorErrores(1), 'nunca menos de 1');
    }
}
