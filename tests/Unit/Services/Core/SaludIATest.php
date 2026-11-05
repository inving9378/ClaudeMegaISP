<?php

namespace Tests\Unit\Services\Core;

use App\Services\Core\SaludIA;
use PHPUnit\Framework\TestCase;

/** #1073 — clasificación de fallas y estado «ciega» del registro de salud de la IA (sin base ni red). */
final class SaludIATest extends TestCase
{
    private string $archivo;

    protected function setUp(): void
    {
        $this->archivo = sys_get_temp_dir() . '/salud-ia-' . bin2hex(random_bytes(4)) . '.json';
        SaludIA::usarArchivo($this->archivo);
    }

    protected function tearDown(): void
    {
        @unlink($this->archivo);
        SaludIA::usarArchivo(null);
    }

    public function test_clasifica_las_causas_que_dejan_ciega_a_la_ia(): void
    {
        $this->assertSame(SaludIA::LLAVE_RECHAZADA, SaludIA::clasificar(401, 'invalid x-api-key'));
        $this->assertSame(SaludIA::SIN_SALDO, SaludIA::clasificar(400, 'Your credit balance is too low to access the Anthropic API.'));
        $this->assertSame(SaludIA::SIN_SALDO, SaludIA::clasificar(429, '{"error":{"code":"insufficient_quota"}}'));
        $this->assertSame(SaludIA::LIMITE_GASTO, SaludIA::clasificar(400, 'You have reached your specified workspace API usage limits.'));
        $this->assertSame(SaludIA::CUOTA, SaludIA::clasificar(429, 'rate_limit_error'));
    }

    public function test_las_fallas_pasajeras_no_cuentan(): void
    {
        $this->assertNull(SaludIA::clasificar(529, 'overloaded_error'));
        $this->assertNull(SaludIA::clasificar(500, 'api_error'));
        $this->assertNull(SaludIA::clasificar(400, 'messages.0.content: field required'));

        SaludIA::registrar('anthropic', 'prueba', 529, 'overloaded_error');
        $this->assertSame([], SaludIA::ciegas());
    }

    public function test_una_falla_la_marca_ciega_y_una_respuesta_buena_la_limpia(): void
    {
        SaludIA::registrar('anthropic', 'prueba', 401, 'invalid x-api-key');
        SaludIA::registrar('anthropic', 'prueba', 401, 'invalid x-api-key');

        $ciegas = SaludIA::ciegas();
        $this->assertSame(SaludIA::LLAVE_RECHAZADA, $ciegas['anthropic']['causa']);
        $this->assertSame(2, $ciegas['anthropic']['conteo']);
        $this->assertSame('el proveedor rechaza la llave', $ciegas['anthropic']['texto']);

        SaludIA::registrar('anthropic', 'prueba', 200);
        $this->assertSame([], SaludIA::ciegas());
    }

    public function test_un_cambio_de_causa_reinicia_el_conteo(): void
    {
        SaludIA::registrar('anthropic', 'prueba', 401, 'invalid x-api-key');
        SaludIA::registrar('anthropic', 'prueba', 400, 'Your credit balance is too low');

        $f = SaludIA::ciegas()['anthropic'];
        $this->assertSame(SaludIA::SIN_SALDO, $f['causa']);
        $this->assertSame(1, $f['conteo']);
    }

    public function test_la_cuota_solo_cuenta_si_dura(): void
    {
        SaludIA::registrar('anthropic', 'prueba', 429, 'rate_limit_error');

        $this->assertSame([], SaludIA::ciegas(time()));
        $this->assertArrayHasKey('anthropic', SaludIA::ciegas(time() + SaludIA::CUOTA_SOSTENIDA_SEG + 1));
    }

    public function test_los_proveedores_se_llevan_por_separado(): void
    {
        SaludIA::registrar('openai', 'prueba', 401, 'Incorrect API key');
        SaludIA::registrar('anthropic', 'prueba', 200);

        $this->assertSame(['openai'], array_keys(SaludIA::ciegas()));
    }

    public function test_sin_archivo_configurado_no_truena(): void
    {
        SaludIA::usarArchivo(null);   // sin app no hay config(): se ignora en silencio

        SaludIA::registrar('anthropic', 'prueba', 401, 'invalid x-api-key');
        SaludIA::marcarLatido('anthropic', false, 'x');

        $this->assertSame([], SaludIA::ciegas());
    }
}
