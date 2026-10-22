<?php

namespace Tests\Feature\Facturacion;

use App\Models\BillingConfig;
use App\Models\User;
use App\Services\Finance\Billing\EnviosAutomaticos;
use App\Services\Finance\Billing\ReactivacionEnvios;
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
        BillingConfig::current()->forceFill(['envio_pausado' => true, 'envio_lote_por_hora' => null, 'envio_lote_hasta' => null,
            'reactivacion_nivel' => 'cerrado', 'reactivacion_limite_corrida' => null, 'reactivacion_limite_diario' => null])->save();
        $this->envios = app(EnviosAutomaticos::class);
        $this->clienteId = (int) DB::table('clients')->min('id');
        $this->assertGreaterThan(0, $this->clienteId, 'La BD de pruebas necesita al menos un cliente.');
    }

    /** La carpeta queda sin permiso de escritura, como cuando la creó otro usuario del servidor. */
    private function carpetaSinPermiso(string $dir): void
    {
        Storage::disk('local')->makeDirectory($dir);
        $ruta = Storage::disk('local')->path($dir);
        chmod($ruta, 0500);
        $this->beforeApplicationDestroyed(fn () => @chmod($ruta, 0755));
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

    public function test_encender_descartando_lo_viejo_con_ritmo_prueba_audita_y_se_puede_revertir(): void
    {
        $admin = $this->usuario('super-administrator');
        $viejo1 = $this->aviso('prefactura', 72);
        $viejo2 = $this->aviso('recibo', 49);
        $nuevo = $this->aviso('ticket', 3);

        $r = $this->envios->encender($admin, EnviosAutomaticos::DESCARTAR_VIEJOS, 'prueba');

        $config = BillingConfig::current()->fresh();
        $this->assertTrue($this->envios->encendido());
        $this->assertSame([false, 'prueba', 20, 100], [$config->envio_pausado, $config->reactivacion_nivel,
            $config->reactivacion_limite_corrida, $config->reactivacion_limite_diario]);
        $this->assertSame([$admin->id, $admin->id], [(int) $config->envio_pausado_cambiado_por, (int) $config->reactivacion_cambiada_por]);
        $this->assertSame(2, $r['descartadas']);
        $this->assertSame(['cancelado', 'cancelado', 'en_espera'],
            DB::table('billing_notifications')->whereIn('id', [$viejo1, $viejo2, $nuevo])->orderBy('id')->pluck('status')->all());
        $this->assertSame(3, DB::table('billing_notifications')->whereIn('id', [$viejo1, $viejo2, $nuevo])->count(), 'No se borra nada.');
        $this->assertSame(1, DB::table('activity_log')->where('log_name', EnviosAutomaticos::LOG)->where('description', 'Envíos automáticos encendidos')
            ->where('causer_id', $admin->id)->count());

        $archivo = $r['archivo_para_revertir'];
        try {
            Artisan::call('billing:cancelar-colas', ['--revertir' => $archivo, '--ejecutar' => true]);
            $this->assertSame(['en_espera', 'en_espera'], DB::table('billing_notifications')->whereIn('id', [$viejo1, $viejo2])->pluck('status')->all());
        } finally {
            Storage::disk('local')->delete(preg_replace('#^storage/app/#', '', $archivo));
        }
    }

    public function test_encender_en_lotes_pone_tope_por_hora_y_se_quita_solo_al_vaciar_lo_acumulado(): void
    {
        $admin = $this->usuario('Super Administrador');
        $acumulado = $this->aviso('prefactura', 5);
        $this->aviso('ticket', 5, 'en_espera', null, false); // sin correo: nunca sale y no debe retener el ritmo
        foreach (range(1, 4) as $i) {
            $this->aviso('recibo', 1, 'enviado', 10);
        }
        $this->aviso('recibo', 3, 'enviado', 90); // fuera de la última hora

        $this->envios->encender($admin, EnviosAutomaticos::LOTES_POR_HORA, 'normal', 10);
        $this->assertSame(6, $this->envios->cupoPorRitmo());

        DB::table('billing_notifications')->where('id', $acumulado)->update(['status' => 'enviado', 'enviado_at' => now()]);
        $this->assertNull($this->envios->cupoPorRitmo(), 'Sin acumulado, el ritmo por hora se quita.');
        $this->assertNull(BillingConfig::current()->fresh()->envio_lote_por_hora);
    }

    public function test_el_envio_se_detiene_cuando_el_ritmo_por_hora_esta_lleno(): void
    {
        $admin = $this->usuario('super-administrator');
        $pendiente = $this->aviso('prefactura', 30);
        foreach (range(1, 10) as $i) {
            $this->aviso('recibo', 1, 'enviado', 5);
        }
        $this->envios->encender($admin, EnviosAutomaticos::LOTES_POR_HORA, 'prueba', 10);

        Artisan::call('billing:send-pending-notifications');

        $this->assertStringContainsString('Ritmo por hora alcanzado', Artisan::output());
        $this->assertSame('en_espera', DB::table('billing_notifications')->where('id', $pendiente)->value('status'));
    }

    /** Los cuatro casos del interruptor por la pantalla: encender, cancelar, cambiar ritmo y apagar. */
    public function test_interruptor_encender_cancelar_cambiar_ritmo_y_apagar(): void
    {
        $dev = $this->usuario('DESARROLLADOR');
        $admin = $this->usuario('super-administrator');
        $url = '/configuracion/envios-automaticos';
        $encender = ['opcion' => 'descartar_48h', 'ritmo' => 'prueba', 'confirmacion' => 'REACTIVAR'];

        try {
            $this->envios->encender($dev, EnviosAutomaticos::ENVIAR_TODO, 'prueba');
            $this->fail('DESARROLLADOR no debe poder encender.');
        } catch (AuthorizationException) {
        }
        foreach (['estado' => 'getJson', 'encender' => 'postJson', 'apagar' => 'postJson', 'ritmo' => 'postJson'] as $ruta => $metodo) {
            $this->actingAs($dev)->{$metodo}("{$url}/{$ruta}", $ruta === 'encender' ? $encender : ['ritmo' => 'normal'])->assertStatus(403);
        }
        $enPanel = fn (User $u) => collect($this->actingAs($u)->getJson('/api/modules/config-sections')->json('sections_flat'))->contains('key', 'envios_automaticos');
        $this->assertTrue($enPanel($admin), 'La sección aparece en Configuración para super-administrador.');
        $this->assertFalse($enPanel($dev), 'Y no para DESARROLLADOR.');

        // Cancelar: sin la palabra (o sin ritmo) no se enciende y queda apagado.
        $this->actingAs($admin)->postJson("{$url}/encender", ['confirmacion' => 'si'] + $encender)->assertStatus(422);
        $this->actingAs($admin)->postJson("{$url}/encender", ['ritmo' => 'cerrado'] + $encender)->assertStatus(422);
        $this->actingAs($admin)->getJson("{$url}/estado")->assertOk()->assertJsonPath('encendido', false);
        $this->actingAs($admin)->postJson("{$url}/ritmo", ['ritmo' => 'normal'])->assertStatus(422);

        // Encender.
        $this->actingAs($admin)->postJson("{$url}/encender", $encender)->assertOk()
            ->assertJsonPath('estado.encendido', true)->assertJsonPath('estado.etapa.ritmo', 'prueba')->assertJsonPath('estado.etapa.limite_por_corrida', 20);

        // Cambiar ritmo.
        $this->actingAs($admin)->postJson("{$url}/ritmo", ['ritmo' => 'normal'])->assertOk()
            ->assertJsonPath('estado.etapa.ritmo', 'normal')->assertJsonPath('estado.etapa.sin_tope', true);
        $this->actingAs($admin)->postJson("{$url}/ritmo", ['ritmo' => 'normal'])->assertStatus(422);

        // Apagar: sin palabra, inmediato; el ritmo queda guardado para la próxima vez.
        $this->actingAs($admin)->postJson("{$url}/apagar")->assertOk()
            ->assertJsonPath('estado.encendido', false)->assertJsonPath('estado.pausado', true)->assertJsonPath('estado.etapa.nombre', 'cerrado')
            ->assertJsonPath('estado.etapa.ritmo', 'normal');
        $this->actingAs($admin)->postJson("{$url}/apagar")->assertStatus(422);

        $this->assertSame(['Envíos automáticos encendidos', 'Ritmo de envío cambiado', 'Envíos automáticos apagados'],
            DB::table('activity_log')->where('log_name', EnviosAutomaticos::LOG)->where('causer_id', $admin->id)->orderBy('id')->pluck('description')->all());
    }

    public function test_depurar_cancela_por_tipo_lo_viejo_aunque_este_pausado_y_se_puede_revertir(): void
    {
        $admin = $this->usuario('super-administrator');
        $ticketViejo = $this->aviso('ticket', 100);
        $ticketNuevo = $this->aviso('ticket', 10);
        $reciboViejo = $this->aviso('recibo', 100);
        $this->assertTrue(BillingConfig::current()->envio_pausado, 'Funciona con los envíos pausados.');

        $conteo = collect($this->envios->conteoDepuracion(48)['tipos'])->pluck('n', 'tipo');
        $this->assertSame(['prefactura' => 0, 'recibo' => 1, 'ticket' => 1], $conteo->all());

        $r = $this->envios->depurar($admin, 48, 'ticket', 1);

        $this->assertSame(1, $r['canceladas']);
        $estados = DB::table('billing_notifications')->whereIn('id', [$ticketViejo, $ticketNuevo, $reciboViejo])->pluck('status', 'id');
        $this->assertSame(['cancelado', 'en_espera', 'en_espera'], [$estados[$ticketViejo], $estados[$ticketNuevo], $estados[$reciboViejo]]);
        $this->assertTrue(BillingConfig::current()->fresh()->envio_pausado, 'Depurar no cambia la pausa.');
        $this->assertSame(1, DB::table('activity_log')->where('log_name', EnviosAutomaticos::LOG)->where('description', 'Cola de avisos depurada')->count());

        try {
            Artisan::call('billing:cancelar-colas', ['--revertir' => $r['archivo_para_revertir'], '--ejecutar' => true]);
            $this->assertSame('en_espera', DB::table('billing_notifications')->where('id', $ticketViejo)->value('status'));
        } finally {
            Storage::disk('local')->delete(preg_replace('#^storage/app/#', '', $r['archivo_para_revertir']));
        }
    }

    public function test_depurar_exige_super_admin_la_palabra_y_el_conteo_vigente(): void
    {
        $dev = $this->usuario('DESARROLLADOR');
        $admin = $this->usuario('Super Administrador');
        $viejo = $this->aviso('prefactura', 72);

        $this->actingAs($dev)->getJson('/configuracion/envios-automaticos/depurar/conteo?horas=48')->assertStatus(403);
        $this->actingAs($dev)->postJson('/configuracion/envios-automaticos/depurar', ['horas' => 48, 'esperado' => 1, 'confirmacion' => 'DEPURAR'])->assertStatus(403);

        $this->actingAs($admin)->getJson('/configuracion/envios-automaticos/depurar/conteo?horas=48')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($admin)->postJson('/configuracion/envios-automaticos/depurar', ['horas' => 48, 'esperado' => 1, 'confirmacion' => 'si'])->assertStatus(422);
        $this->actingAs($admin)->postJson('/configuracion/envios-automaticos/depurar', ['horas' => 48, 'esperado' => 5, 'confirmacion' => 'DEPURAR'])
            ->assertStatus(422)->assertJsonPath('conteo.total', 1);
        $this->assertSame('en_espera', DB::table('billing_notifications')->where('id', $viejo)->value('status'), 'Si el conteo cambió no se toca nada.');

        $r = $this->actingAs($admin)->postJson('/configuracion/envios-automaticos/depurar', ['horas' => 48, 'esperado' => 1, 'confirmacion' => 'DEPURAR'])
            ->assertOk()->assertJsonPath('canceladas', 1);
        $this->assertSame('cancelado', DB::table('billing_notifications')->where('id', $viejo)->value('status'));
        Storage::disk('local')->delete(preg_replace('#^storage/app/#', '', $r->json('archivo_para_revertir')));
    }

    public function test_no_enciende_dos_veces(): void
    {
        $admin = $this->usuario('super-administrator');
        $this->envios->encender($admin, EnviosAutomaticos::ENVIAR_TODO, 'prueba');

        $this->expectException(InvalidArgumentException::class);
        $this->envios->encender($admin, EnviosAutomaticos::ENVIAR_TODO, 'normal');
    }

    public function test_si_no_se_puede_guardar_el_archivo_para_revertir_no_se_depura_nada(): void
    {
        Storage::fake('local');
        $this->carpetaSinPermiso('billing/manifiestos');
        $admin = $this->usuario('super-administrator');
        $id = $this->aviso('recibo', 100);

        try {
            $this->envios->depurar($admin, 48, null, 1);
            $this->fail('Debía fallar sin archivo para revertir.');
        } catch (\App\Support\ArchivoObligatorioException $e) {
            $this->assertStringContainsString('No se cambió nada', $e->getMessage());
        }
        $this->assertSame('en_espera', DB::table('billing_notifications')->where('id', $id)->value('status'));

        $this->actingAs($admin)->postJson('/configuracion/envios-automaticos/depurar', ['horas' => 48, 'esperado' => 1, 'confirmacion' => 'DEPURAR'])
            ->assertStatus(500)->assertJsonPath('message', fn ($m) => str_contains($m, 'No se pudo guardar'));
        $this->assertSame('en_espera', DB::table('billing_notifications')->where('id', $id)->value('status'));
        $this->assertSame(0, DB::table('activity_log')->where('description', 'Cola de avisos depurada')->where('causer_id', $admin->id)->count());
    }

    public function test_el_ritmo_manda_en_el_envio_y_apagado_es_cerrado(): void
    {
        $admin = $this->usuario('super-administrator');
        $etapa = fn () => new ReactivacionEnvios();
        $this->assertSame('cerrado', $etapa()->etapa());

        $this->envios->encender($admin, EnviosAutomaticos::ENVIAR_TODO, 'prueba');
        $this->assertSame(['todos', 'prueba', false], [$etapa()->etapa(), $etapa()->nivel(), $etapa()->sinTope()]);
        $this->assertSame([20, 5, 0], [$etapa()->cupo(0, 0), $etapa()->cupo(0, 95), $etapa()->cupo(20, 20)]);
        $this->assertTrue($etapa()->permiteCliente($this->clienteId)[0], 'Prueba abre a todos los clientes, con tope.');
        foreach (range(1, 3) as $i) {
            $this->aviso('recibo', 30);
        }
        Artisan::call('billing:send-pending-notifications', ['--dry-run' => true]);
        $this->assertStringContainsString('etapa prueba, cupo de esta corrida 20', Artisan::output());

        $this->envios->cambiarRitmo($admin, 'normal');
        $this->assertSame(PHP_INT_MAX, $etapa()->cupo(500, 5000));

        $this->envios->apagar($admin);
        $this->assertSame([false, 'reactivación cerrada'], $etapa()->permiteCliente($this->clienteId), 'Apagado no sale nada, ni por las otras colas.');
    }

    public function test_no_existe_encendido_pero_cerrado(): void
    {
        $admin = $this->usuario('super-administrator');
        BillingConfig::current()->forceFill(['envio_pausado' => false, 'reactivacion_nivel' => 'cerrado'])->save();

        $this->assertFalse($this->envios->estado()['encendido'], 'Sin ritmo cuenta como apagado.');
        $this->envios->apagar($admin);
        $this->assertTrue(BillingConfig::current()->fresh()->envio_pausado, 'Apagar deja la pausa puesta.');
        $this->expectException(InvalidArgumentException::class);
        $this->envios->encender($admin, EnviosAutomaticos::ENVIAR_TODO, 'cerrado');
    }

    public function test_el_env_solo_da_el_valor_inicial(): void
    {
        $this->assertSame('prueba', ReactivacionEnvios::nivelInicial('todos'));
        foreach (['cerrado', 'piloto', 'zona', null, ''] as $etapa) {
            $this->assertSame('cerrado', ReactivacionEnvios::nivelInicial($etapa));
        }
        config(['billing.reactivacion.etapa' => 'todos']);
        $this->assertSame('cerrado', (new ReactivacionEnvios())->etapa(), 'En marcha manda billing_config, no el .env.');
    }

    public function test_si_no_se_guarda_el_archivo_para_revertir_no_se_enciende(): void
    {
        Storage::fake('local');
        $this->carpetaSinPermiso('billing/manifiestos');
        $admin = $this->usuario('super-administrator');
        $id = $this->aviso('recibo', 100);

        try {
            $this->envios->encender($admin, EnviosAutomaticos::DESCARTAR_VIEJOS, 'prueba');
            $this->fail('Debía fallar sin archivo para revertir.');
        } catch (\App\Support\ArchivoObligatorioException) {
        }
        $config = BillingConfig::current()->fresh();
        $this->assertSame([true, 'cerrado'], [$config->envio_pausado, $config->reactivacion_nivel], 'Sigue apagado.');
        $this->assertSame('en_espera', DB::table('billing_notifications')->where('id', $id)->value('status'));
        $this->assertSame(0, DB::table('activity_log')->where('description', 'Envíos automáticos encendidos')->where('causer_id', $admin->id)->count());
    }

    public function test_fuera_de_produccion_avisa_y_bloquea_el_whatsapp_de_facturacion(): void
    {
        config(['updates.enabled' => false, 'mail.interceptar.destino' => 'pruebas@example.test', 'billing.whatsapp_fallback_enabled' => true]);
        $estado = $this->envios->estado();
        $this->assertSame('p•••@example.test', $estado['entorno_pruebas']['buzon']);
        $whatsapp = new ReactivacionEnvios(['etapa' => 'todos', 'whatsapp' => true, 'limite_por_corrida' => 1, 'limite_diario' => 1]);
        $this->assertFalse($whatsapp->permiteWhatsApp(), 'Fuera de producción el WhatsApp de facturación no sale.');
        $this->assertSame('apagado', app(\App\Services\Finance\Billing\DocumentoWhatsAppService::class)->enviar(new \App\Models\Invoice())['status']);

        config(['updates.enabled' => true]);
        $this->assertNull($this->envios->estado()['entorno_pruebas'], 'En producción no hay aviso.');
        $this->assertTrue($whatsapp->permiteWhatsApp());
    }
}
