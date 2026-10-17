<?php

namespace Tests\Feature\Facturacion;

use App\Models\BillingDocumentoEvidencia;
use App\Models\BillingNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesApplication;

/**
 * #511: billing:pdfs-limpiar borra SOLO tickets no enviables y PDFs ya enviados con copia en
 * evidencias, con más de N días; nunca evidencias, recibos, en espera ni recientes. BD de pruebas en
 * transacción revertida y disco falso.
 */
class LimpiarPdfsFacturacionTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private string $p;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('billing.pdf_disk', 'local'));
        $this->p = rtrim((string) config('billing.pdf_path_prefix', 'billing/pdf'), '/');
    }

    private function cliente(): int
    {
        $u = (int) DB::table('users')->value('id');

        return DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function archivo(string $ruta, int $diasAtras): string
    {
        $disco = Storage::disk(config('billing.pdf_disk', 'local'));
        $disco->put($ruta, '%PDF-1.4 x');
        touch($disco->path($ruta), now()->subDays($diasAtras)->getTimestamp());

        return $ruta;
    }

    private function notif(int $cliente, string $tipo, string $estado, string $ruta, bool $email = true): BillingNotification
    {
        return BillingNotification::create(['client_id' => $cliente, 'document_type' => $tipo, 'pdf_path' => $ruta, 'status' => $estado,
            'window_hours' => 12, 'send_by_email' => $email]);
    }

    public function test_borra_solo_lo_aprobado_y_solo_viejo(): void
    {
        $c = $this->cliente();
        $ticketViejo = $this->archivo("{$this->p}/ticket_1.pdf", 120);
        $ticketNuevo = $this->archivo("{$this->p}/ticket_2.pdf", 10);
        $enviadoConCopia = $this->archivo("{$this->p}/prefactura_3_x.pdf", 120);
        $enviadoSinCopia = $this->archivo("{$this->p}/prefactura_4_x.pdf", 120);
        $recibo = $this->archivo("{$this->p}/recibo_5.pdf", 400);
        $enEspera = $this->archivo("{$this->p}/prefactura_6_x.pdf", 400);
        $evidencia = $this->archivo("{$this->p}/enviados/2026/01/uuid-Factura-1.pdf", 400);

        $this->notif($c, 'ticket', 'en_espera', $ticketViejo, false);
        $this->notif($c, 'ticket', 'en_espera', $ticketNuevo, false);
        $n3 = $this->notif($c, 'prefactura', 'enviado', $enviadoConCopia);
        $this->notif($c, 'prefactura', 'enviado', $enviadoSinCopia);
        $this->notif($c, 'recibo', 'enviado', $recibo);
        $this->notif($c, 'prefactura', 'en_espera', $enEspera);
        if (\Illuminate\Support\Facades\Schema::hasTable('billing_documento_evidencias')) {
            BillingDocumentoEvidencia::create(['origen' => 'envio_automatico', 'billing_notification_id' => $n3->id, 'documento' => 'prefactura',
                'archivo' => $evidencia, 'nombre_adjunto' => 'x.pdf', 'sha256' => str_repeat('a', 64), 'bytes' => 10, 'estado' => 'aceptado']);
        }

        $disco = Storage::disk(config('billing.pdf_disk', 'local'));

        $this->artisan('billing:pdfs-limpiar', ['--dias' => 90])->expectsOutputToContain('se borrarían: 2 archivos')->assertExitCode(0);
        $disco->assertExists($ticketViejo);   // modo prueba: nada se borra

        $this->artisan('billing:pdfs-limpiar', ['--dias' => 90, '--ejecutar' => true])->expectsOutputToContain('Borrados: 2')->assertExitCode(0);

        $disco->assertMissing($ticketViejo);
        $disco->assertMissing($enviadoConCopia);
        foreach ([$ticketNuevo, $enviadoSinCopia, $recibo, $enEspera, $evidencia] as $intacto) {
            $disco->assertExists($intacto);
        }
    }
}
