<?php

namespace App\Modules\Addons\Payments\Console;

use App\Models\Client;
use App\Models\Marketing\Message;
use App\Modules\Addons\Payments\Models\ReportedPayment;
use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use App\Modules\Addons\Payments\Models\WhatsappPaymentExtraction as Extraction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * FASE 6 (F6.5) — Genera casos de prueba REVERSIBLES para la cola de Tere.
 *
 *   php artisan conciliacion:demo          → crea 3 casos (propuesto, multi-servicio, escalado)
 *   php artisan conciliacion:demo --clean  → borra los demo y REVIERTE lo aplicado (saldo intacto)
 *
 * Todo se marca con la clave DEMO-F6-* para poder limpiarlo sin tocar datos reales.
 * El cliente de prueba es el 17. Si Irving CONFIRMA un demo, --clean revierte el
 * pago (borra payment + transaction + reported_payment y ajusta el saldo).
 */
class ConciliacionDemoCommand extends Command
{
    protected $signature = 'conciliacion:demo {--clean : Borrar los casos demo y revertir lo aplicado}';
    protected $description = 'Crea (o limpia) casos de prueba reversibles para la cola de conciliación.';

    private const CLAVE_PREFIX = 'DEMO-F6-';
    private const TEST_CLIENT = 17;
    private const DIR = 'private/payments/whatsapp/comprobantes';

    public function handle(): int
    {
        if ($this->option('clean')) {
            return $this->clean();
        }
        return $this->create();
    }

    private function create(): int
    {
        $client = Client::find(self::TEST_CLIENT);
        if (!$client) {
            $this->error('Cliente de prueba 17 no existe.');
            return self::FAILURE;
        }

        $convId = $this->demoConversationId();

        // 1) Propuesto (por nombre) · 2) multi-servicio · 3) escalado (sin cliente)
        $this->makeCase($convId, '1', 350.00, ['state' => Session::STATE_RESOLVED, 'method' => 'name_single', 'certainty' => 'proposed', 'resolved_client_id' => self::TEST_CLIENT]);
        $this->makeCase($convId, '2', 137.77, ['state' => Session::STATE_RESOLVED, 'method' => 'client_id', 'certainty' => 'proposed', 'resolved_client_id' => self::TEST_CLIENT, 'resolved_multiple_services' => true]);
        $this->makeCase($convId, '3', 549.00, ['state' => Session::STATE_ESCALATED, 'escalation_reason' => 'no_match_after_retries']);

        $this->info('3 casos demo creados (propuesto, multi-servicio, escalado) para el cliente 17.');
        $this->line('Míralos en /finanzas/conciliacion-cola. Para borrar: php artisan conciliacion:demo --clean');
        return self::SUCCESS;
    }

    /** Conversación demo dedicada (lead + conversation), reusable/idempotente. */
    private function demoConversationId(): int
    {
        $lead = DB::table('marketing_leads')->where('full_name', 'DEMO CONCILIACION')->first();
        if (!$lead) {
            $leadId = DB::table('marketing_leads')->insertGetId([
                'company_id' => 1, 'full_name' => 'DEMO CONCILIACION', 'phone' => '0000000000',
                'whatsapp' => '0000000000', 'status' => 'new', 'captured_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            $leadId = $lead->id;
        }
        $conv = DB::table('marketing_conversations')->where('lead_id', $leadId)->first();
        if ($conv) {
            return $conv->id;
        }
        $channelId = DB::table('marketing_channels')->where('code', 'whatsapp')->value('id') ?? 1;
        return DB::table('marketing_conversations')->insertGetId([
            'company_id' => 1, 'lead_id' => $leadId, 'channel_id' => $channelId,
            'ai_handled' => 0, 'status' => 'closed', 'unread_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeCase(int $convId, string $n, float $amount, array $sessionAttrs): void
    {
        $clave = self::CLAVE_PREFIX . $n;
        $path  = self::DIR . '/' . $clave . '.jpg';
        Storage::disk('local')->put($path, $this->fakeReceipt($amount, $clave));

        $msg = Message::create([
            'conversation_id'     => $convId,
            'direction'           => 'inbound',
            'content'             => 'comprobante demo',
            'content_type'        => 'image',
            'sender'              => 'lead',
            'external_message_id' => $clave,
            'media_path'          => $path,
            'sent_at'             => now(),
        ]);

        $ext = Extraction::create([
            'message_id'    => $msg->id,
            'conversation_id' => $convId,
            'document_type' => 'spei_transfer',
            'source_mime'   => 'image/jpeg',
            'concepto'      => 'PAGO DEMO ' . $n,
            'fecha_pago'    => now()->format('d/m/Y H:i'),
            'ok'            => true,
            'fields'        => [
                'monto'             => ['value' => number_format($amount, 2, '.', ''), 'confidence' => 'alta'],
                'clave_rastreo'     => ['value' => $clave, 'confidence' => 'alta'],
                'fecha_pago'        => ['value' => now()->format('d/m/Y H:i'), 'confidence' => 'alta'],
                'concepto'          => ['value' => 'PAGO DEMO ' . $n, 'confidence' => 'alta'],
                'banco_origen'      => ['value' => 'BBVA', 'confidence' => 'alta'],
                'titular_ordenante' => ['value' => 'CLIENTE DEMO', 'confidence' => 'media'],
            ],
            'extracted_at'  => now(),
        ]);

        Session::create(array_merge([
            'is_simulation'   => false,
            'extraction_id'   => $ext->id,
            'conversation_id' => $convId,
            'message_id'      => $msg->id,
            'attempts'        => 0,
            'expires_at'      => now()->addHours(12),
        ], $sessionAttrs));
    }

    private function clean(): int
    {
        // Drena la cola primero: asegura que el abono async (PaymentClientJob) ya
        // corrió antes de revertir, para que el revert sea exacto (sin drift).
        \Illuminate\Support\Facades\Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true, '--queue' => 'default,database']);

        // Sesiones demo = las ligadas a extracciones con clave DEMO-F6-*.
        $extIds = Extraction::where('fields->clave_rastreo->value', 'like', self::CLAVE_PREFIX . '%')->pluck('id');
        // fallback robusto: por concepto/clave
        if ($extIds->isEmpty()) {
            $extIds = Extraction::where('concepto', 'like', 'PAGO DEMO %')->pluck('id');
        }
        $sessions = Session::whereIn('extraction_id', $extIds)->get();

        $reverted = 0;
        foreach ($sessions as $s) {
            if ($s->applied_payment_id) {
                $this->reversePayment((int) $s->applied_payment_id);
                $reverted++;
            }
        }

        // Borrar reported_payments demo (por clave), extracciones, mensajes, archivos, sesiones.
        ReportedPayment::where('clave_rastreo', 'like', self::CLAVE_PREFIX . '%')->forceDelete();
        $msgIds = Extraction::whereIn('id', $extIds)->pluck('message_id');
        foreach (Message::whereIn('id', $msgIds)->get() as $m) {
            if ($m->media_path && Storage::disk('local')->exists($m->media_path)) {
                Storage::disk('local')->delete($m->media_path);
            }
            $m->delete();
        }
        Session::whereIn('extraction_id', $extIds)->delete();
        Extraction::whereIn('id', $extIds)->delete();

        // Conversación + lead demo.
        $lead = DB::table('marketing_leads')->where('full_name', 'DEMO CONCILIACION')->first();
        if ($lead) {
            DB::table('marketing_conversations')->where('lead_id', $lead->id)->delete();
            DB::table('marketing_leads')->where('id', $lead->id)->delete();
        }

        $this->info("Demo limpiado. Casos borrados; pagos revertidos: {$reverted}. Saldo del cliente 17 restaurado.");
        return self::SUCCESS;
    }

    /** Revierte un pago aplicado: ajusta saldo, borra transaction + payment. */
    private function reversePayment(int $paymentId): void
    {
        $payment = DB::table('payments')->where('id', $paymentId)->first();
        if (!$payment) {
            return;
        }
        $client = Client::find(self::TEST_CLIENT);
        // Resta el crédito de las transactions de este pago y las borra.
        $credit = (float) DB::table('transactions')->where('payment_id', $paymentId)->sum('credit');
        if ($client && $client->balance && $credit > 0) {
            $client->balance->amount = (float) $client->balance->amount - $credit;
            $client->balance->save();
        }
        DB::table('transactions')->where('payment_id', $paymentId)->delete();
        DB::table('payments')->where('id', $paymentId)->update(['deleted_at' => now()]);
    }

    /** Comprobante falso (JPEG) para que la pantalla muestre algo. */
    private function fakeReceipt(float $amount, string $clave): string
    {
        $im = imagecreatetruecolor(700, 420);
        $bg = imagecolorallocate($im, 255, 255, 255);
        $blue = imagecolorallocate($im, 20, 80, 160);
        $dark = imagecolorallocate($im, 30, 30, 30);
        $gray = imagecolorallocate($im, 120, 120, 120);
        imagefill($im, 0, 0, $bg);
        imagefilledrectangle($im, 0, 0, 700, 70, $blue);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagestring($im, 5, 30, 25, 'BBVA - Comprobante SPEI (DEMO)', $white);
        imagestring($im, 5, 30, 120, 'Monto:  $ ' . number_format($amount, 2), $dark);
        imagestring($im, 4, 30, 170, 'Clave de rastreo: ' . $clave, $dark);
        imagestring($im, 4, 30, 210, 'Fecha: ' . now()->format('d/m/Y H:i'), $gray);
        imagestring($im, 4, 30, 250, 'Banco origen: BBVA', $gray);
        imagestring($im, 3, 30, 360, 'Comprobante de prueba - borrar con --clean', $gray);
        ob_start();
        imagejpeg($im, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($im);
        return $bytes;
    }
}
