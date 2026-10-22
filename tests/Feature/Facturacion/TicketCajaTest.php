<?php

namespace Tests\Feature\Facturacion;

use App\Models\BillingNotification;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\StandardNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * #580: el ticket de caja ya no se encola; desde el pago en la ficha se abre para imprimir y se
 * reenvía a mano por correo, con permiso y auditoría. Sin salidas reales: notificaciones, colas y
 * disco falsos dentro de una transacción que se revierte.
 */
class TicketCajaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        Storage::fake(config('billing.pdf_disk', 'local'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function usuario(?string $rol, array $permisos = []): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => $rol ?? 'sin rol', 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => bcrypt(Str::random(20)), 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::find($id);
        if ($rol) {
            $user->assignRole($rol);
        }
        if ($permisos) {
            $user->givePermissionTo($permisos);
        }

        return $user;
    }

    private function pago(?string $correo): Payment
    {
        $usuario = (int) DB::table('users')->value('id');
        $cliente = DB::table('clients')->insertGetId(['created_by' => $usuario, 'updated_by' => $usuario, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert([
            'client_id' => $cliente, 'name' => 'CLIENTE SINTÉTICO TKT', 'email' => $correo, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Payment::create([
            'payment_method_id' => 2, 'date' => now()->format('Y-m-d H:i:s'), 'amount' => 349.0, 'receipt' => 'TKT-PRUEBA',
            'add_by' => $usuario, 'paymentable_id' => $cliente, 'paymentable_type' => 'App\\Models\\Client',
        ]);
    }

    public function test_el_ticket_no_se_encola_y_se_abre_para_imprimir(): void
    {
        $pago = $this->pago('tkt@example.test');
        $this->assertSame(0, BillingNotification::where('payment_id', $pago->id)->where('document_type', 'ticket')->count());

        $admin = $this->usuario('super-administrator');
        $folio = 'TKT-' . str_pad($pago->id, 8, '0', STR_PAD_LEFT);
        $this->actingAs($admin)->get("/cliente/billing/payment/ticket/{$pago->id}")->assertOk()->assertSee($folio)->assertSee('tkt@example.test');
        $pdf = $this->actingAs($admin)->get("/cliente/billing/payment/ticket/{$pago->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->actingAs($admin)->get('/cliente/billing/payment/ticket/999999999/pdf')->assertNotFound();
    }

    public function test_reenviar_por_correo_con_permiso_audita_y_deja_evidencia(): void
    {
        $pago = $this->pago('tkt@example.test');
        $cajera = $this->usuario(null, ['client_payroll_payment_view_client', 'client_payroll_payment_add_client']);

        $this->actingAs($cajera)->postJson("/cliente/billing/payment/ticket/{$pago->id}/enviar")->assertOk()
            ->assertJsonFragment(['message' => 'Ticket TKT-' . str_pad($pago->id, 8, '0', STR_PAD_LEFT) . ' enviado a tkt@example.test. Sale en unos minutos con la cola de correo.']);

        Notification::assertSentOnDemand(StandardNotification::class, function ($n, $canales, AnonymousNotifiable $destino) {
            $props = (fn () => $this->emailProps)->call($n);

            return $destino->routes['mail'] === 'tkt@example.test' && $canales === ['mail']
                && str_starts_with(base64_decode($props['attachments'][0]['data']), '%PDF') && count($props['evidencias']) === 1;
        });
        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'facturacion-envios')->where('description', 'Ticket de caja reenviado a mano')
            ->where('causer_id', $cajera->id)->where('subject_id', $pago->id)->count());
        $this->assertSame(1, DB::table('billing_documento_evidencias')->where('payment_id', $pago->id)->where('documento', 'ticket')
            ->where('origen', 'enviar_manual')->where('destinatario', 'tkt@example.test')->count());
    }

    public function test_sin_correo_o_sin_permiso_no_se_envia(): void
    {
        $sinCorreo = $this->pago(null);
        $admin = $this->usuario('super-administrator');
        $this->actingAs($admin)->postJson("/cliente/billing/payment/ticket/{$sinCorreo->id}/enviar")->assertStatus(422);

        $pago = $this->pago('tkt@example.test');
        $soloVer = $this->usuario(null, ['client_payroll_payment_view_client']);
        $this->actingAs($soloVer)->get("/cliente/billing/payment/ticket/{$pago->id}/pdf")->assertOk();
        $this->actingAs($soloVer)->postJson("/cliente/billing/payment/ticket/{$pago->id}/enviar")->assertStatus(403);

        Notification::assertNothingSent();
    }
}
