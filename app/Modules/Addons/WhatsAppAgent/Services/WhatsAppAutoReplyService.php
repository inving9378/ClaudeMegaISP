<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppConversation;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

/**
 * Modo auto-respuesta del WhatsApp Agent.
 *
 * Cuando WHATSAPP_AUTO_REPLY=true en config, el WhatsAppRouterService invoca
 * maybeReply() después de persistir un mensaje entrante. Este service:
 *   1. Construye contexto (cliente + historial) igual que el endpoint /ia-assist.
 *   2. Llama a WhatsAppIAService::assist() para obtener borrador + intent.
 *   3. Si intent.confidence >= WHATSAPP_AUTO_REPLY_MIN_CONFIDENCE:
 *        → envía el borrador directamente via EvolutionApiService::sendAndLog()
 *        → marca el mensaje OUT con context.auto_reply=true para auditoría.
 *   4. Si confidence < umbral:
 *        → NO envía nada.
 *        → guarda el borrador y la intent en whatsapp_messages.context del
 *          mensaje IN, para que el panel lo muestre cuando un agente humano
 *          abra la conversación y pueda enviarlo manualmente si lo aprueba.
 *
 * Errores de IA o envío se loguean como warning — el flujo del webhook NO se
 * interrumpe (el mensaje IN ya quedó persistido en handleMessage()).
 */
class WhatsAppAutoReplyService
{
    public function __construct(
        private WhatsAppIAService $ia,
        private EvolutionApiService $evolution,
        private WhatsAppCrmService $crm,
    ) {
    }

    public function maybeReply(WhatsAppMessage $message, WhatsAppConversation $conversation): void
    {
        // Solo procesar mensajes entrantes — evita loops si por error
        // un mensaje OUT termina pasando por aquí.
        if ($message->direction !== 'in') {
            return;
        }

        // Ignorar mensajes sin texto (media, stickers, etc.) — no hay nada
        // para que la IA analice.
        $incoming = trim((string) $message->body);
        if ($incoming === '') {
            return;
        }

        $minConfidence = (float) config('whatsapp.auto_reply_min_confidence', 0.80);
        $tone          = (string) config('whatsapp.auto_reply_tone', 'friendly');

        try {
            $clientContext = $this->buildClientContext($conversation);
            $history       = $this->buildHistory($conversation);
            // Pasar al prompt los datos ya acumulados en turnos previos para
            // que la IA NO vuelva a pedirlos. Sin esto, el cliente se frustra
            // dando el mismo email/dirección 5+ veces.
            $collected     = is_array($conversation->collected_data) ? $conversation->collected_data : [];

            $result = $this->ia->assist($incoming, $history, $clientContext, $tone, $collected);

            // ─── Integración CRM ──────────────────────────────────────────
            // Sin bloquear el envío del borrador: acumular extracted_data en
            // collected_data y crear el lead cuando haya lo mínimo. Errores
            // van al log del CrmService, no propagan.
            $extracted = $result['extracted_data'] ?? null;
            $intent    = (string) ($result['intent']['primary'] ?? '');
            if (is_array($extracted)) {
                $this->crm->accumulateData($conversation, $extracted);
            }
            $this->crm->createLeadIfReady($conversation->fresh(), $message, $intent);
            // ──────────────────────────────────────────────────────────────

            $confidence = (float) ($result['intent']['confidence'] ?? 0);
            $draft      = trim((string) ($result['draft'] ?? ''));

            if ($draft === '') {
                Log::info('WhatsApp auto-reply: IA no devolvió borrador', [
                    'message_id' => $message->id,
                ]);
                return;
            }

            if ($confidence >= $minConfidence) {
                $this->evolution->sendAndLog(
                    to:           $conversation->contact_number,
                    body:         $draft,
                    instanceSlug: $conversation->instance->slug,
                    context:      [
                        'auto_reply'             => true,
                        'replying_to_message_id' => $message->id,
                        'confidence'             => $confidence,
                        'intent'                 => $result['intent']['primary'] ?? null,
                        'tone'                   => $tone,
                    ],
                );

                Log::info('WhatsApp auto-reply: enviado', [
                    'conversation_id' => $conversation->id,
                    'message_id'      => $message->id,
                    'confidence'      => $confidence,
                    'intent'          => $result['intent']['primary'] ?? null,
                ]);
                return;
            }

            // Confidence baja → guardar borrador para revisión humana
            $existingContext = is_array($message->context) ? $message->context : [];
            $message->update([
                'context' => array_merge($existingContext, [
                    'ai_draft'             => $draft,
                    'ai_intent'            => $result['intent']['primary'] ?? null,
                    'ai_intent_label'      => $result['intent']['primary_label'] ?? null,
                    'ai_confidence'        => $confidence,
                    'ai_quick_replies'     => $result['quick_replies'] ?? [],
                    'ai_suggested_actions' => $result['suggested_actions'] ?? [],
                    'auto_reply_skipped'   => true,
                ]),
            ]);

            Log::info('WhatsApp auto-reply: confianza baja, guardado para revisión', [
                'conversation_id' => $conversation->id,
                'message_id'      => $message->id,
                'confidence'      => $confidence,
                'threshold'       => $minConfidence,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp auto-reply: fallo, no se envía nada', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Construye el contexto del cliente para la IA. Misma estructura que
     * WhatsAppPanelController::buildClientContext — duplicado a propósito
     * para no acoplar Service ↔ Controller.
     */
    private function buildClientContext(WhatsAppConversation $conversation): array
    {
        $client = $conversation->client;
        if (!$client) {
            return [];
        }

        $main = $client->client_main_information;

        return array_filter([
            'name'         => $main->name ?? $conversation->contact_name ?? '',
            'plan'         => $client->internet_plan->name ?? null,
            'status'       => $main->estado ?? null,
            'last_payment' => $main->fecha_pago ?? null,
            'seniority'    => $client->created_at?->diffForHumans(),
            'open_tickets' => method_exists($client, 'tickets')
                ? (string) $client->tickets()->where('status', 'open')->count()
                : '0',
            'balance'      => '$' . number_format((float) ($client->balance->amount ?? 0), 2) . ' MXN',
        ]);
    }

    /**
     * Últimos 10 mensajes de la conversación, ordenados cronológicamente,
     * en el formato que espera WhatsAppIAService::assist().
     */
    private function buildHistory(WhatsAppConversation $conversation): array
    {
        return $conversation->messages()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->sortBy('created_at')
            ->map(fn ($m) => ['direction' => $m->direction, 'body' => (string) $m->body])
            ->values()
            ->toArray();
    }
}
