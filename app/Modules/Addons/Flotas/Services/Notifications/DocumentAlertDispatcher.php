<?php

namespace App\Modules\Addons\Flotas\Services\Notifications;

use App\Models\User;
use App\Modules\Addons\Flotas\Mail\FleetDocumentAlertMail;
use App\Modules\Addons\Flotas\Models\FleetDocument;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fase 4 — Despacha alertas de vencimiento de documentos de flota.
 *
 * Independiente del FleetNotificationDispatcher (geocercas). Reutiliza los
 * transportes (Mail, EvolutionApiService) pero no el dispatcher ni su log.
 *
 * Multi-tenancy: usa el client_id del vehículo para instanciar EvolutionApiService
 * con el companyId correcto — no hereda el bug #91.
 */
class DocumentAlertDispatcher
{
    /**
     * Envía alertas por todos los canales configurados en el documento.
     * Escribe cada intento en fleet_document_alert_log.
     *
     * @return array[] resultados [{channel, user_id, status}]
     */
    public function dispatch(FleetDocument $doc, int $threshold, int $daysUntil): array
    {
        $doc->loadMissing('vehicle');
        $vehicle = $doc->vehicle;

        if (!$vehicle) {
            Log::warning("Flotas DocumentAlert: documento {$doc->id} sin vehículo. Saltando.");
            return [];
        }

        $companyId = (int) ($vehicle->client_id ?? 1);
        $channels  = $doc->alert_channels ?? [];

        if (empty($channels)) {
            return [];
        }

        $recipients = $this->resolveRecipients($companyId);

        if ($recipients->isEmpty()) {
            Log::info("Flotas DocumentAlert: sin destinatarios para company {$companyId}.");
            return [];
        }

        $results = [];

        foreach ($recipients as $user) {
            foreach ($channels as $channel) {
                $result = match ($channel) {
                    'email'    => $this->sendEmail($doc, $threshold, $daysUntil, $user),
                    'whatsapp' => $this->sendWhatsapp($doc, $threshold, $daysUntil, $user, $companyId),
                    default    => ['channel' => $channel, 'user_id' => $user->id, 'status' => 'skipped', 'error' => 'Canal no soportado'],
                };

                if ($channel === 'email' || $channel === 'whatsapp') {
                    $this->persistLog($doc->id, $threshold, $daysUntil, $result);
                }

                $results[] = $result;
            }
        }

        return $results;
    }

    private function sendEmail(FleetDocument $doc, int $threshold, int $daysUntil, User $user): array
    {
        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return ['channel' => 'email', 'user_id' => $user->id, 'status' => 'skipped',
                    'recipient' => null, 'error' => 'Usuario sin email'];
        }

        try {
            Mail::to($email)->send(new FleetDocumentAlertMail($doc, $daysUntil));
            return ['channel' => 'email', 'user_id' => $user->id, 'status' => 'sent',
                    'recipient' => $email, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning("Flotas DocumentAlert: email falló para user {$user->id}: {$e->getMessage()}");
            return ['channel' => 'email', 'user_id' => $user->id, 'status' => 'failed',
                    'recipient' => $email, 'error' => mb_substr($e->getMessage(), 0, 1000)];
        }
    }

    private function sendWhatsapp(FleetDocument $doc, int $threshold, int $daysUntil, User $user, int $companyId): array
    {
        $phone = preg_replace('/[^0-9]/', '', (string) ($user->phone ?? ''));
        if ($phone === '') {
            return ['channel' => 'whatsapp', 'user_id' => $user->id, 'status' => 'skipped',
                    'recipient' => null, 'error' => 'Usuario sin teléfono'];
        }

        $vehicle   = $doc->vehicle;
        $typeLabel = FleetDocumentAlertMail::typeLabel($doc->document_type);
        $plates    = $vehicle?->plates ?? '—';
        $fecha     = $doc->expiration_date?->format('d/m/Y') ?? '—';
        $diasText  = $daysUntil === 0 ? 'HOY' : "en {$daysUntil} día(s)";
        $url       = url('/flotas/' . $doc->vehicle_id . '?tab=documentos');

        $text = "⚠️ {$typeLabel} de {$plates} vence {$diasText} ({$fecha}).\nRevisa la ficha: {$url}";

        try {
            (new EvolutionApiService($companyId))->sendText($phone, $text);
            return ['channel' => 'whatsapp', 'user_id' => $user->id, 'status' => 'sent',
                    'recipient' => $phone, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning("Flotas DocumentAlert: WhatsApp falló para user {$user->id}: {$e->getMessage()}");
            return ['channel' => 'whatsapp', 'user_id' => $user->id, 'status' => 'failed',
                    'recipient' => $phone, 'error' => mb_substr($e->getMessage(), 0, 1000)];
        }
    }

    /**
     * Destinatarios = usuarios con permiso fleet.manage del mismo tenant.
     * Super-administradores (client_id NULL) reciben alertas de cualquier empresa.
     */
    private function resolveRecipients(int $companyId): \Illuminate\Support\Collection
    {
        return User::where(function ($q) {
                $q->whereHas('roles.permissions', fn($p) => $p->where('name', 'fleet.manage'))
                  ->orWhereHas('permissions', fn($p) => $p->where('name', 'fleet.manage'));
            })
            ->get()
            ->filter(function (User $u) use ($companyId) {
                // client_id NULL = super-administrador → recibe alertas de cualquier empresa.
                if ($u->client_id === null) {
                    return true;
                }
                return (int) $u->client_id === $companyId;
            })
            ->values();
    }

    private function persistLog(int $docId, int $threshold, int $daysUntil, array $result): void
    {
        try {
            DB::table('fleet_document_alert_log')->insert([
                'document_id' => $docId,
                'threshold'   => $threshold,
                'days_until'  => $daysUntil,
                'channel'     => $result['channel'],
                'recipient'   => $result['recipient'] ?? null,
                'status'      => $result['status'],
                'error'       => $result['error'] ?? null,
                'sent_at'     => $result['status'] === 'sent' ? now() : null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // El log no debe tumbar el envío; si el unique constraint falla, el envío
            // ya ocurrió antes — solo registrar en application log.
            Log::warning("Flotas DocumentAlert: no se pudo persistir log para doc {$docId}: {$e->getMessage()}");
        }
    }
}
