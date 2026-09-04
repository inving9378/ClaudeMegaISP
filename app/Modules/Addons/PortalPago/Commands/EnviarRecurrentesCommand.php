<?php

namespace App\Modules\Addons\PortalPago\Commands;

use App\Models\ClientInvoice;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use App\Modules\Addons\PortalPago\Models\PortalPagoAccount;
use App\Modules\Addons\PortalPago\Models\PortalPagoRecurrence;
use App\Modules\Addons\PortalPago\Services\PortalPagoLinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Recurrencia asistida del Portal de Pago.
 *
 * NO es auto-débito (SPEI no se jala): por cada recurrencia activa cuyo día de
 * corte cae hoy (o que quedó pendiente porque el cron falló un día), genera la
 * liga del mes y la LOGUEA con token + teléfono del cliente. El envío real
 * (WhatsApp/SMS) queda fuera de este paso: lo hace el operador o el canal que
 * se conecte después.
 */
class EnviarRecurrentesCommand extends Command
{
    protected $signature = 'pagos:enviar-recurrentes {--dry-run : Muestra qué se generaría sin crear ligas}';

    protected $description = 'Genera ligas de pago del mes para las recurrencias activas (no envía; loguea para envío manual).';

    public function handle(PortalPagoLinkService $links): int
    {
        $dry       = (bool) $this->option('dry-run');
        $hoy       = Carbon::now()->day;
        $inicioMes = Carbon::now()->startOfMonth();

        $recurrencias = PortalPagoRecurrence::activas()->get()->filter(function (PortalPagoRecurrence $r) use ($hoy, $inicioMes) {
            // Ya se le envió este mes → no repetir (idempotencia mensual).
            if ($r->ultimo_link_enviado_at && $r->ultimo_link_enviado_at->gte($inicioMes)) {
                return false;
            }
            // Día exacto de corte, o catch-up si el cron falló (hoy ya pasó el corte
            // y aún no se ha enviado este mes — el guard de arriba lo asegura).
            return $hoy >= $r->dia_corte;
        });

        $generadas = 0;
        $sinFactura = 0;
        $sinCuenta = 0;
        $filas = [];

        foreach ($recurrencias as $r) {
            $account = PortalPagoAccount::activas()->find($r->account_id);
            if (! $account) {
                $sinCuenta++;
                Log::channel('pagos_recurrentes')->warning('pagos:enviar-recurrentes — recurrencia sin cuenta activa', ['recurrence_id' => $r->id, 'account_id' => $r->account_id]);
                continue;
            }

            // Factura pendiente del cliente (la más antigua en Pagar%).
            $invoice = ClientInvoice::where('client_id', $r->client_id)
                ->where('estado', 'LIKE', 'Pagar%')
                ->orderBy('id')
                ->first();

            if (! $invoice) {
                $sinFactura++;
                continue;
            }

            $phone = DB::table('client_main_information')->where('client_id', $r->client_id)->value('phone');
            $phone = preg_replace('/\D+/', '', (string) $phone);

            if ($dry) {
                $filas[] = [$r->client_id, $invoice->id, '$' . number_format((float) $invoice->total, 2), '(dry-run)', $phone ?: '—'];
                continue;
            }

            $link = $links->generate($invoice, $account->id, (float) $invoice->total);
            $r->update(['ultimo_link_enviado_at' => Carbon::now()]);
            $generadas++;

            // Log para envío manual / conexión a canal.
            Log::channel('pagos_recurrentes')->info('pagos:enviar-recurrentes — liga generada', [
                'recurrence_id' => $r->id,
                'client_id'     => $r->client_id,
                'invoice_id'    => $invoice->id,
                'token'         => $link->token,
                'url'           => url('/f/' . $link->token),
                'monto'         => (string) $link->monto_esperado,
                'telefono'      => $phone ?: null,
            ]);

            $filas[] = [$r->client_id, $invoice->id, '$' . number_format((float) $link->monto_esperado, 2), $link->token, $phone ?: '—'];
        }

        $resumen = [
            'dry_run'          => $dry,
            'evaluadas'        => $recurrencias->count(),
            'ligas'            => $dry ? count($filas) : $generadas,
            'sin_factura'      => $sinFactura,
            'sin_cuenta'       => $sinCuenta,
            'monto_total'      => (string) collect($filas)->sum(fn ($f) => (float) preg_replace('/[^0-9.]/', '', $f[2])),
        ];
        Log::channel('pagos_recurrentes')->info('pagos:enviar-recurrentes — resumen de corrida', $resumen);
        $this->enviarResumenOperativo($resumen, $dry);

        $this->info(($dry ? '[DRY-RUN] ' : '') . "Recurrencias evaluadas: {$recurrencias->count()} · Ligas " . ($dry ? 'a generar' : 'generadas') . ": " . ($dry ? count($filas) : $generadas) . " · Sin factura: {$sinFactura} · Sin cuenta activa: {$sinCuenta}");

        if (! empty($filas)) {
            $this->table(['Cliente', 'Factura', 'Monto', 'Token', 'Teléfono'], $filas);
            $this->line('Las ligas quedaron logueadas (canal: pagos_recurrentes) para envío manual.');
        } else {
            $this->line('No hay recurrencias que apliquen hoy.');
        }

        return self::SUCCESS;
    }

    /**
     * Resumen diario a Irving por correo/WhatsApp (item #163, q3). Sin destinatario
     * configurado (config/pagos.php: recurrentes_resumen_email/_whatsapp, ambos vacíos
     * por default) no hace nada — el resumen ya quedó en el canal 'pagos_recurrentes'.
     * Cada canal aislado: un fallo de envío no debe tumbar la corrida del comando.
     */
    private function enviarResumenOperativo(array $resumen, bool $dry): void
    {
        $email    = config('pagos.recurrentes_resumen_email');
        $whatsapp = config('pagos.recurrentes_resumen_whatsapp');

        if (! $email && ! $whatsapp) {
            return;
        }

        $texto = ($dry ? '[DRY-RUN] ' : '') . 'Pagos recurrentes — evaluadas: ' . $resumen['evaluadas']
            . ' · ligas: ' . $resumen['ligas']
            . ' · sin factura: ' . $resumen['sin_factura']
            . ' · sin cuenta: ' . $resumen['sin_cuenta']
            . ' · monto total: $' . $resumen['monto_total'];

        if ($email) {
            try {
                Mail::raw($texto, fn ($m) => $m->to($email)->subject('Resumen diario — pagos:enviar-recurrentes'));
            } catch (\Throwable $e) {
                Log::channel('pagos_recurrentes')->warning('pagos:enviar-recurrentes — falló envío de resumen por correo', ['error' => $e->getMessage()]);
            }
        }

        if ($whatsapp) {
            try {
                (new EvolutionApiService())->sendText(EvolutionApiService::phoneToJid($whatsapp), $texto);
            } catch (\Throwable $e) {
                Log::channel('pagos_recurrentes')->warning('pagos:enviar-recurrentes — falló envío de resumen por WhatsApp', ['error' => $e->getMessage()]);
            }
        }
    }
}
