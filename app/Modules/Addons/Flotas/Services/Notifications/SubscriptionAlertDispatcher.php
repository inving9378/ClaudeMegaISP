<?php

namespace App\Modules\Addons\Flotas\Services\Notifications;

use App\Models\User;
use App\Modules\Addons\Flotas\Models\FleetSubscription;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notifica a los gestores de flota del cliente sobre recordatorios/vencimiento de
 * su trial de Flotas (item #288). Independiente de FleetNotificationDispatcher
 * (geocercas) y DocumentAlertDispatcher (documentos) — mismo patrón de transporte
 * (Mail + EvolutionApiService por company_id), sin dispatcher/log compartido.
 */
class SubscriptionAlertDispatcher
{
    /**
     * @return array[] resultados [{channel, user_id, status}]
     */
    public function dispatchTrialReminder(FleetSubscription $sub, string $eventType, int $daysLeft): array
    {
        $companyId  = (int) ($sub->client_id ?? 1);
        $recipients = $this->resolveRecipients($companyId);

        if ($recipients->isEmpty()) {
            Log::info("Flotas SubscriptionAlert: sin destinatarios para company {$companyId}.");
            return [];
        }

        $results = [];
        foreach ($recipients as $user) {
            $results[] = $this->sendEmail($sub, $eventType, $daysLeft, $user);
            $results[] = $this->sendWhatsapp($sub, $eventType, $daysLeft, $user, $companyId);
        }

        return $results;
    }

    private function message(FleetSubscription $sub, string $eventType, int $daysLeft): string
    {
        $plan     = $sub->plan_name ?? $sub->plan;
        $url      = url('/flotas/suscripciones');
        $diasText = $daysLeft <= 0 ? 'HOY' : "en {$daysLeft} día(s)";

        if ($eventType === 'trial_expiring') {
            return "⚠️ Tu prueba de Flotas ({$plan}) vence {$diasText}. Configura tu método de pago para no perder acceso: {$url}";
        }

        return "🔔 Tu prueba de Flotas ({$plan}) vence {$diasText}. Configura tu método de pago para continuar sin interrupciones: {$url}";
    }

    private function sendEmail(FleetSubscription $sub, string $eventType, int $daysLeft, User $user): array
    {
        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return ['channel' => 'email', 'user_id' => $user->id, 'status' => 'skipped', 'recipient' => null, 'error' => 'Usuario sin email'];
        }

        try {
            $body = $this->message($sub, $eventType, $daysLeft);
            Mail::raw($body, function ($message) use ($email) {
                $message->to($email)->subject('Meganet Flotas — tu prueba está por vencer');
            });
            return ['channel' => 'email', 'user_id' => $user->id, 'status' => 'sent', 'recipient' => $email, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning("Flotas SubscriptionAlert: email falló para user {$user->id}: {$e->getMessage()}");
            return ['channel' => 'email', 'user_id' => $user->id, 'status' => 'failed', 'recipient' => $email, 'error' => mb_substr($e->getMessage(), 0, 1000)];
        }
    }

    private function sendWhatsapp(FleetSubscription $sub, string $eventType, int $daysLeft, User $user, int $companyId): array
    {
        $phone = preg_replace('/[^0-9]/', '', (string) ($user->phone ?? ''));
        if ($phone === '') {
            return ['channel' => 'whatsapp', 'user_id' => $user->id, 'status' => 'skipped', 'recipient' => null, 'error' => 'Usuario sin teléfono'];
        }

        try {
            (new EvolutionApiService($companyId))->sendText($phone, $this->message($sub, $eventType, $daysLeft));
            return ['channel' => 'whatsapp', 'user_id' => $user->id, 'status' => 'sent', 'recipient' => $phone, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning("Flotas SubscriptionAlert: WhatsApp falló para user {$user->id}: {$e->getMessage()}");
            return ['channel' => 'whatsapp', 'user_id' => $user->id, 'status' => 'failed', 'recipient' => $phone, 'error' => mb_substr($e->getMessage(), 0, 1000)];
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
