<?php

namespace App\Modules\Addons\Talento\Console;

use App\Modules\Addons\Talento\Models\TalentoCredential;
use App\Modules\Addons\Talento\Models\TalentoFund;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckCredentialExpirationsCommand extends Command
{
    protected $signature   = 'talento:check-credential-expirations';
    protected $description = 'Actualiza estados de credenciales y abre fondos de ahorro para renovación.';

    public function handle(): int
    {
        $updated   = 0;
        $fundsOpen = 0;
        $now       = now();

        $credentials = TalentoCredential::with('colaborador.user')
            ->whereNotNull('expires_at')
            ->whereIn('status', ['valid', 'expiring'])
            ->get();

        foreach ($credentials as $cred) {
            $weeksLeft = $cred->weeksUntilExpiry();
            $newStatus = $cred->status;

            if ($weeksLeft === null) continue;

            if ($weeksLeft <= 0) {
                $newStatus = 'expired';
            } elseif ($weeksLeft <= $cred->alert_weeks_before) {
                $newStatus = 'expiring';
            } else {
                $newStatus = 'valid';
            }

            if ($newStatus !== $cred->status) {
                $cred->update(['status' => $newStatus]);
                $updated++;
                $this->notifyStatusChange($cred, $newStatus);
            }

            // Abrir fondo de renovación si pasa a 'expiring' y no tiene uno activo
            if ($newStatus === 'expiring') {
                $fundsOpen += $this->ensureRenewalFund($cred);
            }
        }

        // Marcar 'missing' a colaboradores activos sin credencial
        $this->flagMissingCredentials();

        $this->info("Credenciales actualizadas: {$updated}. Fondos abiertos: {$fundsOpen}.");

        Log::info('talento:check-credential-expirations', [
            'credentials_updated' => $updated,
            'funds_opened'        => $fundsOpen,
        ]);

        return self::SUCCESS;
    }

    // ── Privados ─────────────────────────────────────────────────────────────

    private function ensureRenewalFund(TalentoCredential $cred): int
    {
        // Ya existe fondo accumulating para esta credencial → no duplicar
        $existing = TalentoFund::where('colaborador_id', $cred->colaborador_id)
            ->where('credential_id', $cred->id)
            ->whereIn('status', ['accumulating', 'ready'])
            ->exists();

        if ($existing) return 0;

        // Monto configurable desde settings, fallback 800
        $target    = (float) (setting('talento_license_renewal_cost') ?? 800);
        $weekly    = (float) (setting('talento_license_weekly_deduction') ?? 100);

        TalentoFund::create([
            'colaborador_id'   => $cred->colaborador_id,
            'purpose'          => 'license',
            'target_amount'    => $target,
            'accumulated'      => 0,
            'weekly_deduction' => $weekly,
            'status'           => 'accumulating',
            'authorized'       => false,  // requiere autorización expresa (LFT)
            'credential_id'    => $cred->id,
            'notes'            => "Fondo automático por vencimiento de credencial #{$cred->id}",
            'created_by'       => null,
        ]);

        return 1;
    }

    private function flagMissingCredentials(): void
    {
        // Solo registros ya existentes con status=missing → nada que actualizar
        // (no creamos credenciales de la nada; el admin las carga manualmente)
    }

    private function notifyStatusChange(TalentoCredential $cred, string $newStatus): void
    {
        $user = $cred->colaborador?->user;
        if (!$user) return;

        $msg = match ($newStatus) {
            'expiring' => "⚠ Tu licencia de conducir vence pronto ({$cred->expires_at->format('d/M/Y')}). Se abrirá un fondo de ahorro para renovación.",
            'expired'  => "❌ Tu licencia de conducir venció el {$cred->expires_at->format('d/M/Y')}. Avisa a tu supervisor.",
            default    => null,
        };

        if (!$msg) return;

        // Email si el servidor está configurado — graceful degradation
        try {
            if (config('mail.from.address') && filter_var($user->email ?? '', FILTER_VALIDATE_EMAIL)) {
                Mail::raw($msg, fn($m) => $m
                    ->to($user->email)
                    ->subject("[Talento] Alerta de credencial — {$user->name}")
                );
            }
        } catch (\Throwable $e) {
            Log::warning('talento credential mail failed', ['user' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
