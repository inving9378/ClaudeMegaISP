<?php

namespace App\Modules\Addons\Embajadores\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Modules\Core\Clientes\Models\Client as ClientModel;
use App\Models\ClientMainInformation;
use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCommission;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralSetting;
use App\Models\Referrals\ReferralShareLog;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmbajadorExtApiController extends Controller
{
    // ---- helpers -----------------------------------------------------------

    private function resolveClient(): ?ClientModel
    {
        $client = Client::where('user_id', Auth::id())->first();
        if ($client) return $client;

        $loginUser = optional(Auth::user())->login_user;
        if (! $loginUser) return null;

        $cmi = ClientMainInformation::where('user', $loginUser)->first();
        if (! $cmi) return null;

        return Client::find($cmi->client_id);
    }

    private function noClient(): JsonResponse
    {
        return response()->json(['error' => 'Cliente no encontrado.'], 404);
    }

    private function requireProfile(ClientModel $client): ?ClientReferralProfile
    {
        return ClientReferralProfile::where('client_id', $client->id)->first();
    }

    // ── GET /red ─────────────────────────────────────────────────────────────

    /**
     * Árbol de red multinivel del embajador (hasta 5 niveles).
     * Solo disponible para plan_type = 'multilevel'.
     * Devuelve lista plana de nodos con parent_id para que el cliente construya el árbol.
     */
    public function red(): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        $profile = $this->requireProfile($client);
        if (! $profile) return response()->json(['error' => 'No eres embajador.'], 404);
        if ($profile->plan_type !== 'multilevel') {
            return response()->json(['error' => 'Solo disponible para plan multinivel.'], 422);
        }

        // Todos los descendientes usando closure table (idx_ancestor_depth)
        $rows = DB::table('referral_closures as rc')
            ->join('referrals as r', 'r.referred_client_id', '=', 'rc.descendant_id')
            ->where('rc.ancestor_id', $client->id)
            ->whereBetween('rc.depth', [1, 5])
            ->orderBy('rc.depth')
            ->get(['r.id', 'r.embajador_id', 'r.referred_client_id', 'rc.depth', 'r.status']);

        $clientIds = $rows->pluck('referred_client_id')->unique()->toArray();

        $names = ClientMainInformation::whereIn('client_id', $clientIds)
            ->pluck('name', 'client_id');

        $nodes = $rows->map(fn ($r) => [
            'client_id' => $r->referred_client_id,
            'parent_id' => $r->embajador_id,
            'name'      => $names[$r->referred_client_id] ?? ('Cliente #' . $r->referred_client_id),
            'depth'     => $r->depth,
            'status'    => $r->status,
        ])->values();

        return response()->json([
            'total'     => $nodes->count(),
            'max_depth' => $nodes->max('depth') ?? 0,
            'nodes'     => $nodes,
        ]);
    }

    // ── GET /recompensas ─────────────────────────────────────────────────────

    /**
     * Lista de recompensas (mensualidades) del embajador.
     * Solo disponible para plan_type = 'single_reward'.
     */
    public function recompensas(): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        $profile = $this->requireProfile($client);
        if (! $profile) return response()->json(['error' => 'No eres embajador.'], 404);
        if ($profile->plan_type !== 'single_reward') {
            return response()->json(['error' => 'Solo disponible para plan mensualidad gratis.'], 422);
        }

        $rewards = ReferralReward::where('embajador_id', $client->id)
            ->with(['referral.referredClient.client_main_information'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $transformed = $rewards->getCollection()->map(fn ($r) => [
            'id'             => $r->id,
            'status'         => $r->status,
            'plan_value'     => (float) $r->plan_value_snapshot,
            'available_at'   => $r->available_at?->toDateString(),
            'applied_at'     => $r->applied_at?->toDateString(),
            'expires_at'     => $r->expires_at?->toDateString(),
            'referido_name'  => $r->referral?->referredClient?->client_main_information?->name
                                ?? ('Referido #' . $r->referral?->referred_client_id),
        ]);

        return response()->json([
            'data'         => $transformed,
            'current_page' => $rewards->currentPage(),
            'last_page'    => $rewards->lastPage(),
            'total'        => $rewards->total(),
            'summary'      => [
                'available' => ReferralReward::where('embajador_id', $client->id)->where('status', 'available')->count(),
                'applied'   => ReferralReward::where('embajador_id', $client->id)->where('status', 'applied')->count(),
                'pending'   => ReferralReward::where('embajador_id', $client->id)->where('status', 'pending')->count(),
            ],
        ]);
    }

    // ── POST /recompensas/{id}/aplicar ────────────────────────────────────────

    /**
     * Solicita aplicar una recompensa disponible a la próxima factura.
     * Marca la recompensa como 'applied' con timestamp.
     */
    public function aplicarRecompensa(int $id): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        $reward = ReferralReward::where('id', $id)
            ->where('embajador_id', $client->id)
            ->where('status', 'available')
            ->first();

        if (! $reward) {
            return response()->json(['error' => 'Recompensa no disponible o no encontrada.'], 422);
        }

        $reward->update([
            'status'     => 'applied',
            'applied_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '¡Listo! Tu mensualidad gratis se aplicará en tu próxima factura.',
        ]);
    }

    // ── GET /comisiones ───────────────────────────────────────────────────────

    /**
     * Historial de comisiones mes a mes del embajador.
     * Agrupa por período (year, month) con totales y breakdown por status.
     */
    public function comisiones(): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        $profile = $this->requireProfile($client);
        if (! $profile) return response()->json(['error' => 'No eres embajador.'], 404);

        $rows = ReferralCommission::where('beneficiary_id', $client->id)
            ->selectRaw('period_year, period_month, status, COUNT(*) as count, SUM(commission_amount) as total')
            ->groupBy('period_year', 'period_month', 'status')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->get();

        // Agrupar por año-mes y combinar los distintos statuses
        $byMonth = $rows->groupBy(fn ($r) => "{$r->period_year}-{$r->period_month}");

        $result = $byMonth->map(function ($items, $key) {
            [$year, $month] = explode('-', $key);
            return [
                'period_year'  => (int) $year,
                'period_month' => (int) $month,
                'total'        => (float) $items->sum('total'),
                'count'        => (int) $items->sum('count'),
                'by_status'    => $items->pluck('total', 'status')
                                        ->map(fn ($v) => (float) $v)
                                        ->toArray(),
            ];
        })->values();

        $grandTotal = (float) ReferralCommission::where('beneficiary_id', $client->id)
            ->where('status', 'applied')
            ->sum('commission_amount');

        return response()->json([
            'data'        => $result,
            'grand_total' => $grandTotal,
            'plan_type'   => $profile->plan_type,
        ]);
    }

    // ── POST /share-masivo ────────────────────────────────────────────────────

    /**
     * Envía el link de referido por WhatsApp a múltiples contactos.
     * Body: { contacts: [{name, phone}, ...] }  — máx 50 contactos por llamada.
     */
    public function shareMasivo(Request $request): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        $profile = $this->requireProfile($client);
        if (! $profile) return response()->json(['error' => 'No eres embajador.'], 404);

        $data = $request->validate([
            'contacts'         => 'required|array|min:1|max:50',
            'contacts.*.name'  => 'required|string|max:100',
            'contacts.*.phone' => 'required|string|max:30',
        ]);

        $setting  = ReferralSetting::current();
        $template = $setting->share_template_default
            ?: '¡Hola {{contact_name}}! Te invito a unirte a Meganet con mi código {{code}}. Disfruta de internet de calidad: {{url}}';

        $shareUrl  = url('/registro?ref=' . $profile->referral_code);
        $evolution = app(EvolutionApiService::class);

        $sent   = 0;
        $failed = 0;

        foreach ($data['contacts'] as $contact) {
            $body = str_replace(
                ['{{contact_name}}', '{{name}}', '{{code}}', '{{url}}'],
                [$contact['name'], $contact['name'], $profile->referral_code, $shareUrl],
                $template
            );

            try {
                $jid = EvolutionApiService::phoneToJid($contact['phone']);
                $evolution->sendText($jid, $body);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('EmbajadorExtApiController@shareMasivo: fallo en contacto', [
                    'phone' => $contact['phone'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Registrar el share log
        try {
            ReferralShareLog::create([
                'embajador_id'   => $client->id,
                'channel'        => 'whatsapp_masivo',
                'contacts_count' => $sent,
                'shared_at'      => now(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
        }

        return response()->json([
            'success' => true,
            'sent'    => $sent,
            'failed'  => $failed,
            'total'   => count($data['contacts']),
            'message' => "Enviado a {$sent} contacto(s)" . ($failed > 0 ? ", {$failed} no disponible(s)." : '.'),
        ]);
    }
}
