<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use App\Modules\Addons\MegaFamilia\Models\ParentalPlan;
use App\Modules\Addons\MegaFamilia\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::notificaciones.index');
    }

    /**
     * Cuenta destinatarios sin enviar. Para el preview del modal antes del confirm.
     */
    public function estimateRecipients(Request $request): JsonResponse
    {
        $data = $request->validate([
            'segment' => 'required|in:all,plan,profile_type,account',
            'target'  => 'nullable|string',
        ]);
        $count = count($this->resolveTokens($data['segment'], $data['target'] ?? null));
        return response()->json(['count' => $count]);
    }

    public function send(Request $request, FcmService $fcm): JsonResponse
    {
        $data = $request->validate([
            'title'   => 'required|string|max:100',
            'message' => 'required|string|max:500',
            'segment' => 'required|in:all,plan,profile_type,account',
            'target'  => 'nullable|string',
            'type'    => 'sometimes|in:informativa,urgente,promocion,mantenimiento',
        ]);
        $data['type'] = $data['type'] ?? 'informativa';

        $tokens = $this->resolveTokens($data['segment'], $data['target'] ?? null);
        $tokenCount = count($tokens);

        if ($tokenCount === 0) {
            $this->logEvent($data, ['error' => 'no_tokens'], 0, $request->ip());
            return response()->json([
                'success'      => false,
                'error'        => 'El segmento no produjo ningún token FCM destino.',
                'segment_size' => 0,
            ], 422);
        }

        $result = $fcm->send($tokens, $data['title'], $data['message'], [
            'segment' => $data['segment'],
            'target'  => (string) ($data['target'] ?? ''),
            'type'    => $data['type'],
        ]);

        $this->logEvent($data, $result, $tokenCount, $request->ip());

        return response()->json(array_merge(['segment_size' => $tokenCount], $result));
    }

    public function history(Request $request): JsonResponse
    {
        $rows = ParentalEvent::query()
            ->where('action', 'broadcast_notification')
            ->when($request->date_from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to,   fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('id')
            ->paginate(25);

        // El filtro por tipo se aplica post-query porque el campo está dentro de detail JSON.
        if ($request->filled('type')) {
            $type = $request->type;
            $rows->setCollection($rows->getCollection()->filter(function ($ev) use ($type) {
                $d = json_decode((string) $ev->detail, true);
                return is_array($d) && ($d['type'] ?? null) === $type;
            })->values());
        }

        return response()->json($rows);
    }

    public function show(int $id): JsonResponse
    {
        $ev = ParentalEvent::findOrFail($id);
        $payload = json_decode((string) $ev->detail, true) ?: [];
        return response()->json([
            'id'         => $ev->id,
            'created_at' => $ev->created_at,
            'ip'         => $ev->ip,
            'data'       => $payload,
        ]);
    }

    public function targets(): JsonResponse
    {
        // Helpers para los selects del segmento
        $plans = ParentalPlan::orderBy('price_monthly')->get(['id', 'name', 'slug']);
        $accounts = ParentalAccount::with('user:id,name,email')
            ->orderByDesc('id')->limit(50)->get(['id', 'user_id']);

        return response()->json([
            'plans'    => $plans,
            'accounts' => $accounts,
            'profile_types' => ['nino', 'preadolescente', 'adolescente'],
        ]);
    }

    /**
     * @return array<string>
     */
    private function resolveTokens(string $segment, ?string $target): array
    {
        $q = ParentalDevice::query()->whereNotNull('fcm_token');

        switch ($segment) {
            case 'plan':
                $q->whereHas('account.plan', fn ($qq) => $qq->where('slug', $target));
                break;
            case 'profile_type':
                $q->whereHas('profile', fn ($qq) => $qq->where('profile_type', $target));
                break;
            case 'account':
                $q->where('account_id', (int) $target);
                break;
            case 'all':
            default:
                break;
        }

        return $q->pluck('fcm_token')->all();
    }

    private function logEvent(array $data, array $result, int $segmentSize, ?string $ip): void
    {
        $accountId = ParentalAccount::query()->value('id');
        if (!$accountId) return; // sin cuentas todavía, FK NOT NULL — saltamos
        ParentalEvent::create([
            'account_id' => $accountId,
            'action'     => 'broadcast_notification',
            'detail'     => json_encode(array_merge($data, [
                'segment_size' => $segmentSize,
                'result'       => $result,
            ])),
            'ip'         => $ip,
            'created_at' => now(),
        ]);
    }
}
