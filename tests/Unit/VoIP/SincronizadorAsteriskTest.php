<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\VoIP\Services\SincronizadorAsterisk;
use PHPUnit\Framework\TestCase;

/** La comparación de endpoints que decide si una extensión se vuelve a publicar en Asterisk. */
class SincronizadorAsteriskTest extends TestCase
{
    public function test_iguales_no_hay_diferencias(): void
    {
        $esperado = ['id' => '1001', 'transport' => 'transport-udp', 'context' => 'from-internal', 'media_address' => null];
        $actual   = ['id' => '1001', 'transport' => 'transport-udp', 'context' => 'from-internal', 'media_address' => null, 'otra' => 'x'];

        $this->assertSame([], SincronizadorAsterisk::diferencias($esperado, $actual));
    }

    public function test_vacio_y_null_cuentan_igual(): void
    {
        $this->assertSame([], SincronizadorAsterisk::diferencias(['media_address' => null], ['media_address' => '']));
        $this->assertSame([], SincronizadorAsterisk::diferencias(['callerid' => ''], []));
    }

    public function test_detecta_columnas_distintas_o_faltantes(): void
    {
        $esperado = ['context' => 'from-internal', 'rtp_symmetric' => 'yes', 'transport' => 'transport-externo'];
        $actual   = ['context' => 'from-internal-restringido', 'transport' => 'transport-externo'];

        $this->assertSame(['context', 'rtp_symmetric'], SincronizadorAsterisk::diferencias($esperado, $actual));
    }
}
