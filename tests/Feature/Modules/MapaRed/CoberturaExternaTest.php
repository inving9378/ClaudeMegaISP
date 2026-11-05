<?php

namespace Tests\Feature\Modules\MapaRed;

use App\Models\ApiExternoClave;
use App\Modules\Addons\MapaRed\Services\MapaRedCoberturaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

/**
 * Item #1159: la consulta pública de cobertura solo abre con una clave vigente, contesta solo
 * {cobertura, distancia_m} (nada de la red) y limita las peticiones por clave.
 * Correr SOLO por nombre: usa megaisp_test dentro de una transacción que se deshace.
 */
class CoberturaExternaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const URL = '/api/externo/cobertura?lat=19.7217&lng=-99.0921';

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('api_externo_claves')) {
            $this->markTestSkipped('megaisp_test sin la tabla api_externo_claves (migración 2026_11_04_120000 pendiente).');
        }
    }

    /** El servicio real del mapa, reemplazado por uno que devuelve lo que se le diga. */
    private function servicioDevuelve(array $respuesta): void
    {
        $this->mock(MapaRedCoberturaService::class, function ($m) use ($respuesta) {
            $m->shouldReceive('consultarPunto')->andReturn($respuesta);
        });
    }

    public function test_sin_clave_o_con_clave_mala_o_revocada_no_contesta(): void
    {
        $this->servicioDevuelve(['cobertura' => true, 'nap_mas_cercana' => null]);

        $this->getJson(self::URL)->assertStatus(401)->assertJson(['error' => 'no_autorizado'])
            ->assertHeader('WWW-Authenticate', 'Bearer');
        $this->getJson(self::URL, ['Authorization' => 'Bearer mgx_inventada'])->assertStatus(401);

        [$registro, $clave] = ApiExternoClave::generar('UNI-PRUEBA revocada', 'cobertura');
        $registro->update(['revocada_at' => now()]);
        $this->getJson(self::URL, ['Authorization' => "Bearer {$clave}"])->assertStatus(401);
    }

    public function test_con_clave_contesta_solo_cobertura_y_distancia(): void
    {
        $this->servicioDevuelve([
            'cobertura'       => true,
            'nap_mas_cercana' => ['id' => 99, 'nombre' => 'NAP SECRETA', 'puertos_libres' => 3, 'distancia_metros' => 143.4],
        ]);
        [$registro, $clave] = ApiExternoClave::generar('UNI-PRUEBA Sofía', 'cobertura');

        $r = $this->get(self::URL, ['Authorization' => "Bearer {$clave}"]);

        $r->assertOk()->assertExactJson(['cobertura' => true, 'distancia_m' => 143]);
        $this->assertStringNotContainsString('NAP SECRETA', $r->getContent());
        $this->assertFalse($r->headers->has('Set-Cookie'), 'Una consulta pública no debe abrir sesión.');

        $registro->refresh();
        $this->assertSame(1, $registro->usos);
        $this->assertNotNull($registro->ultimo_uso_at);
        $this->assertNotSame($clave, $registro->huella, 'La clave no se guarda tal cual.');
    }

    public function test_sin_cajas_libres_da_falso_y_distancia_nula(): void
    {
        $this->servicioDevuelve(['cobertura' => false, 'nap_mas_cercana' => null]);
        [, $clave] = ApiExternoClave::generar('UNI-PRUEBA sin cajas', 'cobertura');

        $this->getJson(self::URL, ['Authorization' => "Bearer {$clave}"])
            ->assertOk()->assertExactJson(['cobertura' => false, 'distancia_m' => null]);
    }

    public function test_parametros_malos_dan_422(): void
    {
        $this->servicioDevuelve(['cobertura' => false, 'nap_mas_cercana' => null]);
        [, $clave] = ApiExternoClave::generar('UNI-PRUEBA parámetros', 'cobertura');
        $h = ['Authorization' => "Bearer {$clave}"];

        $this->getJson('/api/externo/cobertura', $h)->assertStatus(422)->assertJson(['error' => 'parametros_invalidos']);
        $this->getJson('/api/externo/cobertura?lat=200&lng=-99', $h)->assertStatus(422);
        $this->getJson('/api/externo/cobertura?lat=abc&lng=-99', $h)->assertStatus(422);
    }

    public function test_limite_por_clave(): void
    {
        config(['api_externo.limite_por_clave_minuto' => 2]);
        $this->servicioDevuelve(['cobertura' => false, 'nap_mas_cercana' => null]);
        [, $clave] = ApiExternoClave::generar('UNI-PRUEBA límite', 'cobertura');
        $h = ['Authorization' => "Bearer {$clave}"];

        $this->getJson(self::URL, $h)->assertOk();
        $this->getJson(self::URL, $h)->assertOk();
        $this->getJson(self::URL, $h)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_alcance_desconocido_no_se_puede_crear(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ApiExternoClave::generar('UNI-PRUEBA', 'clientes');
    }
}
