<?php

namespace App\Console\Commands\Active;

use App\Models\BillingConfig;
use App\Models\BillingNotification;
use App\Services\Finance\Billing\ReactivacionEnvios;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * FAC-08 (item #515): estado de la reactivación gradual (solo lectura) — pausa general, etapa,
 * límites, cuántas notificaciones en espera alcanzaría la etapa actual, envíos y errores de hoy — y
 * verificación de SPF/DMARC (y DKIM solo con --selector: no se adivina) por DNS público.
 */
class ReactivacionEstadoCommand extends Command
{
    protected $signature = 'billing:reactivacion-estado
                            {--dominio= : Dominio a verificar (por defecto, el de mail.from.address)}
                            {--selector= : Selector DKIM del proveedor (si no se da, DKIM no se verifica)}
                            {--sin-dns : No consultar DNS}';

    protected $description = 'Estado de la reactivación gradual de envíos de cobro y verificación SPF/DKIM/DMARC (solo lectura)';

    public function handle(ReactivacionEnvios $etapa): int
    {
        $pausa = (bool) BillingConfig::current()->envio_pausado;
        $cfg = $etapa->resumenConfig();
        $this->info('Pausa general (billing_config.envio_pausado): ' . ($pausa ? 'PUESTA — no sale nada automático' : 'quitada'));
        $this->table(['Ajuste', 'Valor'], collect($cfg)->map(fn ($v, $k) => [$k, is_bool($v) ? ($v ? 'sí' : 'no') : $v])->values()->all());

        $espera = BillingNotification::where('status', BillingNotification::STATUS_WAITING)->where('send_by_email', true);
        $alcanzables = $this->alcanzables(clone $espera, $etapa);
        $this->line('En espera (con envío por correo): ' . (clone $espera)->count() . "; alcanzables por la etapa «{$cfg['etapa']}»: {$alcanzables}.");
        $this->line('Hoy: ' . BillingNotification::where('status', BillingNotification::STATUS_SENT)->whereDate('enviado_at', today())->count()
            . ' enviadas, ' . BillingNotification::where('status', BillingNotification::STATUS_ERROR)->whereDate('updated_at', today())->count() . ' con error.');

        if (! $this->option('sin-dns')) {
            $this->dns();
        }
        $this->line('Nada se modificó.');

        return self::SUCCESS;
    }

    private function alcanzables($query, ReactivacionEnvios $etapa): int
    {
        $cfg = (array) config('billing.reactivacion', []);

        return match ($etapa->etapa()) {
            ReactivacionEnvios::CERRADO => 0,
            ReactivacionEnvios::TODOS   => $query->count(),
            ReactivacionEnvios::PILOTO  => $query->whereIn('client_id', ReactivacionEnvios::ids($cfg['clientes_piloto'] ?? []))->count(),
            default => $query->where(function ($q) use ($cfg) {
                $q->whereIn('client_id', ReactivacionEnvios::ids($cfg['clientes_piloto'] ?? []))
                    ->orWhereIn('client_id', DB::table('client_main_information')->select('client_id')
                        ->whereIn('colony_id', ReactivacionEnvios::ids($cfg['colonias'] ?? []))
                        ->orWhereIn('municipality_id', ReactivacionEnvios::ids($cfg['municipios'] ?? [])));
            })->count(),
        };
    }

    private function dns(): void
    {
        $dominio = $this->option('dominio') ?: substr(strrchr((string) config('mail.from.address'), '@') ?: '', 1);
        if (! $dominio) {
            $this->warn('Sin dominio para verificar (mail.from.address vacío); usa --dominio=.');

            return;
        }
        $txt = fn (string $host) => array_map(fn ($r) => $r['txt'] ?? '', @dns_get_record($host, DNS_TXT) ?: []);

        $mx = array_map(fn ($r) => ($r['target'] ?? '') . ' (' . ($r['pri'] ?? '') . ')', @dns_get_record($dominio, DNS_MX) ?: []);
        $spf = array_values(array_filter($txt($dominio), fn ($t) => str_starts_with(strtolower($t), 'v=spf1')));
        $dmarc = array_values(array_filter($txt('_dmarc.' . $dominio), fn ($t) => str_starts_with(strtoupper($t), 'V=DMARC1')));

        $this->info("DNS público de {$dominio} (solo lectura):");
        $this->line('  MX: ' . ($mx ? implode(', ', $mx) : 'no encontrado'));
        $this->line('  SPF: ' . ($spf ? implode(' | ', $spf) : 'NO encontrado') . (count($spf) > 1 ? '  ⚠️ más de un registro SPF (inválido)' : ''));
        $this->line('  DMARC: ' . ($dmarc ? implode(' | ', $dmarc) : 'NO encontrado'));

        $selector = $this->option('selector');
        if (! $selector) {
            $this->line('  DKIM: no verificado — indica --selector=<el del proveedor de correo>; no se adivina.');

            return;
        }
        $dkim = array_values(array_filter($txt("{$selector}._domainkey.{$dominio}"), fn ($t) => str_contains($t, 'p=')));
        $this->line("  DKIM ({$selector}): " . ($dkim ? 'publicado' : 'NO encontrado'));
    }
}
