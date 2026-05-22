<?php

namespace App\Modules\Addons\WhatsAppAgent\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\User;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppConversation;
use App\Modules\Addons\WhatsAppAgent\Services\EvolutionApiService;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppCrmService;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppIAService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppPanelController extends Controller
{
    /** GET /whatsapp — vista del panel principal */
    public function index()
    {
        return view('addon-whatsapp-agent::panel', [
            'fakeMode' => (bool) config('whatsapp.fake', false),
        ]);
    }

    /**
     * GET /whatsapp/api/conversations
     * Listado paginado; vendedor solo ve sus prospectos.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user      = auth()->user();
        $isSeller  = $user && $user->hasRole(ComunConstantsController::SELLER_ROLE);

        $query = WhatsAppConversation::with(['lastMessage', 'client', 'instance'])
            ->when($request->filled('instance'), fn ($q) => $q->where('instance_id', $request->instance))
            ->when($request->filled('status'),   fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($q2) use ($search) {
                    $q2->where('contact_name', 'like', "%{$search}%")
                       ->orWhere('contact_number', 'like', "%{$search}%");
                });
            })
            ->when($isSeller, fn ($q) => $q->where('seller_id', $user->id))
            ->orderByDesc('last_message_at')
            ->paginate(30);

        return response()->json($query);
    }

    /**
     * GET /whatsapp/api/conversations/{id}/messages
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $conversation = WhatsAppConversation::findOrFail($id);

        if (!$this->canAccess($conversation)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $messages = $conversation->messages()
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($messages);
    }

    /**
     * POST /whatsapp/api/conversations/{id}/send
     */
    public function send(Request $request, int $id): JsonResponse
    {
        $request->validate(['body' => 'required|string|max:4096']);

        $conversation = WhatsAppConversation::with('instance')->findOrFail($id);

        if (!$this->canAccess($conversation)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $user = auth()->user();

        $message = app(EvolutionApiService::class)->sendAndLog(
            to:           $conversation->contact_number,
            body:         $request->body,
            instanceSlug: $conversation->instance->slug,
            context:      ['sent_by' => $user->id ?? null],
        );

        return response()->json($message, 201);
    }

    /**
     * POST /whatsapp/api/conversations/{id}/mark-read
     */
    public function markRead(int $id): JsonResponse
    {
        $conversation = WhatsAppConversation::findOrFail($id);

        if (!$this->canAccess($conversation)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $conversation->markRead();
        return response()->json(['success' => true]);
    }

    /**
     * POST /whatsapp/api/conversations/{id}/close
     */
    public function close(int $id): JsonResponse
    {
        $conversation = WhatsAppConversation::findOrFail($id);

        if (!$this->canAccess($conversation)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $conversation->update(['status' => 'closed']);
        return response()->json(['success' => true]);
    }

    /**
     * POST /whatsapp/api/conversations/{id}/ia-assist
     * Genera borrador + intención + acciones sugeridas para el último mensaje entrante.
     */
    public function iaAssist(Request $request, int $id): JsonResponse
    {
        $conversation = WhatsAppConversation::with([
            'messages' => fn ($q) => $q->orderByDesc('created_at')->limit(10),
            'client.client_main_information',
            'instance',
        ])->findOrFail($id);

        if (!$this->canAccess($conversation)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $lastIncoming = $conversation->messages
            ->where('direction', 'in')
            ->sortByDesc('created_at')
            ->first();

        if (!$lastIncoming) {
            return response()->json(['error' => 'No hay mensaje entrante para analizar'], 422);
        }

        $clientContext = $this->buildClientContext($conversation);

        $history = $conversation->messages
            ->sortBy('created_at')
            ->take(10)
            ->map(fn ($m) => ['direction' => $m->direction, 'body' => (string) $m->body])
            ->values()
            ->toArray();

        $tone = (string) $request->input('tone', 'friendly');

        if (config('whatsapp.fake')) {
            return response()->json($this->fakeAssist($clientContext, $tone));
        }

        try {
            $result = app(WhatsAppIAService::class)->assist(
                (string) $lastIncoming->body,
                $history,
                $clientContext,
                $tone
            );
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('WhatsApp IA assist error', ['error' => $e->getMessage()]);
            return response()->json([
                'error'   => 'IA no disponible',
                'message' => $e->getMessage(),
            ], 503);
        }
    }

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

    private function fakeAssist(array $clientContext, string $tone): array
    {
        $name = $clientContext['name'] ?? 'cliente';
        return [
            'intent' => [
                'primary'              => 'upgrade_plan',
                'primary_label'        => 'Upgrade de plan',
                'confidence'           => 0.94,
                'secondary'            => 'price_inquiry',
                'secondary_label'      => 'Consulta precio',
                'secondary_confidence' => 0.61,
            ],
            'draft'         => "¡Claro {$name}! Contamos con planes de 20, 30 y 50 Mbps. El upgrade a 20 Mbps tiene un costo adicional de \$80 MXN/mes. ¿Te gustaría que lo procesemos ahora?",
            'quick_replies' => ['Procesamos el upgrade ahora', 'Te contactamos mañana', 'Ver todos los planes'],
            'suggested_actions' => [
                ['label' => 'Ofrecer upgrade',   'detail' => 'Plan 20 Mbps disponible — +$80/mes',         'type' => 'offer'],
                ['label' => 'Crear ticket',      'detail' => 'Dar seguimiento post-upgrade',              'type' => 'ticket'],
                ['label' => 'Aplicar descuento', 'detail' => 'Cliente frecuente — 10% primer mes',        'type' => 'discount'],
            ],
            'tone_used'      => $tone,
            'extracted_data' => app(WhatsAppIAService::class)->emptyExtractedData(),
            'fake'           => true,
        ];
    }

    /**
     * GET /whatsapp/api/technicians
     * Lista de usuarios con rol TECNICO para el selector del modal "agendar".
     */
    public function technicians(): JsonResponse
    {
        $techs = User::role('TECNICO')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
        return response()->json($techs);
    }

    /**
     * POST /whatsapp/api/conversations/{id}/schedule-installation
     * Body: { scheduled_at: "2026-05-25 10:00", technician_id: 42|null, notes: "..." }
     * Crea Ticket vinculado al crm_id de la conversación y notifica al cliente.
     */
    public function scheduleInstallation(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'scheduled_at'  => 'required|date|after:now',
            'technician_id' => 'nullable|integer|exists:users,id',
            'notes'         => 'nullable|string|max:500',
        ]);

        $conversation = WhatsAppConversation::with('instance')->findOrFail($id);

        if (!$this->canAccess($conversation)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if (!$conversation->crm_id) {
            return response()->json([
                'error'   => 'Sin CRM vinculado',
                'message' => 'La conversación aún no tiene un prospecto. Pide al cliente sus datos primero para que se cree el lead automáticamente.',
            ], 422);
        }

        $ticket = app(WhatsAppCrmService::class)->scheduleInstallation(
            $conversation,
            $data['scheduled_at'],
            $data['technician_id'] ?? null,
            $data['notes']         ?? null,
        );

        if (!$ticket) {
            return response()->json([
                'error'   => 'No se pudo agendar',
                'message' => 'Revisa logs de WhatsApp CRM para el detalle.',
            ], 500);
        }

        return response()->json([
            'success'   => true,
            'ticket_id' => $ticket->id,
            'crm_id'    => $conversation->crm_id,
        ], 201);
    }

    /**
     * Vendedor solo accede a conversaciones con seller_id == auth user id.
     * Otros roles autorizados acceden a todo.
     */
    private function canAccess(WhatsAppConversation $conversation): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        if ($user->hasRole(ComunConstantsController::SELLER_ROLE)) {
            return $conversation->seller_id === $user->id;
        }
        return true;
    }
}
