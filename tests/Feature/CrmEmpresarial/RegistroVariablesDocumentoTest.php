<?php

namespace Tests\Feature\CrmEmpresarial;

use App\Modules\Addons\CrmEmpresarial\Services\RegistroVariablesDocumento;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

class RegistroVariablesDocumentoTest extends TestCase
{
    use CreatesApplication;

    public function test_sustituye_y_formatea_por_tipo(): void
    {
        $r = (new RegistroVariablesDocumento())->renderizar(
            '{{cliente.razon_social}} paga {{servicio.precio_mensual}} el {{documento.fecha}}',
            ['cliente' => ['razon_social' => 'ACME'], 'servicio' => ['precio_mensual' => 1234.5], 'documento' => ['fecha' => '2026-10-23']]
        );
        $this->assertSame('ACME paga $1,234.50 el 23/10/2026', $r['texto']);
        $this->assertSame([], $r['faltantes']);
    }

    public function test_escapa_html_y_no_ejecuta_codigo(): void
    {
        $r = (new RegistroVariablesDocumento())->renderizar(
            '{{cliente.nombre}} {{ system("ls") }} <?php echo 1; ?> {{cliente.nombre}}',
            ['cliente' => ['nombre' => '<script>alert(1)</script>']]
        );
        $this->assertStringNotContainsString('<script>', $r['texto']);
        $this->assertStringContainsString('&lt;script&gt;', $r['texto']);
        $this->assertStringContainsString('{{ system("ls") }}', $r['texto']);
    }

    public function test_variable_sin_dato_o_desconocida_queda_y_se_reporta(): void
    {
        $r = (new RegistroVariablesDocumento())->renderizar('{{cliente.rfc}} {{foo.bar}}', []);
        $this->assertSame('{{cliente.rfc}} {{foo.bar}}', $r['texto']);
        $this->assertSame(['cliente.rfc', 'foo.bar'], $r['faltantes']);
    }

    public function test_firma_solo_acepta_data_uri_raster(): void
    {
        $s = new RegistroVariablesDocumento();
        $this->assertNull($s->resolver('firma.cliente', ['firma' => ['cliente' => 'http://x/y.png']]));
        $this->assertNull($s->resolver('firma.cliente', ['firma' => ['cliente' => 'data:image/svg+xml;base64,AAAA']]));
        $this->assertStringStartsWith('<img', $s->resolver('firma.cliente', ['firma' => ['cliente' => 'data:image/png;base64,iVBORw0=']]));
    }
}
