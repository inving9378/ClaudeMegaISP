<?php

namespace Tests\Feature\Facturacion;

use App\Models\BillingConfig;
use App\Models\User;
use App\Services\Finance\Billing\ColaAvisos;
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
 * #579 «Ver detalle de la cola»: lista con filtros y cancelar o reactivar lo seleccionado, reversible
 * y auditado. BD de pruebas dentro de una transacción que se revierte; no se envía nada.
 */
class ColaAvisosTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private ColaAvisos $cola;

    private int $clienteId;

    private array $archivos = [];

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('billing_notifications')->delete();
        BillingConfig::current()->forceFill(['envio_pausado' => true, 'envio_lote_por_hora' => null, 'envio_lote_hasta' => null])->save();
        $this->cola = app(ColaAvisos::class);
        $this->clienteId = (int) DB::table('clients')->min('id');
        $this->assertGreaterThan(0, $this->clienteId, 'La BD de pruebas necesita al menos un cliente.');
    }

    protected function tearDown(): void
    {
        foreach ($this->archivos as $archivo) {
            Storage::disk('local')->delete(preg_replace('#^storage/app/#', '', $archivo));
        }
        parent::tearDown();
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

    private function aviso(string $tipo, int $horas, string $estado = 'en_espera', array $extra = []): int
    {
        return DB::table('billing_notifications')->insertGetId($extra + [
            'client_id' => $this->clienteId, 'document_type' => $tipo, 'status' => $estado, 'send_by_email' => $tipo !== 'ticket',
            'email' => 'nadie@example.test', 'notificar_despues_de' => now()->subHours($horas),
            'created_at' => now()->subHours($horas), 'updated_at' => now()->subHours($horas),
        ]);
    }

    private function estados(array $ids): array
    {
        return DB::table('billing_notifications')->whereIn('id', $ids)->orderBy('id')->pluck('status', 'id')->all();
    }

    public function test_lista_paginada_con_filtros_canal_y_monto(): void
    {
        for ($i = 0; $i < 52; $i++) {
            $this->aviso('recibo', 72 + $i);
        }
        $ticket = $this->aviso('ticket', 3);
        $sinCorreo = $this->aviso('prefactura', 5, 'en_espera', ['email' => null]);
        $factura = DB::table('invoices')->whereNotNull('total')->first(['id', 'total']);
        $conFactura = $this->aviso('prefactura', 1, 'cancelado', ['invoice_id' => $factura?->id]);

        $todo = $this->cola->listar(['estado' => 'en_espera']);
        $this->assertSame(54, $todo['total']);
        $this->assertSame([50, 2], [count($todo['filas']), $todo['paginas']]);
        $this->assertSame($ticket, $todo['filas'][0]['id'], 'Lo más nuevo va primero.');
        $this->assertSame('caja', $todo['filas'][0]['canal'], 'Un ticket es solo para imprimir en caja.');
        $this->assertSame('ninguno', collect($todo['filas'])->firstWhere('id', $sinCorreo)['canal']);
        $this->assertSame('correo', $todo['filas'][2]['canal']);
        $this->assertCount(4, $this->cola->listar(['estado' => 'en_espera'], 2)['filas']);

        $this->assertSame(52, $this->cola->listar(['tipo' => 'recibo', 'horas' => 48])['total']);
        $this->assertSame(0, $this->cola->listar(['tipo' => 'recibo', 'horas' => 200])['total']);
        $this->assertSame(1, $this->cola->listar(['estado' => 'cancelado'])['total']);
        $hoy = now()->format('Y-m-d');
        $deHoy = collect([3, 5, 1])->filter(fn ($h) => now()->subHours($h)->format('Y-m-d') === $hoy)->count();
        $this->assertSame($deHoy, $this->cola->listar(['desde' => $hoy, 'hasta' => $hoy])['total'], 'Los recibos tienen 72 h o más.');
        $this->assertSame(55, $this->cola->listar(['cliente' => (string) $this->clienteId])['total']);
        $this->assertSame(0, $this->cola->listar(['cliente' => '999999999'])['total']);
        $nombre = DB::table('client_main_information')->where('client_id', $this->clienteId)->value('name');
        if ($nombre) {
            $this->assertSame(55, $this->cola->listar(['cliente' => mb_substr($nombre, 0, 4)])['total']);
        }
        if ($factura) {
            $fila = $this->cola->listar(['estado' => 'cancelado'])['filas'][0];
            $this->assertSame([$conFactura, round((float) $factura->total, 2)], [$fila['id'], $fila['monto']]);
        }
    }

    public function test_cancelar_por_filas_solo_toca_lo_cancelable_audita_y_se_revierte(): void
    {
        $admin = $this->usuario('super-administrator');
        $espera = $this->aviso('prefactura', 10);
        $error = $this->aviso('recibo', 10, 'error');
        $enviado = $this->aviso('recibo', 10, 'enviado');
        $cancelado = $this->aviso('recibo', 10, 'cancelado');
        $ids = [$espera, $error, $enviado, $cancelado];

        $conteo = $this->cola->conteo('cancelar', ['ids' => $ids]);
        $this->assertSame([4, 2, 2], [$conteo['seleccionados'], $conteo['total'], $conteo['no_aplican']]);

        $r = $this->cola->aplicar($admin, 'cancelar', ['ids' => $ids], 2);
        $this->archivos[] = $r['archivo_para_revertir'];
        $this->assertSame(['cancelado', 'cancelado', 'enviado', 'cancelado'], array_values($this->estados($ids)));
        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'facturacion-envios')
            ->where('description', 'Avisos cancelados desde el detalle de la cola')->where('causer_id', $admin->id)->count());

        Artisan::call('billing:cancelar-colas', ['--revertir' => $r['archivo_para_revertir'], '--ejecutar' => true]);
        $this->assertSame(['en_espera', 'error', 'enviado', 'cancelado'], array_values($this->estados($ids)));
    }

    public function test_reactivar_vuelve_a_espera_sin_tickets_ni_enviados_y_se_revierte(): void
    {
        $admin = $this->usuario('super-administrator');
        $cancelado = $this->aviso('prefactura', 10, 'cancelado');
        $error = $this->aviso('recibo', 10, 'error');
        $ticket = $this->aviso('ticket', 10, 'cancelado');
        $enviado = $this->aviso('recibo', 10, 'enviado');
        $ids = [$cancelado, $error, $ticket, $enviado];

        $this->assertSame(2, $this->cola->conteo('reactivar', ['ids' => $ids])['total']);
        $r = $this->cola->aplicar($admin, 'reactivar', ['ids' => $ids], 2);
        $this->archivos[] = $r['archivo_para_revertir'];
        $this->assertStringContainsString('pausados', $r['message'], 'Si los envíos están pausados, lo dice.');
        $this->assertSame(['en_espera', 'en_espera', 'cancelado', 'enviado'], array_values($this->estados($ids)));
        $this->assertStringContainsString('Reactivada a mano', (string) DB::table('billing_notifications')->where('id', $cancelado)->value('error_log'));

        Artisan::call('billing:cancelar-colas', ['--revertir' => $r['archivo_para_revertir'], '--ejecutar' => true]);
        $this->assertSame(['cancelado', 'error', 'cancelado', 'enviado'], array_values($this->estados($ids)));
    }

    public function test_todos_los_del_filtro_y_conteo_vigente(): void
    {
        $admin = $this->usuario('super-administrator');
        $viejos = [$this->aviso('recibo', 100), $this->aviso('recibo', 80)];
        $nuevo = $this->aviso('recibo', 2);
        $otroTipo = $this->aviso('prefactura', 100);
        $filtro = ['tipo' => 'recibo', 'estado' => 'en_espera', 'horas' => 48];

        $this->assertSame(2, $this->cola->conteo('cancelar', ['filtro' => $filtro])['total']);
        // Entra uno más al filtro entre el conteo y la confirmación: no se toca nada.
        $tarde = $this->aviso('recibo', 60);
        try {
            $this->cola->aplicar($admin, 'cancelar', ['filtro' => $filtro], 2);
            $this->fail('Debía rechazar un conteo viejo.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('ahora son 3', $e->getMessage());
        }
        $this->assertSame('en_espera', DB::table('billing_notifications')->where('id', $tarde)->value('status'));

        $r = $this->cola->aplicar($admin, 'cancelar', ['filtro' => $filtro], 3);
        $this->archivos[] = $r['archivo_para_revertir'];
        $this->assertSame(3, $r['cambiadas']);
        $this->assertSame(['en_espera', 'en_espera'], array_values($this->estados([$nuevo, $otroTipo])));
        $this->assertSame(['cancelado', 'cancelado'], array_values($this->estados($viejos)));
    }

    public function test_por_http_solo_super_admin_con_la_palabra(): void
    {
        $admin = $this->usuario('super-administrator');
        $dev = $this->usuario('DESARROLLADOR');
        $id = $this->aviso('recibo', 10);
        $cuerpo = ['accion' => 'cancelar', 'ids' => [$id], 'esperado' => 1];

        $this->actingAs($dev)->getJson('/configuracion/envios-automaticos/cola/listar')->assertStatus(403);
        $this->actingAs($dev)->postJson('/configuracion/envios-automaticos/cola/aplicar', $cuerpo + ['confirmacion' => 'CANCELAR'])->assertStatus(403);

        $this->actingAs($admin)->getJson('/configuracion/envios-automaticos/cola/listar?estado=en_espera')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('filas.0.id', $id)->assertJsonPath('por_pagina', 50);
        $this->actingAs($admin)->postJson('/configuracion/envios-automaticos/cola/conteo', ['accion' => 'cancelar', 'ids' => [$id]])
            ->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($admin)->postJson('/configuracion/envios-automaticos/cola/aplicar', $cuerpo + ['confirmacion' => 'REACTIVAR'])->assertStatus(422);
        $this->actingAs($admin)->postJson('/configuracion/envios-automaticos/cola/aplicar', ['esperado' => 2, 'confirmacion' => 'CANCELAR'] + $cuerpo)
            ->assertStatus(422)->assertJsonPath('conteo.total', 1);
        $this->assertSame('en_espera', DB::table('billing_notifications')->where('id', $id)->value('status'));

        $r = $this->actingAs($admin)->postJson('/configuracion/envios-automaticos/cola/aplicar', $cuerpo + ['confirmacion' => 'CANCELAR'])->assertOk();
        $this->archivos[] = $r->json('archivo_para_revertir');
        $this->assertSame('cancelado', DB::table('billing_notifications')->where('id', $id)->value('status'));
    }
}
