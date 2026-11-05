<?php

namespace App\Modules\Addons\Flotas\Services\Notifications;

use App\Modules\Addons\Hub\Services\Correo;
use App\Modules\Addons\Hub\Services\Servicios;
use App\Models\User;
use App\Modules\Addons\Flotas\Models\FleetSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifica a los gestores de flota del cliente sobre recordatorios/vencimiento de
 * su trial de Flotas (item #288). Independiente de FleetNotificationDispatcher
 * (geocercas) y DocumentAlertDispatcher (documentos) — mismo patrón de transporte
 * (Mail + WhatsAppProviderFactory por company_id), sin dispatcher/log compartido.
 *
 * Item #624: el destinatario PRINCIPAL es el cliente (email/teléfonos de su ficha,
 * client_main_information; el cliente no tiene usuario de panel). El personal con
 * fleet.subscriptions.manage recibe copia con el texto en tercera persona. El aviso
 * se manda SIEMPRE, aunque el cliente haya hecho el alta en el portal (constancia).
 */
class SubscriptionAlertDispatcher
{
    /**
     * @return array[] resultados [{channel, user_id, status}]
     */
    public function dispatchTrialReminder(FleetSubscription $sub, string $eventType, int $daysLeft): array
    {
        $companyId  = (int) ($sub->client_id ?? 1);
        $asunto = $sub->is_promo
            ? 'Meganet Flotas — tu promoción gratis está por terminar'
            : 'Meganet Flotas — tu prueba está por vencer';

        return $this->send($sub, $this->message($sub, $eventType, $daysLeft), $asunto);
    }

    /** Aviso de cambio de bloque (#621): mismos destinatarios y transporte que los avisos de prueba. */
    public function dispatchBlockChange(FleetSubscription $sub, string $mensaje): array
    {
        return $this->send($sub, $mensaje . ' Detalle: ' . url('/portal/flotas'), 'Meganet Flotas — cambio de bloque');
    }

    /** Cliente (principal, texto en 2ª persona + liga al portal) y personal (copia). */
    private function send(FleetSubscription $sub, string $mensaje, string $asunto): array
    {
        $companyId = (int) ($sub->client_id ?? 1);
        $results   = [];

        foreach ($this->clientContacts($sub) as $c) {
            $texto     = $mensaje;
            $results[] = $this->sendEmail($texto, $asunto, $c['email'], null);
            $results[] = $this->sendWhatsapp($texto, $c['phone'], $companyId, null);
        }

        $copia = 'Cliente #' . $sub->client_id . ': ' . $mensaje . ' ' . url('/flotas/suscripciones');
        foreach ($this->resolveRecipients($companyId) as $user) {
            $results[] = $this->sendEmail($copia, $asunto, $user->email, $user->id);
            $results[] = $this->sendWhatsapp($copia, $user->phone, $companyId, $user->id);
        }

        return $results;
    }

    /** Contactos de la ficha del cliente: un email y los teléfonos (phone, phone2, phone3). */
    private function clientContacts(FleetSubscription $sub): array
    {
        if ($sub->client_id === null) {
            return [];
        }
        $row = DB::table('client_main_information')->where('client_id', $sub->client_id)->first(['email', 'phone', 'phone2', 'phone3']);
        if (! $row) {
            return [];
        }
        $contacts = [['email' => $row->email, 'phone' => null]];
        $vistos   = [];
        foreach ([$row->phone, $row->phone2, $row->phone3] as $tel) {
            $d = preg_replace('/[^0-9]/', '', (string) $tel);
            if ($d !== '' && ! isset($vistos[$d])) {
                $vistos[$d] = true;
                $contacts[] = ['email' => null, 'phone' => $d];
            }
        }
        return $contacts;
    }

    private function message(FleetSubscription $sub, string $eventType, int $daysLeft): string
    {
        $plan     = $sub->plan_name ?? $sub->plan;
        $url      = url('/portal/flotas');
        $diasText = $daysLeft <= 0 ? 'HOY' : "en {$daysLeft} día(s)";

        if ($sub->is_promo) {
            return "🔔 Tu promoción gratis de Flotas ({$plan}) termina {$diasText}. A partir de entonces el servicio empieza a cobrarse dentro de tu factura mensual. Detalle: {$url}";
        }

        if ($eventType === 'trial_expiring') {
            return "⚠️ Tu prueba de Flotas ({$plan}) vence {$diasText}. Configura tu método de pago para no perder acceso: {$url}";
        }

        return "🔔 Tu prueba de Flotas ({$plan}) vence {$diasText}. Configura tu método de pago para continuar sin interrupciones: {$url}";
    }

    private function sendEmail(string $body, string $subject, ?string $email, ?int $userId): array
    {
        $email = trim((string) $email);
        if ($email === '') {
            return ['channel' => 'email', 'user_id' => $userId, 'status' => 'skipped', 'recipient' => null, 'error' => 'Sin email'];
        }

        try {
            Correo::mailer('flotas.correo')->raw($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });
            return ['channel' => 'email', 'user_id' => $userId, 'status' => 'sent', 'recipient' => $email, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning("Flotas SubscriptionAlert: email falló para destino {$userId}: {$e->getMessage()}");
            return ['channel' => 'email', 'user_id' => $userId, 'status' => 'failed', 'recipient' => $email, 'error' => mb_substr($e->getMessage(), 0, 1000)];
        }
    }

    private function sendWhatsapp(string $body, ?string $phone, int $companyId, ?int $userId): array
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($phone === '') {
            return ['channel' => 'whatsapp', 'user_id' => $userId, 'status' => 'skipped', 'recipient' => null, 'error' => 'Sin teléfono'];
        }

        try {
            Servicios::whatsapp('flotas.suscripciones', $companyId)->sendText($phone, $body);
            return ['channel' => 'whatsapp', 'user_id' => $userId, 'status' => 'sent', 'recipient' => $phone, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning("Flotas SubscriptionAlert: WhatsApp falló para destino {$userId}: {$e->getMessage()}");
            return ['channel' => 'whatsapp', 'user_id' => $userId, 'status' => 'failed', 'recipient' => $phone, 'error' => mb_substr($e->getMessage(), 0, 1000)];
        }
    }

    /** Destinatarios = usuarios con permiso fleet.subscriptions.manage del mismo tenant. */
    private function resolveRecipients(int $companyId): Collection
    {
        return User::where(function ($q) {
                $q->whereHas('roles.permissions', fn ($p) => $p->where('name', 'fleet.subscriptions.manage'))
                  ->orWhereHas('permissions', fn ($p) => $p->where('name', 'fleet.subscriptions.manage'));
            })
            ->get()
            ->filter(fn (User $u) => $u->client_id === null || (int) $u->client_id === $companyId)
            ->values();
    }
}
