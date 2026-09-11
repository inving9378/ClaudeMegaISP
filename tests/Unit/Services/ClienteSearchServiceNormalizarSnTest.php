<?php

namespace Tests\Unit\Services;

use App\Modules\Core\Clientes\Services\ClienteSearchService;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO toca BD, NO migrate:fresh.

/**
 * Item roadmap #9990833, Fase 1: el SN del equipo llega en dos formatos distintos
 * para el mismo aparato — ASCII+hex de la OLT (ECOMC8012F9B) y ya-canónico de la
 * captura manual (45434F4DC8012F9B). normalizarSn() debe hacerlos idénticos; si
 * eso deja de cumplirse, el buscador vuelve a dar falsos "no encontrado".
 */
class ClienteSearchServiceNormalizarSnTest extends TestCase
{
    public function test_formato_corto_ascii_y_formato_canonico_normalizan_igual(): void
    {
        $this->assertSame(
            ClienteSearchService::normalizarSn('ECOMC8012F9B'),
            ClienteSearchService::normalizarSn('45434F4DC8012F9B')
        );
    }

    public function test_formato_corto_ecom_normaliza_a_16_hex(): void
    {
        $this->assertSame('45434F4DC8012F9B', ClienteSearchService::normalizarSn('ECOMC8012F9B'));
    }

    public function test_formato_canonico_se_mantiene_igual(): void
    {
        $this->assertSame('45434F4DC8012F9B', ClienteSearchService::normalizarSn('45434F4DC8012F9B'));
    }

    public function test_tolera_espacios_y_minusculas(): void
    {
        $this->assertSame('45434F4DC8012F9B', ClienteSearchService::normalizarSn('ecom c801 2f9b'));
    }

    public function test_vendor_huawei_normaliza_correcto(): void
    {
        $this->assertSame('48575443FEFCC9A2', ClienteSearchService::normalizarSn('HWTCFEFCC9A2'));
    }

    public function test_formato_no_reconocido_se_limpia_sin_transformar(): void
    {
        $this->assertSame('SNINVENTADO', ClienteSearchService::normalizarSn('SN-INVENTADO'));
    }

    public function test_null_y_vacio_devuelven_null(): void
    {
        $this->assertNull(ClienteSearchService::normalizarSn(null));
        $this->assertNull(ClienteSearchService::normalizarSn(''));
    }

    public function test_formato_corto_es_la_inversa_del_canonico(): void
    {
        $this->assertSame('ECOMC8012F9B', ClienteSearchService::formatoCorto('45434F4DC8012F9B'));
    }

    public function test_formato_corto_no_inventa_vendor_no_imprimible(): void
    {
        // Los primeros 8 hex no decodifican a 4 ASCII imprimibles (ej. dato ya
        // manual sin vendor real): se devuelve el canónico tal cual.
        $canonico = '00000000C8012F9B';
        $this->assertSame($canonico, ClienteSearchService::formatoCorto($canonico));
    }
}
