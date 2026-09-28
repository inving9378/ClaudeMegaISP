<?php

namespace App\Modules\Addons\WhatsAppAgent\Services\AgenteVentas;

use App\Models\Ticket;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppConversation;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use App\Modules\Addons\WhatsAppAgent\Services\EvolutionApiService;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppCrmService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquestación del Agente de Ventas (IA): arma contexto (incluida la
 * disponibilidad real de instalación), llama al cerebro aislado
 * {@see AgenteVentasIAService}, decide enviar o retener para revisión humana,
 * y cierra el ciclo de registro — crea el prospecto en CRM y, cuando confirma
 * un día CON cupo, agenda la instalación sola (sin esperar a un humano).
 *
 * Reusa a propósito la plomería YA existente y compartida (no la duplica):
 * WhatsAppCrmService (crear prospecto + agendar instalación) y
 * EvolutionApiService (el único gateway de envío). Lo único propio de este
 * agente es el cerebro/prompt y el motor de disponibilidad.
 */
class AgenteVentasReplyService
{
    public function __construct(
        private AgenteVentasIAService            $ia,
        private AgenteVentasDisponibilidadService $disponibilidad,
        private EvolutionApiService               $evolution,
        private WhatsAppCrmService                $crm,
    ) {
    }

    public function maybeReply(WhatsAppMessage $message, WhatsAppConversation $conversation): void
    {
        if ($message->direction !== 'in') {
            return;
        }

        $incoming = trim((string) $message->body);
        if ($incoming === '') {
            return;
        }

        // Mismo candado que el bot viejo: si esta conversación tiene una
        // identificación de conciliación de pagos en curso, el agente de
        // ventas NO responde — lo maneja el FSM de Payments. Evita doble
        // respuesta y que ventas descarrile la identificación de un voucher.
        $inConciliation = DB::table('whatsapp_identification_sessions')
            ->where('source', 'gateway')
            ->where('source_conversation_id', $conversation->id)
            ->where('is_simulation', false)
            ->where(function ($q) use ($message) {
                $q->whereNotIn('state', ['resolved', 'escalated'])
                    ->orWhere('updated_at', '>=', $message->created_at);
            })
            ->exists();
        if ($inConciliation) {
            return;
        }

        $minConfidence = (float) config('whatsapp.agente_ventas_min_confidence', 0.80);

        try {
            $history         = $this->buildHistory($conversation);
            $collected       = is_array($conversation->collected_data) ? $conversation->collected_data : [];
            $disponibilidad  = $this->disponibilidad->formatoParaPrompt();

            $result = $this->ia->responder($incoming, $history, $collected, $disponibilidad);

            $intent = (string) $result['intent'];

            // ─── CRM: acumular datos + crear prospecto cuando esté listo ───
            $datos = $result['datos_prospecto'] ?? null;
            if (is_array($datos)) {
                $this->crm->accumulateData($conversation, $datos);
            }
            // createLeadIfReady() es plomería COMPARTIDA con el bot viejo y
            // solo acepta sus dos intents históricos — se los homologamos
            // aquí sin tocar ese servicio (mantiene el agente aislado).
            if (in_array($intent, ['interes_contratar', 'dando_datos', 'listo_agendar'], true)) {
                $this->crm->createLeadIfReady($conversation->fresh(), $message, 'installation_request');
            }

            $conversation->refresh();

            // ─── Cerrar el registro: agendar instalación sin esperar a un
            // humano, SOLO cuando la IA confirmó fecha/hora (ya validada
            // contra la disponibilidad real que se le pasó arriba) y ya
            // existe prospecto en CRM. Nunca crea la cuenta de cliente ni
            // cobra nada (eso lo hace un humano al concretar la visita).
            $registro = $result['registro'] ?? [];
            if (
                ($registro['listo_para_agendar'] ?? false)
                && !empty($registro['fecha_iso'])
                && $conversation->crm_id
                && $this->disponibilidad->tieneCupo(substr((string) $registro['fecha_iso'], 0, 10))
                && !Ticket::where('customer_lead', $conversation->crm_id)->exists()
            ) {
                $this->crm->scheduleInstallation($conversation, (string) $registro['fecha_iso']);
                // scheduleInstallation() ya envía su propia confirmación por
                // WhatsApp (notifyClient) — no duplicar aquí.
            }

            $confidence = (float) $result['confidence'];
            $draft      = trim((string) $result['draft']);

            if ($draft === '') {
                Log::info('AgenteVentas: sin borrador de la IA', ['message_id' => $message->id]);
                return;
            }

            if ($confidence >= $minConfidence) {
                $this->evolution->sendAndLog(
                    to:           $conversation->contact_number,
                    body:         $draft,
                    instanceSlug: $conversation->instance->slug,
                    context:      [
                        'agente_ventas'          => true,
                        'replying_to_message_id' => $message->id,
                        'confidence'             => $confidence,
                        'intent'                 => $intent,
                    ],
                );

                Log::info('AgenteVentas: enviado', [
                    'conversation_id' => $conversation->id,
                    'message_id'      => $message->id,
                    'confidence'      => $confidence,
                    'intent'          => $intent,
                ]);
                return;
            }

            // Confianza baja → guardar borrador para revisión humana en el panel.
            $existingContext = is_array($message->context) ? $message->context : [];
            $message->update([
                'context' => array_merge($existingContext, [
                    'agente_ventas_draft'         => $draft,
                    'agente_ventas_intent'        => $intent,
                    'agente_ventas_confidence'    => $confidence,
                    'agente_ventas_quick_replies' => $result['quick_replies'] ?? [],
                    'agente_ventas_skipped'       => true,
                ]),
            ]);

            Log::info('AgenteVentas: confianza baja, guardado para revisión', [
                'conversation_id' => $conversation->id,
                'message_id'      => $message->id,
                'confidence'      => $confidence,
                'threshold'       => $minConfidence,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AgenteVentas: fallo, no se envía nada', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /** Últimos 10 mensajes de la conversación, cronológico. */
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
