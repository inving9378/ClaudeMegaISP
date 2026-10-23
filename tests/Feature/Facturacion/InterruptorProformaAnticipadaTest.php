<?php

namespace Tests\Feature\Facturacion;

use App\Models\BillingConfig;
use App\Models\User;
use App\Services\Finance\Billing\EnviosAutomaticos;
use App\Services\Finance\Billing\InterruptorProformaAnticipada;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * #581: interruptor de la proforma anticipada en Configuración › Finanzas › «Envíos automáticos»
 * (billing_config.proforma_anticipada), con los días de anticipo, confirmación, solo
 * super-administrador y auditoría. BD de pruebas en transacción revertida; no se genera ninguna proforma.
 */
class InterruptorProformaAnticipadaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const URL = '/configuracion/envios-automaticos';

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        BillingConfig::current()->forceFill(['proforma_anticipada' => false, 'envio_prefactura_dias' => 5,
            'proforma_anticipada_cambiada_por' => null, 'proforma_anticipada_cambiada_at' => null,
            'envio_prefactura_dias_cambiado_por' => null, 'envio_prefactura_dias_cambiado_at' => null])->save();
        InterruptorProformaAnticipada::olvidar();
    }

    private function usuario(string $rol): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => $rol, 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => bcrypt(Str::random(20)), 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::find($id);
        $user->assignRole($rol);

        return $user;
    }

    private function auditoria(User $user): array
    {
        return DB::table('activity_log')->where('log_name', EnviosAutomaticos::LOG)->where('causer_id', $user->id)
            ->orderBy('id')->get(['description', 'properties'])
            ->map(fn ($f) => [$f->description, json_decode($f->properties, true)])->all();
    }

    public function test_encender_cancelar_cambiar_dias_y_apagar(): void
    {
        $dev = $this->usuario('DESARROLLADOR');
        $admin = $this->usuario('super-administrator');
        $encender = ['dias' => 7, 'confirmacion' => 'ENCENDER'];

        // Solo super-administrador.
        try {
            app(InterruptorProformaAnticipada::class)->encender($dev, 7);
            $this->fail('DESARROLLADOR no debe poder encender la proforma anticipada.');
        } catch (AuthorizationException) {
        }
        foreach (['proforma/encender' => $encender, 'proforma/apagar' => [], 'proforma/dias' => ['dias' => 9]] as $ruta => $cuerpo) {
            $this->actingAs($dev)->postJson(self::URL . "/{$ruta}", $cuerpo)->assertStatus(403);
        }

        // Cancelar: sin la palabra, o con días fuera de rango, no cambia nada.
        $this->actingAs($admin)->postJson(self::URL . '/proforma/encender', ['confirmacion' => 'si'] + $encender)->assertStatus(422);
        $this->actingAs($admin)->postJson(self::URL . '/proforma/encender', ['dias' => 61] + $encender)->assertStatus(422);
        $this->actingAs($admin)->getJson(self::URL . '/estado')->assertOk()
            ->assertJsonPath('proforma_anticipada.encendido', false)->assertJsonPath('proforma_anticipada.dias', 5);
        $this->assertFalse(InterruptorProformaAnticipada::encendida());

        // Encender con 7 días: queda en billing_config con quién y cuándo, y los días también.
        $this->actingAs($admin)->postJson(self::URL . '/proforma/encender', $encender)->assertOk()
            ->assertJsonPath('estado.proforma_anticipada.encendido', true)
            ->assertJsonPath('estado.proforma_anticipada.dias', 7)
            ->assertJsonPath('estado.proforma_anticipada.cambiado_por', 'Sintético super-administrator')
            ->assertJsonPath('estado.proforma_anticipada.dias_cambiados_por', 'Sintético super-administrator');
        $config = BillingConfig::current()->fresh();
        $this->assertSame([true, 7, $admin->id, $admin->id], [$config->proforma_anticipada, (int) $config->envio_prefactura_dias,
            (int) $config->proforma_anticipada_cambiada_por, (int) $config->envio_prefactura_dias_cambiado_por]);
        $this->assertNotNull($config->proforma_anticipada_cambiada_at);
        $this->assertTrue(InterruptorProformaAnticipada::encendida());
        $this->actingAs($admin)->postJson(self::URL . '/proforma/encender', $encender)->assertStatus(422);

        // Cambiar días: con su propia confirmación en la pantalla; el mismo valor no deja rastro.
        $this->actingAs($admin)->postJson(self::URL . '/proforma/dias', ['dias' => 10])->assertOk()
            ->assertJsonPath('estado.proforma_anticipada.dias', 10)->assertJsonPath('cambio', true);
        $this->actingAs($admin)->postJson(self::URL . '/proforma/dias', ['dias' => 10])->assertOk()->assertJsonPath('cambio', false);
        $this->actingAs($admin)->postJson(self::URL . '/proforma/dias', ['dias' => 61])->assertStatus(422);

        // Apagar: sin palabra, inmediato; los días quedan guardados.
        $this->actingAs($admin)->postJson(self::URL . '/proforma/apagar')->assertOk()
            ->assertJsonPath('estado.proforma_anticipada.encendido', false)->assertJsonPath('estado.proforma_anticipada.dias', 10);
        $this->actingAs($admin)->postJson(self::URL . '/proforma/apagar')->assertStatus(422);
        $this->assertFalse(InterruptorProformaAnticipada::encendida());

        $auditoria = $this->auditoria($admin);
        $this->assertSame(['Proforma anticipada encendida', 'Días de anticipo de la proforma cambiados', 'Proforma anticipada apagada'],
            array_column($auditoria, 0));
        $this->assertEquals(['antes' => 'apagada', 'despues' => 'encendida', 'dias_antes' => 5, 'dias' => 7], $auditoria[0][1]);
        $this->assertSame([7, 10, 'Configuración › Envíos automáticos'], [$auditoria[1][1]['antes'], $auditoria[1][1]['despues'], $auditoria[1][1]['origen']]);
        $this->assertSame([], $this->auditoria($dev), 'Lo rechazado no deja rastro.');
    }

    public function test_los_dias_desde_notificaciones_finanzas_tambien_quedan_auditados(): void
    {
        $admin = $this->usuario('super-administrator');
        $url = '/configuracion/finance-notification/envio-prefactura';

        $this->actingAs($admin)->getJson($url)->assertOk()->assertJsonPath('interruptor_encendido', false)->assertJsonPath('dias', 5);
        $this->actingAs($admin)->postJson($url, ['dias' => 8])->assertOk()->assertJsonPath('dias', 8);

        $config = BillingConfig::current()->fresh();
        $this->assertSame([8, $admin->id, false], [(int) $config->envio_prefactura_dias, (int) $config->envio_prefactura_dias_cambiado_por,
            $config->proforma_anticipada], 'Cambia solo los días, nunca el interruptor.');
        // assertEquals: MySQL reordena las llaves del JSON.
        $this->assertEquals([['Días de anticipo de la proforma cambiados',
            ['antes' => 5, 'despues' => 8, 'origen' => 'Notificaciones Finanzas › Facturas proforma', 'proforma_anticipada' => 'apagada']]],
            $this->auditoria($admin));
    }

    public function test_el_env_solo_da_el_valor_inicial(): void
    {
        $this->assertTrue(InterruptorProformaAnticipada::valorInicial(true));
        $this->assertFalse(InterruptorProformaAnticipada::valorInicial(null));

        config(['billing.proforma_anticipada' => true]);
        $this->assertFalse(InterruptorProformaAnticipada::encendida(), 'En marcha manda billing_config, no el .env.');
    }
}
