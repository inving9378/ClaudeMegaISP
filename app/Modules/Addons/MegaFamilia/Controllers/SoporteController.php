<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketThread;
use App\Models\User;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use App\Modules\Addons\MegaFamilia\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Reusa la tabla `tickets` de MegaISP. Los tickets MegaFamilia se identifican
 * porque tienen el marcador "[MegaFamilia]" en el topic.
 */
class SoporteController extends Controller
{
    /** Mapeo a estados internos para la UI */
    private const STATUS_OPEN     = 'Nuevo';
    private const STATUS_PROGRESS = 'Trabajo en curso';
    private const STATUS_RESOLVED = 'Resuelto';
    private const STATUS_CLOSED   = 'Cerrado';

    private const MEGAFAM_TAG = '[MegaFamilia]';

    public function __construct(private FcmService $fcm)
    {
    }

    public function index()
    {
        return view('addon-megafamilia::soporte.index');
    }

    public function data(Request $request): JsonResponse
    {
        $base = $this->megafamiliaQuery();

        $now    = Carbon::now();
        $todayS = $now->copy()->startOfDay();

        $kpis = [
            'open'          => (clone $base)->where('estado', self::STATUS_OPEN)->count(),
            'in_progress'   => (clone $base)->where('estado', self::STATUS_PROGRESS)->count(),
            'resolved_today'=> (clone $base)->where('estado', self::STATUS_RESOLVED)
                                ->where('updated_at', '>=', $todayS)->count(),
            'avg_resolution_hours' => (float) (clone $base)
                ->whereIn('estado', [self::STATUS_RESOLVED, self::STATUS_CLOSED])
                ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as h'))
                ->value('h'),
        ];

        $q = (clone $base)
            ->when($request->estado,    fn ($qq, $v) => $qq->where('estado', $v))
            ->when($request->priority,  fn ($qq, $v) => $qq->where('priority', $v))
            ->when($request->assigned_to, fn ($qq, $v) => $qq->where('assigned_to', $v))
            ->when($request->date_from, fn ($qq, $v) => $qq->whereDate('created_at', '>=', $v))
            ->when($request->date_to,   fn ($qq, $v) => $qq->whereDate('created_at', '<=', $v))
            ->when($request->search, function ($qq, $v) {
                $qq->where(function ($q2) use ($v) {
                    $q2->where('topic', 'like', "%{$v}%")
                       ->orWhere('reporter', 'like', "%{$v}%");
                });
            })
            ->orderByDesc('id');

        $tickets = $q->paginate(25);

        return response()->json(['kpis' => $kpis, 'tickets' => $tickets]);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = Ticket::with('ticket_thread')->findOrFail($id);

        // Intentar enriquecer con info de la cuenta parental si el reporter coincide.
        $account = null;
        if ($ticket->reporter_id) {
            $account = ParentalAccount::with(['user:id,name,email', 'plan:id,name,slug', 'license'])
                ->where('user_id', $ticket->reporter_id)
                ->first();
        }

        $assigned = $ticket->assigned_to ? User::find($ticket->assigned_to, ['id', 'name', 'email']) : null;

        return response()->json([
            'ticket'    => $ticket,
            'account'   => $account,
            'assigned'  => $assigned,
        ]);
    }

    public function respond(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string',
        ]);

        $ticket = Ticket::findOrFail($id);

        $thread = TicketThread::create([
            'ticket_id' => $ticket->id,
            'edited_by' => Auth::id(),
            'message'   => $data['message'],
            'hidden'    => 0,
        ]);

        $ticket->touch();

        // Push FCM al cliente reporter, si tiene cuenta MegaFamilia
        if ($ticket->reporter_id) {
            $tokens = ParentalDevice::query()
                ->whereHas('account', fn ($q) => $q->where('user_id', $ticket->reporter_id))
                ->whereNotNull('fcm_token')
                ->pluck('fcm_token')->all();

            if (!empty($tokens)) {
                $this->fcm->send(
                    $tokens,
                    'Respuesta de soporte',
                    mb_substr($data['message'], 0, 120),
                    ['type' => 'support_reply', 'ticket_id' => (string) $ticket->id],
                );
            }
        }

        return response()->json(['success' => true, 'thread_id' => $thread->id]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', [
                self::STATUS_OPEN, self::STATUS_PROGRESS, self::STATUS_RESOLVED, self::STATUS_CLOSED,
                'Esperando al cliente', 'Esperando al agente',
            ]),
        ]);

        $ticket = Ticket::findOrFail($id);
        $previous = $ticket->estado;
        $ticket->update(['estado' => $data['status']]);

        $account = ParentalAccount::query()->where('user_id', $ticket->reporter_id)->first();
        ParentalEvent::create([
            'account_id' => $account?->id ?? ParentalAccount::value('id'),
            'action'     => 'support.status_change',
            'detail'     => json_encode([
                'ticket_id' => $ticket->id,
                'from'      => $previous,
                'to'        => $data['status'],
                'by'        => Auth::id(),
            ]),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'assigned_to' => 'nullable|integer|exists:users,id',
            'priority'    => 'nullable|integer|min:1|max:4',
        ]);

        $ticket = Ticket::findOrFail($id);
        $ticket->update(array_filter([
            'assigned_to' => $data['assigned_to'] ?? null,
            'priority'    => $data['priority'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json(['success' => true]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'topic'        => 'required|string|max:255',
            'reporter_id'  => 'nullable|integer|exists:users,id',
            'priority'     => 'nullable|integer|min:1|max:4',
            'message'      => 'sometimes|nullable|string',
        ]);

        // Garantizar el tag MegaFamilia en el topic
        if (!str_contains($data['topic'], self::MEGAFAM_TAG)) {
            $data['topic'] = self::MEGAFAM_TAG . ' ' . $data['topic'];
        }

        $reporterName = null;
        if (!empty($data['reporter_id'])) {
            $reporterName = User::where('id', $data['reporter_id'])->value('name');
        }

        $ticket = Ticket::create([
            'topic'         => $data['topic'],
            'estado'        => self::STATUS_OPEN,
            'priority'      => $data['priority'] ?? 2,
            'reporter'      => $reporterName,
            'reporter_id'   => $data['reporter_id'] ?? null,
            'reporter_type' => $data['reporter_id'] ? User::class : null,
            'edited_by'     => Auth::id(),
            'date_time'     => now()->toDateTimeString(),
        ]);

        if (!empty($data['message'])) {
            TicketThread::create([
                'ticket_id' => $ticket->id,
                'edited_by' => Auth::id(),
                'message'   => $data['message'],
                'hidden'    => 0,
            ]);
        }

        return response()->json(['success' => true, 'ticket' => $ticket->fresh()]);
    }

    public function technicians(): JsonResponse
    {
        $techs = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['TECNICO', 'tecnico', 'Tecnico']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        return response()->json(['technicians' => $techs]);
    }

    private function megafamiliaQuery()
    {
        return Ticket::query()->where('topic', 'like', '%' . self::MEGAFAM_TAG . '%');
    }
}
