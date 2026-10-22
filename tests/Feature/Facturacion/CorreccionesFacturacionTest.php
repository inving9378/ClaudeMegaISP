<?php

namespace Tests\Feature\Facturacion;

use App\Models\FacturacionCorreccion as Correccion;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\Billing\CorreccionesFacturacion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Item #571, Parte B: la corrida al actualizar corrige sola solo lo determinista (fantasma sin
 * dinero, caso decidido que coincide exacto) y deja lo demás en «Correcciones pendientes»; una
 * corrida no corre dos veces y lo descartado no vuelve. Clientes sintéticos, BD de pruebas en una
 * transacción que se revierte y disco de respaldos falso.
 */
class CorreccionesFacturacionTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private CorreccionesFacturacion $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['billing.correcciones.automaticas' => true, 'billing.correcciones.decididas' => []]);
        DB::table('facturacion_corridas')->update(['estado' => 'terminada']);
        $this->svc = app(CorreccionesFacturacion::class);
    }

    // ─── Datos sintéticos ────────────────────────────────────────────────────

    private function cliente(string $corte = '2026-07-04 23:59:59'): int
    {
        $usuario = (int) DB::table('users')->value('id');
        $id = DB::table('clients')->insertGetId(['fecha_corte' => $corte, 'created_by' => $usuario, 'updated_by' => $usuario, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert(['client_id' => $id, 'name' => 'CLIENTE SINTÉTICO CORRECCIONES', 'email' => "correcciones-{$id}@example.test", 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function pago(int $cliente, string $fecha, float $monto = 200, ?float $enCuenta = null): int
    {
        $id = Payment::withoutEvents(fn () => Payment::create([
            'payment_method_id' => 1, 'date' => $fecha, 'amount' => $monto, 'payment_period' => substr($fecha, 0, 7),
            'receipt' => 'CORRECCION', 'add_by' => (int) DB::table('users')->value('id'),
            'paymentable_id' => $cliente, 'paymentable_type' => 'App\\Models\\Client',
        ]))->id;
        if ($enCuenta !== null) {
            DB::table('transactions')->insert([
                'date' => $fecha, 'client_id' => $cliente, 'cantidad' => '1', 'category' => 'Pago', 'type' => 'credit', 'credit' => $enCuenta,
                'price' => $enCuenta, 'iva' => 0, 'total' => $enCuenta, 'account_balance' => 0, 'company_balance' => 0, 'movement' => '+',
                'description' => 'Pago', 'add_to_invoice' => 0, 'is_payment' => 1, 'payment_id' => $id,
                'transactionable_id' => $cliente, 'transactionable_type' => 'App\\Models\\Client', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }

    private function factura(int $cliente, string $periodo, string $status, ?int $pago, ?float $pendiente = null): int
    {
        return Invoice::create([
            'number' => "CORR-{$cliente}-{$periodo}-" . uniqid(), 'client_id' => $cliente, 'type' => Invoice::TYPE_PROFORMA,
            'period' => $periodo, 'subtotal' => 172.41, 'tax' => 27.59, 'total' => 200, 'status' => $status,
            'pending_balance' => $pendiente ?? ($status === Invoice::STATUS_PAID ? 0 : 200), 'payment_id' => $pago,
            'due_date' => $periodo . '-04',
        ])->id;
    }

    private function superAdmin(string $rol = 'super-administrator'): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => $rol, 'email' => Str::random(10) . '@example.test', 'login_user' => 'sint_' . Str::random(8),
            'password' => bcrypt(Str::random(20)), 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $u = User::find($id);
        $u->assignRole($rol);

        return $u;
    }

    private function correr(): array
    {
        $this->svc->nuevaCorrida('prueba');

        return $this->svc->correrPendientes();
    }

    /** Fantasma clásica del #571: el periodo ya pagado tiene una segunda proforma abierta. */
    private function fantasmaDuplicada(): array
    {
        $c = $this->cliente();
        $p6 = $this->pago($c, '2026-06-03 10:00:00');
        $p7 = $this->pago($c, '2026-07-03 10:00:00');
        $f6 = $this->factura($c, '2026-06', Invoice::STATUS_PAID, $p6);
        $f7 = $this->factura($c, '2026-07', Invoice::STATUS_PAID, $p7);
        $dup = $this->factura($c, '2026-07', Invoice::STATUS_DRAFT, null);

        return compact('c', 'p6', 'p7', 'f6', 'f7', 'dup');
    }

    /** El caso del cliente 1286 en chico: un pago sin factura y desde ahí cada pago un mes atrás. */
    private function casoDecidido(?float $enCuentaJun = null): array
    {
        $c = $this->cliente();
        $p = ['mar' => $this->pago($c, '2026-03-01 10:00:00'), 'abr' => $this->pago($c, '2026-04-02 10:00:00'),
            'may' => $this->pago($c, '2026-05-03 10:00:00'), 'jun' => $this->pago($c, '2026-06-04 10:00:00', 200, $enCuentaJun)];
        $f = ['feb' => $this->factura($c, '2026-02', Invoice::STATUS_PAID, $p['abr']), 'mar' => $this->factura($c, '2026-03', Invoice::STATUS_PAID, $p['abr']),
            'abr' => $this->factura($c, '2026-04', Invoice::STATUS_PAID, $p['may']), 'may' => $this->factura($c, '2026-05', Invoice::STATUS_PAID, $p['jun']),
            'jun' => $this->factura($c, '2026-06', Invoice::STATUS_DRAFT, null)];

        return compact('c', 'p', 'f');
    }

    private function decidir(array $caso, array $esperado): void
    {
        config(['billing.correcciones.decididas' => [[
            'clave' => 'prueba', 'cliente' => $caso['c'], 'desde' => '2026-02', 'pagos_desde' => '2026-03-01',
            'anular' => [$caso['f']['mar']], 'esperado' => $esperado, 'motivo' => 'Caso decidido de prueba',
        ]]]);
    }

    // ─── Pruebas ─────────────────────────────────────────────────────────────

    public function test_la_fantasma_sin_dinero_se_corrige_sola_con_respaldo_y_no_se_corrige_dos_veces(): void
    {
        $x = $this->fantasmaDuplicada();

        $this->correr();

        $this->assertSame(Invoice::STATUS_CANCELLED, Invoice::find($x['dup'])->status);
        $this->assertSame((int) $x['p7'], (int) Invoice::find($x['f7'])->payment_id, 'La factura pagada queda con su pago.');
        $c = Correccion::where('client_id', $x['c'])->sole();
        $this->assertSame([Correccion::APLICADA, Correccion::AUTOMATICA], [$c->estado, $c->origen]);
        $this->assertSame([200.0, 0.0], [$c->deuda_antes, $c->deuda_despues]);
        $this->assertStringContainsString("UPDATE invoices SET", $c->deshacer_sql);
        $this->assertTrue(Storage::disk('local')->exists(preg_replace('#^storage/app/#', '', $c->respaldo_path)));
        $this->assertSame(1, DB::table('activity_log')->where('log_name', CorreccionesFacturacion::LOG)->where('subject_id', $c->id)->count());

        $this->assertSame([], $this->svc->correrPendientes(), 'Una corrida terminada no vuelve a correr.');
        $this->correr();
        $this->assertSame(1, Correccion::where('client_id', $x['c'])->count(), 'Lo corregido ya no se vuelve a corregir.');
    }

    public function test_una_fantasma_que_subiria_la_deuda_queda_pendiente_y_no_se_toca(): void
    {
        $c = $this->cliente('2026-09-04 23:59:59');
        $f = $this->factura($c, '2026-08', Invoice::STATUS_DRAFT, null, 0); // abierta sin saldo, sin pago

        $this->correr();

        $this->assertSame(['status' => Invoice::STATUS_DRAFT, 'pending_balance' => '0.00'], Invoice::find($f)->only(['status', 'pending_balance']));
        $corr = Correccion::where('client_id', $c)->sole();
        $this->assertSame([Correccion::PENDIENTE, Correccion::REACOMODO], [$corr->estado, $corr->tipo]);
        $this->assertSame([0.0, 200.0], [$corr->deuda_antes, $corr->deuda_despues]);
        $this->assertStringContainsString('subiría', $corr->detalle);

        // Descartada con los mismos datos, no vuelve.
        $this->svc->descartar($corr, $this->superAdmin(), 'Lo revisé: sí se cobra agosto');
        $this->correr();
        $this->assertSame(1, Correccion::where('client_id', $c)->count());
        $this->assertSame(Correccion::DESCARTADA, $corr->fresh()->estado);
    }

    public function test_el_caso_decidido_se_aplica_solo_si_coincide_exacto(): void
    {
        $x = $this->casoDecidido();
        $this->decidir($x, [$x['f']['feb'] => $x['p']['mar'], $x['f']['abr'] => $x['p']['abr'], $x['f']['may'] => $x['p']['may'], $x['f']['jun'] => $x['p']['jun']]);

        $this->correr();

        $this->assertSame(Invoice::STATUS_CANCELLED, Invoice::find($x['f']['mar'])->status);
        $this->assertSame((int) $x['p']['mar'], (int) Invoice::find($x['f']['feb'])->payment_id);
        $this->assertSame((int) $x['p']['jun'], (int) Invoice::find($x['f']['jun'])->payment_id);
        $c = Correccion::where('client_id', $x['c'])->where('tipo', Correccion::REACOMODO)->sole();
        $this->assertSame([Correccion::APLICADA, Correccion::DECIDIDA], [$c->estado, $c->origen]);

        $this->correr();
        $this->assertSame(1, Correccion::where('client_id', $x['c'])->count(), 'Ya aplicado: la siguiente corrida no hace nada.');
    }

    public function test_el_caso_decidido_que_no_coincide_queda_pendiente_sin_tocar_nada(): void
    {
        $x = $this->casoDecidido();
        $this->decidir($x, [$x['f']['feb'] => $x['p']['abr'], $x['f']['abr'] => $x['p']['abr'], $x['f']['may'] => $x['p']['may'], $x['f']['jun'] => $x['p']['jun']]);

        $this->correr();

        $this->assertSame(Invoice::STATUS_PAID, Invoice::find($x['f']['mar'])->status, 'No se toca nada.');
        $c = Correccion::where('client_id', $x['c'])->where('tipo', Correccion::REACOMODO)->sole();
        $this->assertSame([Correccion::PENDIENTE, Correccion::DECIDIDA], [$c->estado, $c->origen]);
        $this->assertStringContainsString('no coincide', $c->detalle);
    }

    public function test_el_monto_en_duda_espera_al_banco_y_se_aplica_con_la_opcion_elegida(): void
    {
        $x = $this->casoDecidido(349);
        $this->decidir($x, [$x['f']['feb'] => $x['p']['mar'], $x['f']['abr'] => $x['p']['abr'], $x['f']['may'] => $x['p']['may'], $x['f']['jun'] => $x['p']['jun']]);

        $this->correr();

        $m = Correccion::where('clave', "monto_pago:{$x['p']['jun']}")->sole();
        $this->assertSame(Correccion::PENDIENTE, $m->estado);
        $this->assertEqualsCanonicalizing([349.0, 200.0], array_column($m->despues['opciones'], 'monto'));
        $this->assertSame(200.0, (float) DB::table('payments')->where('id', $x['p']['jun'])->value('amount'), 'Mientras tanto no se toca.');

        $this->correr();
        $this->assertSame(Correccion::PENDIENTE, $m->fresh()->estado, 'Sigue esperando al banco aunque el reacomodo ya se aplicó.');

        $admin = $this->superAdmin();
        try {
            $this->svc->aprobar($m->fresh(), $admin, 123.0);
            $this->fail('Solo una de las dos opciones.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Elige', $e->getMessage());
        }
        $this->svc->aprobar($m->fresh(), $admin, 349.0);

        $this->assertSame(349.0, (float) DB::table('payments')->where('id', $x['p']['jun'])->value('amount'));
        $this->assertSame([Correccion::APLICADA, $admin->id], [$m->fresh()->estado, (int) $m->fresh()->resuelta_por]);
        $this->correr();
        $this->assertSame(Correccion::APLICADA, $m->fresh()->estado, 'Resuelto: no vuelve a proponerse.');
    }

    public function test_aprobar_una_propuesta_vieja_no_escribe_y_la_recalcula(): void
    {
        $c = $this->cliente('2026-09-04 23:59:59');
        $f = $this->factura($c, '2026-08', Invoice::STATUS_DRAFT, null, 0);
        $this->correr();
        $vieja = Correccion::where('client_id', $c)->sole();

        $this->pago($c, '2026-08-05 10:00:00'); // llegó un pago después de la propuesta

        try {
            $this->svc->aprobar($vieja, $this->superAdmin());
            $this->fail('Con datos cambiados no se aplica.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('cambiaron', $e->getMessage());
        }
        $this->assertSame(Correccion::OBSOLETA, $vieja->fresh()->estado);
        $this->assertSame(Invoice::STATUS_DRAFT, Invoice::find($f)->status, 'No se escribió nada.');
        $this->assertSame(1, Correccion::where('client_id', $c)->where('estado', Correccion::PENDIENTE)->count(), 'Queda la propuesta recalculada.');
    }

    public function test_la_pantalla_es_solo_para_super_administrador(): void
    {
        $x = $this->fantasmaDuplicada();
        config(['billing.correcciones.automaticas' => false]);
        $this->correr();
        $corr = Correccion::where('client_id', $x['c'])->sole();
        $this->assertSame(Correccion::PENDIENTE, $corr->estado, 'Con el interruptor apagado todo queda como propuesta.');

        $dev = $this->superAdmin('DESARROLLADOR');
        $this->actingAs($dev)->getJson('/finanzas/correcciones-pendientes/listar')->assertStatus(403);
        $this->actingAs($dev)->postJson("/finanzas/correcciones-pendientes/{$corr->id}/aprobar")->assertStatus(403);

        $admin = $this->superAdmin('Super Administrador');
        $lista = $this->actingAs($admin)->getJson('/finanzas/correcciones-pendientes/listar')->assertOk()->json('correcciones');
        $this->assertContains($corr->id, array_column($lista, 'id'));
        $this->actingAs($admin)->postJson("/finanzas/correcciones-pendientes/{$corr->id}/descartar", ['motivo' => 'no'])->assertStatus(422);
        $r = $this->actingAs($admin)->postJson("/finanzas/correcciones-pendientes/{$corr->id}/aprobar");
        $this->assertSame(200, $r->status(), (string) $r->getContent());
        $r->assertJsonPath('estado', Correccion::APLICADA);
        $this->assertSame(Invoice::STATUS_CANCELLED, Invoice::find($x['dup'])->status);
    }
}
