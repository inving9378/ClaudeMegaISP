<?php

namespace App\Modules\Addons\Marketing\Services\Pilot;

use App\Models\Marketing\PilotCampaign;
use App\Models\Marketing\PilotCampaignSend;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Piloto interno de campaña multivariante A/B por email — item roadmap #47.
 *
 * Alcance aprobado (log del item #47): SOLO lista de prueba (empleados/
 * cuentas dummy), 2 variantes A/B, canal email, Irving define el copy.
 * El envío real está detrás de un kill-switch (config('marketing.pilot_campaign_send_enabled'))
 * que debe encenderse a mano — nunca lo activa este servicio por sí solo.
 */
class PilotCampaignService
{
    public function create(array $data): PilotCampaign
    {
        return DB::transaction(function () use ($data) {
            /** @var PilotCampaign $campaign */
            $campaign = PilotCampaign::create([
                'company_id'           => 1,
                'name'                 => $data['name'],
                'status'               => 'draft',
                'variant_a_subject'    => $data['variant_a_subject'],
                'variant_a_body'       => $data['variant_a_body'],
                'variant_a_cta_label'  => $data['variant_a_cta_label'] ?? null,
                'variant_a_cta_url'    => $data['variant_a_cta_url'] ?? null,
                'variant_b_subject'    => $data['variant_b_subject'],
                'variant_b_body'       => $data['variant_b_body'],
                'variant_b_cta_label'  => $data['variant_b_cta_label'] ?? null,
                'variant_b_cta_url'    => $data['variant_b_cta_url'] ?? null,
                'batch_size'           => $data['batch_size'] ?? 10,
                'batch_pause_seconds'  => $data['batch_pause_seconds'] ?? 5,
                'created_by_user_id'   => $data['created_by_user_id'] ?? null,
            ]);

            $recipients = $this->uniqueRecipients($data['recipients'] ?? []);

            foreach ($recipients as $index => $recipient) {
                PilotCampaignSend::create([
                    'pilot_campaign_id' => $campaign->id,
                    'email'             => $recipient['email'],
                    'name'              => $recipient['name'] ?? null,
                    // Split determinista round-robin: mismo tamaño de muestra por variante.
                    'variant'           => $index % 2 === 0 ? 'a' : 'b',
                    'token'             => $this->uniqueToken(),
                    'status'            => 'pending',
                ]);
            }

            return $campaign->fresh('sends');
        });
    }

    /**
     * Construye el reporte de dry-run: SIN enviar nada. Cuenta destinatarios
     * por variante, valida SMTP y arma una vista previa renderizada.
     */
    public function dryRun(PilotCampaign $campaign): array
    {
        $sends = $campaign->sends()->get();

        $report = [
            'generated_at'   => now()->toDateTimeString(),
            'smtp'           => $this->validateSmtp(),
            'total'          => $sends->count(),
            'variant_a'      => $sends->where('variant', 'a')->count(),
            'variant_b'      => $sends->where('variant', 'b')->count(),
            'batches'        => (int) ceil(max($sends->count(), 1) / max($campaign->batch_size, 1)),
            'preview_a'      => $this->preview($campaign, 'a', $sends->where('variant', 'a')->first()),
            'preview_b'      => $this->preview($campaign, 'b', $sends->where('variant', 'b')->first()),
        ];

        $campaign->update([
            'status'         => 'dry_run',
            'dry_run_report' => $report,
            'dry_run_at'     => now(),
        ]);

        return $report;
    }

    /**
     * Envía el piloto de verdad. Requiere: (1) dry-run previo hecho,
     * (2) el kill-switch encendido a mano. Envía por lotes con pausa entre
     * cada uno para no saturar el SMTP.
     */
    public function send(PilotCampaign $campaign): array
    {
        if (!in_array($campaign->status, ['dry_run', 'ready_to_send'], true)) {
            throw new RuntimeException('Falta correr el dry-run antes de enviar.');
        }

        if (!config('marketing.pilot_campaign_send_enabled')) {
            throw new RuntimeException(
                'Envío real desactivado (MARKETING_PILOT_CAMPAIGN_SEND_ENABLED=false). '
                . 'Actívalo a mano solo cuando Irving lo confirme explícitamente.'
            );
        }

        $campaign->update(['status' => 'sending']);

        $sent = 0;
        $failed = 0;

        $campaign->sends()->where('status', 'pending')->orderBy('id')
            ->chunk($campaign->batch_size, function ($batch) use ($campaign, &$sent, &$failed) {
                foreach ($batch as $send) {
                    try {
                        $this->deliver($campaign, $send);
                        $send->update(['status' => 'sent', 'sent_at' => now()]);
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::warning('[Pilot Campaign] envío falló', ['send_id' => $send->id, 'e' => $e->getMessage()]);
                        $send->update(['status' => 'failed', 'error' => $e->getMessage()]);
                        $failed++;
                    }
                }

                if ($campaign->batch_pause_seconds > 0) {
                    sleep($campaign->batch_pause_seconds);
                }
            });

        $campaign->update(['status' => 'sent', 'sent_at' => now()]);

        return ['sent' => $sent, 'failed' => $failed];
    }

    public function markOpened(PilotCampaignSend $send): void
    {
        if ($send->opened_at) {
            return;
        }
        $send->update([
            'opened_at' => now(),
            'status'    => $send->status === 'clicked' ? $send->status : 'opened',
        ]);
    }

    public function markClicked(PilotCampaignSend $send): void
    {
        $send->update([
            'opened_at'  => $send->opened_at ?? now(),
            'clicked_at' => $send->clicked_at ?? now(),
            'status'     => 'clicked',
        ]);
    }

    public function markConverted(PilotCampaignSend $send): void
    {
        $send->update([
            'converted_at' => $send->converted_at ?? now(),
            'status'       => 'converted',
        ]);
    }

    private function deliver(PilotCampaign $campaign, PilotCampaignSend $send): void
    {
        $subject = $campaign->variantSubject($send->variant);
        $html = $this->buildHtml($campaign, $send);

        Mail::html($html, function ($msg) use ($send, $subject) {
            $msg->to($send->email, $send->name)->subject($subject);
        });
    }

    private function preview(PilotCampaign $campaign, string $variant, ?PilotCampaignSend $sample): array
    {
        return [
            'subject' => $campaign->variantSubject($variant),
            'body'    => $campaign->variantBody($variant),
            'cta'     => $campaign->variantCtaLabel($variant),
            'html'    => $sample ? $this->buildHtml($campaign, $sample) : null,
        ];
    }

    private function buildHtml(PilotCampaign $campaign, PilotCampaignSend $send): string
    {
        $body = nl2br(e($campaign->variantBody($send->variant)));
        $ctaLabel = $campaign->variantCtaLabel($send->variant);
        $ctaUrl = $campaign->variantCtaUrl($send->variant);

        $ctaHtml = '';
        if ($ctaUrl) {
            $trackedUrl = url('/marketing/pilot/track/click/' . $send->token);
            $ctaHtml = '<div style="margin:24px 0;"><a href="' . e($trackedUrl) . '" '
                . 'style="display:inline-block;background:#e63946;color:#fff;padding:14px 28px;'
                . 'border-radius:6px;text-decoration:none;font-weight:bold;font-size:16px;">'
                . e($ctaLabel ?: 'Ver más') . ' &rarr;</a></div>';
        }

        $pixelUrl = url('/marketing/pilot/track/open/' . $send->token . '.gif');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;font-family:Arial,sans-serif;background:#f4f4f4;">
  <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
    <tr><td style="background:#1a1a2e;padding:20px;text-align:center;">
      <span style="color:#fff;font-size:22px;font-weight:bold;">Meganet</span>
    </td></tr>
    <tr><td style="padding:30px;">
      <p style="color:#555;line-height:1.6;">{$body}</p>
      {$ctaHtml}
    </td></tr>
    <tr><td style="background:#f4f4f4;padding:15px;text-align:center;">
      <p style="color:#999;font-size:12px;margin:0;">Piloto interno Meganet — mensaje de prueba, no enviado a clientes.</p>
    </td></tr>
  </table>
  <img src="{$pixelUrl}" width="1" height="1" alt="" style="display:none;">
</body></html>
HTML;
    }

    private function validateSmtp(): array
    {
        $host = config('mail.mailers.smtp.host');
        $from = config('mail.from.address');

        if (!$host || !$from) {
            return ['valid' => false, 'message' => 'SMTP no configurado en .env (MAIL_HOST, MAIL_FROM_ADDRESS)'];
        }

        return ['valid' => true, 'message' => "SMTP listo: {$from} vía {$host}"];
    }

    private function uniqueRecipients(array $recipients): array
    {
        $seen = [];
        $out = [];
        foreach ($recipients as $r) {
            $email = is_array($r) ? ($r['email'] ?? null) : $r;
            $email = strtolower(trim((string) $email));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $out[] = ['email' => $email, 'name' => is_array($r) ? ($r['name'] ?? null) : null];
        }
        return $out;
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(40);
        } while (PilotCampaignSend::where('token', $token)->exists());

        return $token;
    }
}
