<?php

namespace Tests\Feature\Facturacion;

use App\Models\BillingDocumentoEvidencia as Evidencia;
use App\Models\BillingNotification;
use App\Models\Invoice;
use App\Services\Finance\Billing\EvidenciaDocumentoService;
use App\Services\Finance\Billing\PrefacturaPdfService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesApplication;

/**
 * FAC-05 (item #510): evidencia de los PDFs enviados. Contra la BD de pruebas en transacción
 * revertida; la tabla de evidencias va como TEMPORARY TABLE de la sesión (en MySQL no hace commit
 * implícito y desaparece al cerrar la conexión): no se migra ni se altera `megaisp_test`.
 * Columnas iguales a la migración 2026_10_16_210000.
 */
class EvidenciaDocumentoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(config('billing.pdf_disk', 'local'));
        config(['mail.default' => 'array', 'mail.mailers.array' => ['transport' => 'array'], 'mail.interceptar.activo' => false]);
        Mail::purge('array');

        if (! Schema::hasTable('billing_documento_evidencias')) {
            Schema::create('billing_documento_evidencias', function (Blueprint $t) {
                $t->temporary();
                $t->id();
                $t->string('origen', 30);
                $t->unsignedBigInteger('billing_notification_id')->nullable();
                $t->unsignedBigInteger('client_id')->nullable();
                $t->unsignedBigInteger('invoice_id')->nullable();
                $t->unsignedBigInteger('payment_id')->nullable();
                $t->string('documento', 20);
                $t->string('canal', 20)->default('correo');
                $t->string('destinatario')->nullable();
                $t->string('asunto')->nullable();
                $t->string('archivo');
                $t->string('nombre_adjunto');
                $t->char('sha256', 64);
                $t->unsignedInteger('bytes');
                $t->string('estado', 20);
                $t->boolean('interceptado')->default(false);
                $t->string('message_id')->nullable();
                $t->text('error')->nullable();
                $t->timestamp('generado_at')->nullable();
                $t->timestamp('encolado_at')->nullable();
                $t->timestamp('aceptado_at')->nullable();
                $t->timestamps();
            });
        }
    }

    private function servicio(): EvidenciaDocumentoService
    {
        return app(EvidenciaDocumentoService::class);
    }

    private function registrar(string $bytes = "%PDF-1.4 evidencia\n", string $estado = Evidencia::ESTADO_GENERADO): Evidencia
    {
        return $this->servicio()->registrar(['data' => $bytes, 'name' => 'Factura-PRUEBA 1.pdf'], [
            'origen' => Evidencia::ORIGEN_AUTOMATICO, 'documento' => 'factura', 'destinatario' => 'x@example.test', 'asunto' => 'Prueba',
        ], $estado);
    }

    public function test_guarda_los_bytes_exactos_con_su_sha256_en_el_disco_privado(): void
    {
        $bytes = "%PDF-1.4 evidencia ñ\n";
        $e = $this->registrar($bytes);

        $this->assertSame(hash('sha256', $bytes), $e->sha256);
        $this->assertSame(strlen($bytes), $e->bytes);
        $this->assertSame(Evidencia::ESTADO_GENERADO, $e->estado);
        $this->assertStringStartsWith(config('billing.pdf_path_prefix', 'billing/pdf') . '/enviados/' . now()->format('Y/m') . '/', $e->archivo);
        $this->assertStringEndsWith('-Factura-PRUEBA-1.pdf', $e->archivo);
        $this->assertSame($bytes, Storage::disk(config('billing.pdf_disk', 'local'))->get($e->archivo));
        $this->assertTrue($this->servicio()->verificar($e));
    }

    public function test_el_correo_aceptado_por_el_transporte_marca_la_evidencia_con_su_message_id(): void
    {
        $e = $this->registrar(estado: Evidencia::ESTADO_ENCOLADO);

        Mail::to('x@example.test')->send(new CorreoConEvidencia([$e->id]));

        $e->refresh();
        $this->assertSame(Evidencia::ESTADO_ACEPTADO, $e->estado);
        $this->assertNotNull($e->aceptado_at);
        $this->assertNotEmpty($e->message_id);
        $this->assertFalse($e->interceptado);
    }

    public function test_si_estaba_la_interceptacion_de_prueba_queda_anotado(): void
    {
        config(['mail.interceptar' => ['activo' => true, 'destino' => 'pruebas@example.test', 'prefijo' => '[DEV — PRUEBA]']]);
        $e = $this->registrar();

        Mail::to('cliente@example.test')->send(new CorreoConEvidencia([$e->id]));

        $this->assertTrue($e->fresh()->interceptado);
        $this->assertSame(Evidencia::ESTADO_ACEPTADO, $e->fresh()->estado);
    }

    public function test_fallido_y_verificacion_de_alteraciones(): void
    {
        $e = $this->registrar();
        $this->servicio()->marcarFallido([$e->id], 'SMTP 550');
        $this->assertSame(Evidencia::ESTADO_FALLIDO, $e->fresh()->estado);
        $this->assertSame('SMTP 550', $e->fresh()->error);

        Storage::disk(config('billing.pdf_disk', 'local'))->put($e->archivo, 'alterado');
        $this->assertFalse($this->servicio()->verificar($e->fresh()));
        Storage::disk(config('billing.pdf_disk', 'local'))->delete($e->archivo);
        $this->assertNull($this->servicio()->verificar($e->fresh()));
    }

    public function test_el_envio_automatico_deja_evidencia_aceptada_del_pdf_que_salio(): void
    {
        DB::table('billing_config')->update(['envio_pausado' => 0, 'send_ticket_email' => 0]);
        $this->app->instance(PrefacturaPdfService::class, new PrefacturaPdfServiceFalso());

        $usuario = (int) DB::table('users')->value('id');
        $cliente = DB::table('clients')->insertGetId(['created_by' => $usuario, 'updated_by' => $usuario, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert(['client_id' => $cliente, 'name' => 'CLIENTE SINTÉTICO FAC-05', 'email' => 'fac05@example.test', 'created_at' => now(), 'updated_at' => now()]);
        $invoice = Invoice::create(['number' => 'FAC05-' . $cliente, 'client_id' => $cliente, 'type' => 'proforma', 'period' => '2026-11',
            'subtotal' => 387.07, 'tax' => 61.93, 'total' => 449, 'pending_balance' => 449, 'status' => Invoice::STATUS_ISSUED]);
        $n = BillingNotification::create(['client_id' => $cliente, 'document_type' => 'prefactura', 'invoice_id' => $invoice->id,
            'email' => 'fac05@example.test', 'status' => 'en_espera', 'window_hours' => 12, 'notificar_despues_de' => now()->subMinute(), 'send_by_email' => true]);

        $this->artisan('billing:send-pending-notifications')->assertExitCode(0);

        $this->assertSame(BillingNotification::STATUS_SENT, $n->fresh()->status);
        $e = Evidencia::where('billing_notification_id', $n->id)->sole();
        $this->assertSame('prefactura', $e->documento);
        $this->assertSame(Evidencia::ESTADO_ACEPTADO, $e->estado);
        $this->assertSame(hash('sha256', '%PDF-1.4 sintético'), $e->sha256);
        $this->assertSame($invoice->id, $e->invoice_id);

        $adjunto = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage()->getAttachments()[0];
        $this->assertSame($e->sha256, hash('sha256', $adjunto->getBody()), 'lo guardado es exactamente lo que salió');
    }

    public function test_el_inventario_no_borra_nada_y_clasifica(): void
    {
        $disco = Storage::disk(config('billing.pdf_disk', 'local'));
        $prefijo = config('billing.pdf_path_prefix', 'billing/pdf');
        $disco->put("{$prefijo}/huerfano.pdf", 'x');
        $e = $this->registrar();

        $this->artisan('billing:pdfs-inventario', ['--dias' => 0, '--verificar-evidencias' => true])
            ->expectsOutputToContain('Solo lectura')
            ->expectsOutputToContain('Evidencias: 1 íntegras')
            ->assertExitCode(0);

        $disco->assertExists("{$prefijo}/huerfano.pdf");
        $disco->assertExists($e->archivo);
    }
}

/** Mailable mínimo con la cabecera de evidencia. */
class CorreoConEvidencia extends Mailable
{
    public function __construct(private array $ids)
    {
    }

    public function build()
    {
        $valor = EvidenciaDocumentoService::valorCabecera($this->ids);

        return $this->subject('Prueba evidencia')->html('<p>x</p>')
            ->withSymfonyMessage(fn ($m) => $m->getHeaders()->addTextHeader(EvidenciaDocumentoService::CABECERA, $valor));
    }
}
