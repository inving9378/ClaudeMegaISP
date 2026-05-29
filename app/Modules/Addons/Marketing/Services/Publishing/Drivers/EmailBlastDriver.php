<?php

namespace App\Modules\Addons\Marketing\Services\Publishing\Drivers;

use App\Models\Marketing\Lead;
use App\Models\Marketing\Publication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailBlastDriver extends AbstractPublishDriver
{
    public function getRequiredCredentials(): array
    {
        return ['smtp_host', 'smtp_from_address'];
    }

    public function validateCredentials(): array
    {
        $host = config('mail.mailers.smtp.host');
        $from = config('mail.from.address');

        if (!$host || !$from) {
            return ['valid' => false, 'message' => 'SMTP no configurado en .env (MAIL_HOST, MAIL_FROM_ADDRESS)'];
        }

        return ['valid' => true, 'message' => "SMTP listo: {$from} via {$host}"];
    }

    public function publish(Publication $pub): array
    {
        $content = $pub->content;
        $config  = $this->channel->platform_config ?? [];

        // Obtener lista de destinatarios
        $recipients = $this->getRecipients($config);

        if (empty($recipients)) {
            return ['success' => false, 'error' => 'No hay destinatarios de email configurados'];
        }

        $videoUrl  = $content->output_url ?? url('storage/' . ltrim($content->output_path ?? '', '/'));
        $thumbUrl  = $content->thumbnail_path
            ? url('storage/' . ltrim($content->thumbnail_path, '/'))
            : null;
        $subject   = $config['email_subject'] ?? '¡Nuevo contenido de Meganet!';
        $caption   = $pub->caption ?? '';

        $sentCount = 0;
        $failed    = 0;

        foreach ($recipients as $email) {
            try {
                Mail::html(
                    $this->buildHtml($subject, $caption, $videoUrl, $thumbUrl),
                    function ($msg) use ($email, $subject) {
                        $msg->to($email)->subject($subject);
                    }
                );
                $sentCount++;
            } catch (\Throwable $e) {
                Log::warning('[Email Blast] failed to send', ['to' => $email, 'e' => $e->getMessage()]);
                $failed++;
            }
        }

        if ($sentCount === 0) {
            return ['success' => false, 'error' => "Todos los envíos fallaron ({$failed} intentos)"];
        }

        return [
            'success'          => true,
            'external_post_id' => 'email_blast_' . now()->timestamp,
            'external_post_url'=> null,
            'metrics'          => ['sent' => $sentCount, 'failed' => $failed],
        ];
    }

    private function getRecipients(array $config): array
    {
        // Si hay lista explícita en config, usarla
        if (!empty($config['recipient_list'])) {
            return (array) $config['recipient_list'];
        }

        // Fallback: leads con email en la BD
        return Lead::where('company_id', $this->companyId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->limit(1000)
            ->pluck('email')
            ->toArray();
    }

    private function buildHtml(string $subject, string $caption, string $videoUrl, ?string $thumbUrl): string
    {
        $thumb = $thumbUrl
            ? "<a href=\"{$videoUrl}\"><img src=\"{$thumbUrl}\" alt=\"Video\" style=\"max-width:100%;border-radius:8px;\"></a>"
            : "<a href=\"{$videoUrl}\" style=\"display:block;background:#1a1a2e;color:#fff;padding:40px;text-align:center;border-radius:8px;font-size:18px;\">▶ Ver Video</a>";

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
      <h2 style="color:#1a1a2e;margin-top:0;">{$subject}</h2>
      <p style="color:#555;line-height:1.6;">{$caption}</p>
      <div style="margin:20px 0;">{$thumb}</div>
      <a href="{$videoUrl}" style="display:inline-block;background:#e63946;color:#fff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:16px;">Ver Video Completo →</a>
    </td></tr>
    <tr><td style="background:#f4f4f4;padding:15px;text-align:center;">
      <p style="color:#999;font-size:12px;margin:0;">Has recibido este mensaje porque eres cliente de Meganet.<br>
      Para no recibir más comunicaciones de marketing, contáctanos.</p>
    </td></tr>
  </table>
</body></html>
HTML;
    }
}
