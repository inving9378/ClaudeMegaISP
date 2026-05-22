<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Models\Seller;
use App\Models\Ticket;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppConversation;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppMessage;
use App\Modules\Core\CRM\Models\Crm;
use App\Modules\Core\CRM\Models\CrmLeadInformation;
use App\Modules\Core\CRM\Models\CrmMainInformation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Puente entre el WhatsApp Agent y el CRM de megaisp.
 *
 * Flujo end-to-end:
 *   1. La IA extrae datos del cliente (nombre, dirección, etc.) en cada mensaje
 *      y los devuelve en result['extracted_data'].
 *   2. WhatsAppAutoReplyService llama accumulateData() para hacer merge en
 *      whatsapp_conversations.collected_data sin perder datos previos.
 *   3. Si la intent es installation_request Y hay nombre (mínimo viable),
 *      createLeadIfReady() crea el Crm + CrmMainInformation + CrmLeadInformation
 *      en una transacción y vuelca conversation.crm_id.
 *   4. Cuando un agente humano (o futuro automatismo) decide agendar, llama a
 *      scheduleInstallation() que crea un Ticket tipo "Solicitud de función"
 *      vinculado al crm_id y envía la confirmación por WhatsApp.
 *
 * Direcciones: resolveAddress() intenta resolver state/municipality/colony por
 * nombre + CP usando las tablas geo (states/municipalities/colonies). Si no
 * todos los IDs salen, deja los faltantes en NULL y marca
 * crm_status="Dirección a validar" para que un vendedor humano complete antes
 * de agendar.
 */
class WhatsAppCrmService
{
    public function __construct(
        private EvolutionApiService $evolution,
    ) {
    }

    /**
     * Merge no destructivo del extracted_data en conversation.collected_data.
     * Nunca pisa un valor existente con null — solo agrega/actualiza valores
     * que realmente vinieron en este turno.
     */
    public function accumulateData(WhatsAppConversation $conversation, array $extracted): array
    {
        $current = is_array($conversation->collected_data) ? $conversation->collected_data : [];
        $clean   = array_filter($extracted, fn ($v) => $v !== null && $v !== '');

        if (empty($clean)) {
            return $current; // nada útil este turno
        }

        $merged = array_merge($current, $clean);

        $conversation->update(['collected_data' => $merged]);
        return $merged;
    }

    /**
     * Crea un Crm vinculado a la conversación SI:
     *   - intent es installation_request o schedule_visit
     *   - aún no hay crm_id en la conversación (no duplicar)
     *   - tenemos al menos un nombre (mínimo viable; el teléfono ya lo tenemos
     *     en conversation.contact_number)
     *
     * Returns: el Crm creado o null si todavía no aplica.
     */
    public function createLeadIfReady(
        WhatsAppConversation $conversation,
        WhatsAppMessage $message,
        string $intent
    ): ?Crm {
        if ($conversation->crm_id) {
            return null; // ya existe
        }

        if (!in_array($intent, ['installation_request', 'schedule_visit'], true)) {
            return null;
        }

        $data = is_array($conversation->collected_data) ? $conversation->collected_data : [];
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            return null; // mínimo: nombre
        }

        $address = $this->resolveAddress($data);

        try {
            $crm = DB::transaction(function () use ($conversation, $data, $nombre, $address) {
                $crmRow = Crm::create(['enable_same_name_or_rfc' => 0]);

                CrmMainInformation::create([
                    'crm_id'            => $crmRow->id,
                    'name'              => $nombre,
                    'father_last_name'  => $data['apellido_paterno'] ?? null,
                    'mother_last_name'  => $data['apellido_materno'] ?? null,
                    'email'             => $data['email']            ?? null,
                    'phone'             => $conversation->contact_number,
                    'phone2'            => $data['phone2']           ?? null,
                    'street'            => $data['calle']            ?? null,
                    'external_number'   => $data['numero_ext']       ?? null,
                    'internal_number'   => $data['numero_int']       ?? null,
                    'zip'               => $data['cp']               ?? null,
                    'state_id'          => $address['state_id'],
                    'municipality_id'   => $address['municipality_id'],
                    'colony_id'         => $address['colony_id'],
                    'address'           => $this->buildAddressText($data),
                    'high_date'         => now()->toDateString(),
                ]);

                $sellerId = null;
                if ($conversation->seller_id) {
                    $sellerId = Seller::where('user_id', $conversation->seller_id)->value('id');
                }

                CrmLeadInformation::create([
                    'crm_id'          => $crmRow->id,
                    'score'           => 0,
                    'crm_status'      => $address['resolved'] ? 'Nuevo' : 'Dirección a validar',
                    'source'          => 'WhatsApp',
                    'owner_id'        => $sellerId,
                    'state_id'        => $address['state_id'],
                    'municipality_id' => $address['municipality_id'],
                    'colony_id'       => $address['colony_id'],
                ]);

                return $crmRow;
            });

            $conversation->update(['crm_id' => $crm->id]);

            Log::info('WhatsApp CRM: prospecto creado', [
                'crm_id'           => $crm->id,
                'conversation_id'  => $conversation->id,
                'message_id'       => $message->id,
                'address_resolved' => $address['resolved'],
                'crm_status'       => $address['resolved'] ? 'Nuevo' : 'Dirección a validar',
            ]);

            return $crm;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp CRM: fallo creando prospecto', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Crea un Ticket tipo "Solicitud de función" agendando la instalación.
     * El cliente recibe automáticamente una confirmación por WhatsApp con la
     * fecha + hora + dirección registrada.
     */
    public function scheduleInstallation(
        WhatsAppConversation $conversation,
        string $fechaIso,
        ?int $tecnicoUserId = null,
        ?string $notas = null
    ): ?Ticket {
        if (!$conversation->crm_id) {
            Log::warning('WhatsApp CRM: scheduleInstallation sin crm_id', [
                'conversation_id' => $conversation->id,
            ]);
            return null;
        }

        $main = CrmMainInformation::where('crm_id', $conversation->crm_id)->first();

        try {
            $ticket = Ticket::create([
                'customer_lead' => $conversation->crm_id,
                'type'          => 'Solicitud de función',
                'topic'         => 'Instalación WhatsApp',
                'estado'        => 'Nuevo',
                'priority'      => 'Media',
                'group'         => 'Instalaciones',
                'assigned_to'   => $tecnicoUserId,
                'date_time'     => $fechaIso,
                'phone'         => $conversation->contact_number,
                'colony_id'     => $main->colony_id ?? null,
                'reporter_id'   => auth()->id(),
                'description'   => $this->buildTicketDescription($conversation, $main, $notas),
            ]);

            $this->notifyClient($conversation, $fechaIso, $main, $ticket);

            Log::info('WhatsApp CRM: instalación agendada', [
                'crm_id'     => $conversation->crm_id,
                'ticket_id'  => $ticket->id,
                'fecha'      => $fechaIso,
                'tecnico_id' => $tecnicoUserId,
            ]);

            return $ticket;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp CRM: fallo agendando instalación', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolución best-effort de dirección de texto libre a state_id /
     * municipality_id / colony_id. Estrategia conservadora:
     *   - colony_id: match exacto por nombre + CP. Si no hay coincidencia
     *     única con CP, devuelve null (no adivinar).
     *   - municipality_id: del colony match, o por texto si colony falló.
     *   - state_id: del municipality match, o por texto si los anteriores fallaron.
     *
     * Returns: ['state_id'=>?int, 'municipality_id'=>?int, 'colony_id'=>?int,
     *          'resolved'=>bool] — resolved=true solo si los 3 IDs salen.
     */
    public function resolveAddress(array $data): array
    {
        $coloniaTxt   = trim((string) ($data['colonia_texto']   ?? ''));
        $municipioTxt = trim((string) ($data['municipio_texto'] ?? ''));
        $estadoTxt    = trim((string) ($data['estado_texto']    ?? ''));
        $cp           = trim((string) ($data['cp']              ?? ''));

        $stateId = $municipalityId = $colonyId = null;

        // 1) Colonia por (nombre + CP) — la combinación más confiable
        if ($coloniaTxt !== '' && $cp !== '') {
            $colony = DB::table('colonies')
                ->where('name', 'like', "%{$coloniaTxt}%")
                ->where('data', 'like', "%{$cp}%")
                ->first();
            if ($colony) {
                $colonyId       = $colony->id;
                $municipalityId = $colony->municipality_id ?? null;
            }
        }

        // 2) Municipio (si no salió desde colony)
        if ($municipalityId === null && $municipioTxt !== '') {
            $muni = DB::table('municipalities')
                ->where('name', 'like', "%{$municipioTxt}%")
                ->first();
            if ($muni) {
                $municipalityId = $muni->id;
                $stateId        = $muni->state_id ?? null;
            }
        }

        // 3) Estado (si no salió desde municipality)
        if ($stateId === null && $estadoTxt !== '') {
            $state = DB::table('states')
                ->where('name', 'like', "%{$estadoTxt}%")
                ->first();
            if ($state) {
                $stateId = $state->id;
            }
        }

        // Si tengo municipalityId pero no stateId, sacarlo de la tabla
        if ($stateId === null && $municipalityId !== null) {
            $stateId = DB::table('municipalities')->where('id', $municipalityId)->value('state_id');
        }

        return [
            'state_id'        => $stateId,
            'municipality_id' => $municipalityId,
            'colony_id'       => $colonyId,
            'resolved'        => ($stateId !== null && $municipalityId !== null && $colonyId !== null),
        ];
    }

    private function buildAddressText(array $data): ?string
    {
        $parts = array_filter([
            trim(($data['calle'] ?? '') . ' ' . ($data['numero_ext'] ?? '')),
            !empty($data['numero_int'])      ? "Int. {$data['numero_int']}"      : null,
            !empty($data['colonia_texto'])   ? "Col. {$data['colonia_texto']}"   : null,
            $data['municipio_texto']         ?? null,
            $data['estado_texto']            ?? null,
            !empty($data['cp'])              ? "CP {$data['cp']}"                : null,
        ], fn ($x) => is_string($x) && trim($x) !== '');

        return empty($parts) ? null : implode(', ', $parts);
    }

    private function buildTicketDescription(
        WhatsAppConversation $conversation,
        ?CrmMainInformation $main,
        ?string $notas
    ): string {
        $lines = [
            'Instalación agendada desde WhatsApp Agent.',
            'Cliente: ' . ($main->name ?? $conversation->contact_name ?? $conversation->contact_number),
            'Teléfono: ' . $conversation->contact_number,
            'Dirección: ' . ($main->address ?? '(sin dirección registrada)'),
        ];
        if ($notas) {
            $lines[] = 'Notas: ' . $notas;
        }
        $lines[] = 'CRM ID: ' . $conversation->crm_id;
        return implode("\n", $lines);
    }

    private function notifyClient(
        WhatsAppConversation $conversation,
        string $fechaIso,
        ?CrmMainInformation $main,
        Ticket $ticket
    ): void {
        try {
            $fechaFormat = \Carbon\Carbon::parse($fechaIso)->locale('es')->isoFormat('dddd D [de] MMMM, HH:mm');
        } catch (\Throwable $e) {
            $fechaFormat = $fechaIso;
        }

        $direccion = $main->address ?? 'la dirección registrada';

        $body = "✅ Tu instalación está agendada para el {$fechaFormat}. Te esperamos en {$direccion}. Si necesitas reprogramar, responde a este mensaje. Folio: #{$ticket->id}";

        $this->evolution->sendAndLog(
            to:           $conversation->contact_number,
            body:         $body,
            instanceSlug: $conversation->instance->slug,
            context:      [
                'auto_notification' => 'installation_scheduled',
                'ticket_id'         => $ticket->id,
                'crm_id'            => $conversation->crm_id,
            ],
        );
    }
}
