<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MegaFamilia\Models\ParentalAccount;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use App\Modules\Addons\MegaFamilia\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionesController extends Controller
{
    public function index()
    {
        return view('addon-megafamilia::notificaciones.index');
    }

    public function history(): JsonResponse
    {
        $rows = ParentalEvent::where('action', 'broadcast_notification')
            ->orderByDesc('id')
            ->paginate(20);
        return response()->json($rows);
    }

    /**
     * Envío real de push a un segmento de dispositivos.
     *
     * Segmentos:
     *   - all           → todos los devices con fcm_token
     *   - plan          → target = slug de plan (basico/plus/premium)
     *   - profile_type  → target = nino/preadolescente/adolescente
     *   - account       → target = id de parental_accounts
     *
     * Persiste un ParentalEvent con el resultado FCM (sent/failed) para
     * la pestaña Historial.
     */
    public function send(Request $request, FcmService $fcm): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string',
            'message' => 'required|string',
            'segment' => 'required|in:all,plan,profile_type,account',
            'target' => 'nullable|string',
        ]);

        $tokens = $this->resolveTokens($data['segment'], $data['target'] ?? null);
        $tokenCount = count($tokens);

        if ($tokenCount === 0) {
            $this->logEvent($data, ['error' => 'no_tokens'], 0, $request->ip());
            return response()->json([
                'success' => false,
                'error' => 'El segmento no produjo ningún token FCM destino.',
                'segment_size' => 0,
            ], 422);
        }

        $result = $fcm->send($tokens, $data['title'], $data['message'], [
            'segment' => $data['segment'],
            'target' => (string) ($data['target'] ?? ''),
        ]);

        $this->logEvent($data, $result, $tokenCount, $request->ip());

        return response()->json(array_merge(['segment_size' => $tokenCount], $result));
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
                // sin filtro adicional
                break;
        }

        return $q->pluck('fcm_token')->all();
    }

    private function logEvent(array $data, array $result, int $segmentSize, ?string $ip): void
    {
        $accountId = ParentalAccount::query()->value('id');
        if (! $accountId) {
            // No hay cuentas todavía — saltamos el log (FK NOT NULL).
            return;
        }
        ParentalEvent::create([
            'account_id' => $accountId,
            'action' => 'broadcast_notification',
            'detail' => json_encode(array_merge($data, [
                'segment_size' => $segmentSize,
                'result' => $result,
            ])),
            'ip' => $ip,
            'created_at' => now(),
        ]);
    }
}
