<?php

namespace App\Services\Referrals;

use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\ReferralCommission;
use App\Models\Referrals\ReferralNotificationLog;
use App\Models\Referrals\ReferralNotificationTemplate;
use App\Models\Referrals\ReferralProspect;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\Referral;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Support\Facades\Log;

class ReferralWhatsAppNotifier
{
    public function notifyProspectConverted(Referral $referral, ?ReferralProspect $prospect): void
    {
        $embajador = Client::with('client_main_information')->find($referral->embajador_id);
        if (!$embajador) {
            return;
        }

        $this->send('prospect_converted', $embajador, [
            'embajador_name' => $this->resolveName($embajador),
            'prospect_name'  => $prospect?->name ?? 'tu referido',
        ]);
    }

    public function notifyThresholdCovered(Referral $referral): void
    {
        $embajador = Client::with('client_main_information')->find($referral->embajador_id);
        $referred  = Client::with('client_main_information')->find($referral->referred_client_id);
        if (!$embajador) {
            return;
        }

        $this->send('threshold_covered', $embajador, [
            'embajador_name' => $this->resolveName($embajador),
            'referido_name'  => $this->resolveName($referred),
        ]);
    }

    public function notifyCommissionGenerated(ReferralCommission $commission): void
    {
        $embajador = Client::with('client_main_information')->find($commission->beneficiary_id);
        if (!$embajador) {
            return;
        }

        $referral = Referral::with(['referredClient.client_main_information'])
            ->find($commission->referral_id);

        $daysUntilApply = $commission->apply_after_at
            ? max(0, (int) now()->diffInDays($commission->apply_after_at, false))
            : 15;

        $this->send('commission_generated', $embajador, [
            'embajador_name'  => $this->resolveName($embajador),
            'amount'          => number_format((float) $commission->commission_amount, 2),
            'referido_name'   => $referral ? $this->resolveName($referral->referredClient) : 'tu referido',
            'days_until_apply' => $daysUntilApply,
        ]);
    }

    public function notifyCommissionsApplied(Client $embajador, float $total, int $count): void
    {
        $embajador->loadMissing('client_main_information');

        $this->send('commissions_applied', $embajador, [
            'embajador_name' => $this->resolveName($embajador),
            'total_amount'   => number_format($total, 2),
            'count'          => $count,
        ]);
    }

    public function notifyRewardExpiringSoon(ReferralReward $reward, int $daysRemaining): void
    {
        $embajador = Client::with('client_main_information')->find($reward->embajador_id);
        if (!$embajador) {
            return;
        }

        $this->send('reward_expiring_soon', $embajador, [
            'embajador_name' => $this->resolveName($embajador),
            'days_remaining' => $daysRemaining,
        ]);
    }

    public function notifyEmbajadorActivated(ClientReferralProfile $profile): void
    {
        $profile->loadMissing('client.client_main_information');
        $embajador = $profile->client;

        if (!$embajador) {
            return;
        }

        $info = $embajador->client_main_information;
        $name = trim(($info?->name ?? '') . ' ' . ($info?->father_last_name ?? '')) ?: 'Estimado cliente';

        $body = "🎉 ¡Felicidades {$name}! Has sido activado como Embajador Meganet.\n\n"
              . "Tu código de referido es: *{$profile->referral_code}*\n"
              . "Comparte este link: {$profile->referral_link}\n\n"
              . "Por cada cliente que refieras y pague \$1,500 en servicios, "
              . "ganarás comisiones automáticas. ¡Gracias por crecer con nosotros! 🚀";

        $this->sendRaw('embajador_activated', $embajador, $body);
    }

    // ── Internals ────────────────────────────────────────────────────────────────

    private function sendRaw(string $eventType, Client $embajador, string $body): void
    {
        $phone = $embajador->client_main_information?->phone ?? '';
        if (empty(preg_replace('/\D/', '', $phone))) {
            Log::warning('ReferralWhatsAppNotifier: sin teléfono para embajador', [
                'client_id'  => $embajador->id,
                'event_type' => $eventType,
            ]);
            return;
        }

        $success = false;
        $error   = null;

        try {
            $jid = EvolutionApiService::phoneToJid($phone);
            app(EvolutionApiService::class)->sendText($jid, $body);
            $success = true;
            Log::info('ReferralWhatsAppNotifier: notificación enviada', [
                'client_id'  => $embajador->id,
                'event_type' => $eventType,
            ]);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::warning('ReferralWhatsAppNotifier: fallo al enviar notificación', [
                'client_id'  => $embajador->id,
                'event_type' => $eventType,
                'error'      => $error,
            ]);
        }

        ReferralNotificationLog::create([
            'client_id'     => $embajador->id,
            'event_type'    => $eventType,
            'body_sent'     => $body,
            'sent_at'       => now(),
            'success'       => $success,
            'error_message' => $error,
        ]);
    }

    private function send(string $eventType, Client $embajador, array $placeholders): void
    {
        $template = ReferralNotificationTemplate::forEvent($eventType);
        if (!$template) {
            return;
        }

        $phone = $embajador->client_main_information?->phone ?? '';
        if (empty(preg_replace('/\D/', '', $phone))) {
            Log::warning('ReferralWhatsAppNotifier: sin teléfono para embajador', [
                'client_id'  => $embajador->id,
                'event_type' => $eventType,
            ]);
            return;
        }

        $body    = $template->render($placeholders);
        $success = false;
        $error   = null;

        try {
            $jid = EvolutionApiService::phoneToJid($phone);
            app(EvolutionApiService::class)->sendText($jid, $body);
            $success = true;
            Log::info('ReferralWhatsAppNotifier: notificación enviada', [
                'client_id'  => $embajador->id,
                'event_type' => $eventType,
            ]);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::warning('ReferralWhatsAppNotifier: fallo al enviar notificación', [
                'client_id'  => $embajador->id,
                'event_type' => $eventType,
                'error'      => $error,
            ]);
        }

        ReferralNotificationLog::create([
            'client_id'     => $embajador->id,
            'event_type'    => $eventType,
            'body_sent'     => $body,
            'sent_at'       => now(),
            'success'       => $success,
            'error_message' => $error,
        ]);
    }

    private function resolveName(?Client $client): string
    {
        if (!$client) {
            return 'cliente';
        }
        $info = $client->client_main_information;
        return trim(($info?->name ?? '') . ' ' . ($info?->father_last_name ?? '')) ?: 'cliente';
    }
}
