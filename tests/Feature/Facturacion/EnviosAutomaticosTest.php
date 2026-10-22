<?php

namespace Tests\Feature\Facturacion;

use App\Models\BillingConfig;
use App\Models\User;
use App\Services\Finance\Billing\EnviosAutomaticos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Configuración › Finanzas › «Envíos automáticos»: pausa y reactivación de billing_config.envio_pausado
 * con las tres opciones para lo acumulado, solo super-administrador y con auditoría. BD de pruebas
 * dentro de una transacción que se revierte; no se envía nada.
 */
class EnviosAutomaticosTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private EnviosAutomaticos $envios;

    private int $clienteId;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('billing_notifications')->delete();
        BillingConfig::current()->forceFill(['envio_pausado' => true, 'envio_lote_por_hora' => null, 'envio_lote_hasta' => null])->save();
        $this->envios = app(EnviosAutomaticos::class);
        $this->clienteId = (int) DB::table('clients')->min('id');
        $this->assertGreaterThan(0, $this->clienteId, 'La BD de pruebas necesita al menos un cliente.');
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

    private function aviso(string $tipo, int $horas, string $estado = 'en_espera', ?int $enviadoHaceMin = null, bool $conCorreo = true): int
    {
        return DB::table('billing_notifications')->insertGetId([
            'client_id' => $this->clienteId, 'document_type' => $tipo, 'status' => $estado, 'send_by_email' => $conCorreo, 'email' => 'nadie@example.test',
            'notificar_despues_de' => now()->subHours($horas), 'enviado_at' => $enviadoHaceMin !== null ? now()->subMinutes($enviadoHaceMin) : null,
            'created_at' => now()->subHours($horas), 'updated_at' => now()->subHours($horas),
        ]);
    }

    public function test_el_estado_cuenta_la_cola_por_tipo_y_la_mas_vieja(): void
    {
        $this->aviso('prefactura', 100);
        $this->aviso('prefactura', 2);
        $this->aviso('recibo', 50);
        $this->aviso('ticket', 1, 'enviado', 30);

        $estado = $this->envios->estado();
        $porTipo = collect($estado['cola']['tipos'])->keyBy('tipo');

        $this->assertTrue($estado['pausado']);
        $this->assertSame([2, 1], [$porTipo['prefactura']['total'], $porTipo['prefactura']['viejas']]);
        $this->assertSame([1, 1], [$porTipo['recibo']['total'], $porTipo['recibo']['viejas']]);
        $this->assertSame(0, $porTipo['ticket']['total'], 'Lo ya enviado no cuenta como cola.');
        $this->assertEqualsWithDelta(now()->subHours(100)->timestamp, strtotime($porTipo['prefactura']['mas_vieja']), 5);
        $this->assertSame(3, $estado['cola']['total']);
        $this->assertArrayHasKey('encendido', $estado['proforma_anticipada']);
        $this->assertArrayHasKey('dias', $estado['proforma_anticipada']);
    }

    public function test_reactivar_descartando_lo_viejo_cancela_sin_borrar_audita_y_se_puede_revertir(): void
    {
        $admin = $this->usuario('super-administrator');
        $viejo1 = $this->aviso('prefactura', 72);
        $viejo2 = $this->aviso('recibo', 49);
        $nuevo = $this->aviso('ticket', 3);

        $r = $this->envios->reactivar($admin, EnviosAutomaticos::DESCARTAR_VIEJOS);

        $config = BillingConfig::current()->fresh();
        $this->assertFalse($config->envio_pausado);
        $this->assertSame($admin->id, (int) $config->envio_pausado_cambiado_por);
        $this->assertNotNull($config->envio_pausado_cambiado_at);
        $this->assertSame(2, $r['descartadas']);
        $this->assertSame(['cancelado', 'cancelado', 'en_espera'],
            DB::table('billing_notifications')->whereIn('id', [$viejo1, $viejo2, $nuevo])->orderBy('id')->pluck('status')->all());
        $this->assertSame(3, DB::table('billing_notifications')->whereIn('id', [$viejo1, $viejo2, $nuevo])->count(), 'No se borra nada.');
        $this->assertSame(1, DB::table('activity_log')->where('log_name', EnviosAutomaticos::LOG)->where('causer_id', $admin->id)->count());

        $archivo = $r['archivo_para_revertir'];
        try {
            Artisan::call('billing:cancelar-colas', ['--revertir' => $archivo, '--ejecutar' => true]);
            $this->assertSame(['en_espera', 'en_espera'], DB::table('billing_notifications')->whereIn('id', [$viejo1, $viejo2])->pluck('status')->all());
        } finally {
            Storage::disk('local')->delete(preg_replace('#^storage/app/#', '', $archivo));
        }
    }

    public function test_reactivar_en_lotes_pone_tope_por_hora_y_se_quita_solo_al_vaciar_lo_acumulado(): void
    {
        $admin = $this->usuario('Super Administrador');
        $acumulado = $this->aviso('prefactura', 5);
        $this->aviso('ticket', 5, 'en_espera', null, false); // sin correo: nunca sale y no debe retener el ritmo
        foreach (range(1, 4) as $i) {
            $this->aviso('recibo', 1, 'enviado', 10);
        }
        $this->aviso('recibo', 3, 'enviado', 90); // fuera de la última hora

        $this->envios->reactivar($admin, EnviosAutomaticos::LOTES_POR_HORA, 10);
        $this->assertSame(6, $this->envios->cupoPorRitmo());

        DB::table('billing_notifications')->where('id', $acumulado)->update(['status' => 'enviado', 'enviado_at' => now()]);
        $this->assertNull($this->envios->cupoPorRitmo(), 'Sin acumulado, el ritmo se quita.');
        $this->assertNull(BillingConfig::current()->fresh()->envio_lote_por_hora);
    }

    public function test_el_envio_se_detiene_cuando_el_ritmo_por_hora_esta_lleno(): void
    {
        $admin = $this->usuario('super-administrator');
        $pendiente = $this->aviso('prefactura', 30);
        foreach (range(1, 10) as $i) {
            $this->aviso('recibo', 1, 'enviado', 5);
        }
        $this->envios->reactivar($admin, EnviosAutomaticos::LOTES_POR_HORA, 10);

        Artisan::call('billing:send-pending-notifications');

        $this->assertStringContainsString('Ritmo por hora alcanzado', Artisan::output());
        $this->assertSame('en_espera', DB::table('billing_notifications')->where('id', $pendiente)->value('status'));
    }

    public function test_solo_super_administrador_y_con_confirmacion_escrita(): void
    {
        $dev = $this->usuario('DESARROLLADOR');
        $admin = $this->usuario('super-administrator');

        try {
            $this->envios->reactivar($dev, EnviosAutomaticos::ENVIAR_TODO);
            $this->fail('DESARROLLADOR no debe poder reactivar.');
        } catch (AuthorizationException) {
        }
        $this->actingAs($dev)->getJson('/configuracion/envios-automaticos/estado')->assertStatus(403);

        $this->actingAs($admin)->getJson('/configuracion/envios-automaticos/estado')->assertOk()->assertJsonPath('pausado', true);
        $enPanel = fn (User $u) => collect($this->actingAs($u)->getJson('/api/modules/config-sections')->json('sections_flat'))->contains('key', 'envios_automaticos');
        $this->assertTrue($enPanel($admin), 'La sección aparece en Configuración para super-administrador.');
        $this->assertFalse($enPanel($dev), 'Y no para DESARROLLADOR.');
        $this->actingAs($admin)->postJson('/configuracion/envios-automaticos/reactivar', ['opcion' => 'enviar_todo', 'confirmacion' => 'si'])
            ->assertStatus(422);
        $this->assertTrue(BillingConfig::current()->fresh()->envio_pausado, 'Sin la palabra escrita no cambia nada.');

        $this->actingAs($admin)->postJson('/configuracion/envios-automaticos/reactivar', ['opcion' => 'enviar_todo', 'confirmacion' => 'REACTIVAR'])
            ->assertOk()->assertJsonPath('estado.pausado', false);
        $this->actingAs($admin)->postJson('/configuracion/envios-automaticos/pausar', ['confirmacion' => 'PAUSAR'])
            ->assertOk()->assertJsonPath('estado.pausado', true);
        $this->assertSame(2, DB::table('activity_log')->where('log_name', EnviosAutomaticos::LOG)->where('causer_id', $admin->id)->count());
    }

    public function test_no_reactiva_dos_veces(): void
    {
        $admin = $this->usuario('super-administrator');
        $this->envios->reactivar($admin, EnviosAutomaticos::ENVIAR_TODO);

        $this->expectException(InvalidArgumentException::class);
        $this->envios->reactivar($admin, EnviosAutomaticos::ENVIAR_TODO);
    }
}
