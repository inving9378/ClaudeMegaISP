<?php

namespace Tests\Unit\Services\Ipv6;

use App\Services\Ipv6\MikrotikBoardFamilyResolver;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO toca BD, NO migrate:fresh.

class MikrotikBoardFamilyResolverTest extends TestCase
{
    public function test_override_manual_siempre_gana(): void
    {
        $result = MikrotikBoardFamilyResolver::resolve('CCR2216-1G-12XS-2XQ', 'arm64', 'tile-forzado');
        $this->assertSame('tile-forzado', $result['family']);
        $this->assertSame('override', $result['source']);
    }

    public function test_usa_architecture_name_nativo_si_no_hay_override(): void
    {
        $result = MikrotikBoardFamilyResolver::resolve('CCR2216-1G-12XS-2XQ', 'arm64');
        $this->assertSame('arm64', $result['family']);
        $this->assertSame('architecture-name', $result['source']);
    }

    public function test_cae_a_heuristica_de_board_name_si_falta_architecture_name(): void
    {
        $result = MikrotikBoardFamilyResolver::resolve('hAP ac2', null);
        $this->assertSame('mipsbe', $result['family']);
        $this->assertSame('board-name-heuristico', $result['source']);
    }

    public function test_ccr1_vs_ccr2_no_se_confunden(): void
    {
        $this->assertSame('tile', MikrotikBoardFamilyResolver::resolve('CCR1036-8G-2S+', null)['family']);
        $this->assertSame('arm64', MikrotikBoardFamilyResolver::resolve('CCR2116-12G-4S+', null)['family']);
    }

    public function test_desconocida_si_no_hay_ninguna_senal(): void
    {
        $result = MikrotikBoardFamilyResolver::resolve(null, null);
        $this->assertSame('desconocida', $result['family']);
        $this->assertSame('ninguna', $result['source']);
    }

    public function test_board_name_sin_match_cae_a_desconocida(): void
    {
        $result = MikrotikBoardFamilyResolver::resolve('EquipoNoCatalogado-9999', null);
        $this->assertSame('desconocida', $result['family']);
    }
}
