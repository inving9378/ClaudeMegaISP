<?php

namespace App\Modules\Addons\PortalPago\Commands;

use App\Models\ClientInvoice;
use App\Modules\Addons\PortalPago\Models\PortalPagoAccount;
use App\Modules\Addons\PortalPago\Models\PortalPagoRecurrence;
use App\Modules\Addons\PortalPago\Services\PortalPagoLinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                Log::warning('pagos:enviar-recurrentes — recurrencia sin cuenta activa', ['recurrence_id' => $r->id, 'account_id' => $r->account_id]);
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
            Log::info('pagos:enviar-recurrentes — liga generada', [
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

        $this->info(($dry ? '[DRY-RUN] ' : '') . "Recurrencias evaluadas: {$recurrencias->count()} · Ligas " . ($dry ? 'a generar' : 'generadas') . ": " . ($dry ? count($filas) : $generadas) . " · Sin factura: {$sinFactura} · Sin cuenta activa: {$sinCuenta}");

        if (! empty($filas)) {
            $this->table(['Cliente', 'Factura', 'Monto', 'Token', 'Teléfono'], $filas);
            $this->line('Las ligas quedaron logueadas (canal: laravel.log) para envío manual.');
        } else {
            $this->line('No hay recurrencias que apliquen hoy.');
        }

        return self::SUCCESS;
    }
}
