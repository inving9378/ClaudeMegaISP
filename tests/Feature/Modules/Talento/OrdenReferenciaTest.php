<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Support\OrdenReferencia;
use PHPUnit\Framework\TestCase;

class OrdenReferenciaTest extends TestCase
{
    public function test_parse_y_format(): void
    {
        $this->assertSame(['origen' => 'task', 'id' => 7], OrdenReferencia::parse('T-7'));
        $this->assertSame(['origen' => 'work_order', 'id' => 7], OrdenReferencia::parse('l-7'));
        $this->assertSame(['origen' => null, 'id' => 12], OrdenReferencia::parse(12));
        $this->assertNull(OrdenReferencia::parse('X-1'));
        $this->assertNull(OrdenReferencia::parse('T-'));
        $this->assertSame('T-5', OrdenReferencia::format('task', 5));
        $this->assertSame('L-5', OrdenReferencia::format('work_order', 5));
    }

    public function test_referencia_invalida_no_toca_la_base(): void
    {
        $this->assertSame(OrdenReferencia::INVALIDA, OrdenReferencia::resolve('abc')['estado']);
    }
}
