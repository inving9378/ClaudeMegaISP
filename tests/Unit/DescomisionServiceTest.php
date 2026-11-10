<?php

namespace Tests\Unit;

use App\Modules\Addons\Vendedores\Services\DescomisionService;
use PHPUnit\Framework\TestCase;

class DescomisionServiceTest extends TestCase
{
    public function test_con_flag_apagado_no_hace_nada(): void
    {
        $svc = new class extends DescomisionService {
            public function enabled(): bool { return false; }
            protected function candidatas(int $ventana) { throw new \RuntimeException('no debe leer'); }
        };
        $r = $svc->ejecutar();
        $this->assertFalse($r['enabled']);
        $this->assertSame(0, $r['descomisionadas']);
    }
}
