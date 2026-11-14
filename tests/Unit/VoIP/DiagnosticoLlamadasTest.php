<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\VoIP\Services\DiagnosticoLlamadas;
use PHPUnit\Framework\TestCase;

/**
 * El historial SIP de Asterisk sale como lista, pero si el filtro deja un solo mensaje sale
 * completo (visto en Asterisk 22): el diagnóstico tiene que leer los dos.
 */
class DiagnosticoLlamadasTest extends TestCase
{
    private function entradas(string $salida): array
    {
        $con = new class ($salida) extends DiagnosticoLlamadas {
            public function __construct(private string $salida) {}
            public function cli(string $comando): string { return $this->salida; }
        };
        $m = new \ReflectionMethod(DiagnosticoLlamadas::class, 'entradas');
        $m->setAccessible(true);

        return $m->invoke($con, 'where x');
    }

    public function test_lista(): void
    {
        $salida = "No.   Timestamp  (Dir) Address                  SIP Message\n"
            . "===== ========== ============================== ===================================\n"
            . "00004 1794600929 * ==> 203.0.113.2:5060        INVITE sip:5500001234@sip.ejemplo.mx:5060 SIP/2.0\n"
            . "00006 1794600931 * <== 203.0.113.2:5060        SIP/2.0 503 Service unavailable\n";

        $this->assertSame([
            4 => '==> 203.0.113.2:5060  INVITE sip:5500001234@sip.ejemplo.mx:5060 SIP/2.0',
            6 => '<== 203.0.113.2:5060  SIP/2.0 503 Service unavailable',
        ], $this->entradas($salida));
    }

    public function test_un_solo_mensaje_sale_completo(): void
    {
        $salida = "<--- History Entry 0 Received from 127.0.0.1:45842 at 1794677047 --->\n"
            . "INVITE sip:5500001234@127.0.0.1 SIP/2.0\nVia: SIP/2.0/UDP 127.0.0.1:5099\nCall-ID: abc\n";

        $this->assertSame([0 => '<== 127.0.0.1:45842  INVITE sip:5500001234@127.0.0.1 SIP/2.0'], $this->entradas($salida));
    }

    public function test_nada(): void
    {
        $this->assertSame([], $this->entradas("No.   Timestamp  (Dir) Address  SIP Message\n=====\n"));
    }
}
