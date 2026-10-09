<?php

namespace App\Modules\Addons\MonitoreoOficial\Services;

use App\Mail\StandardMail;
use App\Models\User;
use App\Modules\Addons\Hub\Services\Correo;
use App\Modules\Addons\Hub\Services\Servicios;
use App\Modules\Addons\MonitoreoOficial\Models\MonitoringCaseDocument;
use App\Notifications\StandardNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fase 6 ("notificaciones"): avisa cuando DocumentAnalysisService (Fase 5) marcó un
 * documento con cambio relevante. 3 canales, cada uno INDEPENDIENTE y best-effort —
 * que uno falle no debe tumbar a los demás ni la cola:
 *   - Campana interna (core-bell): a quien subió el documento y a quien creó el
 *     expediente (si son usuarios reales del sistema) — siempre, sin configurar nada.
 *   - Correo: solo si el expediente tiene notify_email configurado.
 *   - WhatsApp: solo si el expediente tiene notify_whatsapp configurado.
 * Reusa los servicios compartidos del núcleo (Correo, Servicios::whatsapp,
 * StandardNotification/StandardMail) — nada de clientes propios.
 */
class CaseChangeNotificationService
{
    private const CLAVE = 'monitoreo-oficial.cambio';

    public function notificar(MonitoringCaseDocument $document): void
    {
        $case = $document->case;
        if (! $case) {
            return;
        }

        $titulo = "Monitoreo Oficial: cambio detectado en «{$case->name}»";
        $cuerpo = ($document->ai_change_summary ?: $document->ai_summary) ?: 'Se detectó un cambio en el expediente.';
        $url = url('/monitoreo-oficial/expedientes/' . $case->id);

        $this->avisarCampana($case, $document, $titulo, $cuerpo, $url);
        $this->avisarCorreo($case, $titulo, $cuerpo, $url);
        $this->avisarWhatsapp($case, $titulo, $cuerpo, $url);
    }

    private function avisarCampana($case, MonitoringCaseDocument $document, string $titulo, string $cuerpo, string $url): void
    {
        $destinatarios = collect([$document->uploader, $case->created_by ? User::find($case->created_by) : null])
            ->filter()
            ->unique('id');

        foreach ($destinatarios as $user) {
            try {
                $payload = new class($titulo, $cuerpo, $case->id) {
                    public function __construct(public string $topic, public string $resumen, public int $case_id) {}
                    public function toArray(): array
                    {
                        return ['topic' => $this->topic, 'reporter' => 'Monitoreo Oficial', 'resumen' => $this->resumen, 'case_id' => $this->case_id, 'created_at' => now()->toDateTimeString()];
                    }
                };
                $user->notify(new StandardNotification($payload, ['database']));
            } catch (Throwable $e) {
                Log::warning('[MonitoreoOficial] Aviso de campana falló', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function avisarCorreo($case, string $titulo, string $cuerpo, string $url): void
    {
        if (! $case->notify_email) {
            return;
        }

        try {
            $mail = new StandardMail([
                'title' => $titulo,
                'body' => nl2br(e($cuerpo)) . '<br><br><a href="' . e($url) . '">Ver expediente</a>',
            ]);
            Correo::enviar(self::CLAVE, $case->notify_email, $mail);
        } catch (Throwable $e) {
            Log::warning('[MonitoreoOficial] Aviso por correo falló', ['case_id' => $case->id, 'error' => $e->getMessage()]);
        }
    }

    private function avisarWhatsapp($case, string $titulo, string $cuerpo, string $url): void
    {
        if (! $case->notify_whatsapp) {
            return;
        }

        try {
            $texto = "{$titulo}\n\n{$cuerpo}\n\n{$url}";
            $proveedor = Servicios::whatsapp(self::CLAVE);
            // normalizeRecipient() es del propio proveedor (interfaz WhatsAppProviderInterface):
            // JID de Evolution o E.164 de Meta según cuál esté activo — nunca se asume uno.
            $proveedor->sendText($proveedor->normalizeRecipient($case->notify_whatsapp), $texto);
        } catch (Throwable $e) {
            Log::warning('[MonitoreoOficial] Aviso por WhatsApp falló', ['case_id' => $case->id, 'error' => $e->getMessage()]);
        }
    }
}
