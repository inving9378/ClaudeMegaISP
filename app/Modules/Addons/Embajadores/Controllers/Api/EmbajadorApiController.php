<?php

namespace App\Modules\Addons\Embajadores\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientMainInformation;
use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\ReferralCommission;
use App\Models\Referrals\ReferralSetting;
use App\Models\Referrals\ReferralShareLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmbajadorApiController extends Controller
{
    // ---- helpers -----------------------------------------------------------

    private function resolveClient(): ?Client
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

    // ---- public (no auth required) -----------------------------------------

    /**
     * GET /api/megafamilia/embajadores/terms
     * Devuelve el texto de términos y la configuración pública del programa.
     */
    public function terms(): JsonResponse
    {
        $s = ReferralSetting::current();

        return response()->json([
            'program_name'      => $s->program_name,
            'program_active'    => $s->program_active,
            'threshold_amount'  => (float) $s->threshold_amount,
            'duration_months'   => $s->duration_months,
            'max_levels'        => $s->max_levels,
            'terms_text'        => $s->terms_conditions,
            'welcome_message'   => $s->welcome_message,
        ]);
    }

    // ---- authenticated -----------------------------------------------------

    /**
     * GET /api/megafamilia/embajadores/status
     * Estado del cliente en el programa (activo/inactivo, plan, elegibilidad).
     */
    public function status(): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        $profile = ClientReferralProfile::where('client_id', $client->id)->first();
        $setting = ReferralSetting::current();

        if (! $profile) {
            return response()->json([
                'active'           => false,
                'program_name'     => $setting->program_name,
                'program_active'   => $setting->program_active,
                'threshold_amount' => (float) $setting->threshold_amount,
                'can_join'         => (bool) $setting->program_active,
            ]);
        }

        return response()->json([
            'active'            => true,
            'plan_type'         => $profile->plan_type,
            'plan_label'        => $this->planLabel($profile->plan_type),
            'is_eligible'       => $profile->is_eligible,
            'referral_code'     => $profile->referral_code,
            'threshold_paid'    => (float) $profile->threshold_amount_paid,
            'threshold_total'   => (float) $setting->threshold_amount,
            'total_referrals'   => $profile->total_referrals,
            'activated_at'      => optional($profile->activated_at)->toDateString(),
        ]);
    }

    /**
     * POST /api/megafamilia/embajadores/activate
     * Body: { plan_type: "single_reward" | "multilevel" }
     * Activa al cliente como embajador. Idempotente: 422 si ya está activo.
     */
    public function activate(Request $request): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        if (ClientReferralProfile::where('client_id', $client->id)->exists()) {
            return response()->json(['error' => 'Ya eres embajador Meganet.'], 422);
        }

        $setting = ReferralSetting::current();
        if (! $setting->program_active) {
            return response()->json(['error' => 'El programa de embajadores no está activo actualmente.'], 422);
        }

        $data = $request->validate([
            'plan_type' => 'required|in:single_reward,multilevel',
        ]);

        $code = ClientReferralProfile::generateUniqueCode($client);

        $profile = ClientReferralProfile::create([
            'client_id'                => $client->id,
            'plan_type'                => $data['plan_type'],
            'referral_code'            => $code,
            'referral_link'            => url('/registro?ref=' . $code),
            'is_eligible'              => false,
            'threshold_amount_paid'    => 0,
            'total_referrals'          => 0,
            'total_commissions_earned' => 0,
            'total_rewards_earned'     => 0,
            'activated_at'             => now(),
        ]);

        return response()->json([
            'success'      => true,
            'message'      => '¡Bienvenido al programa Embajadores Meganet!',
            'referral_code'=> $profile->referral_code,
            'plan_type'    => $profile->plan_type,
            'plan_label'   => $this->planLabel($profile->plan_type),
        ], 201);
    }

    /**
     * GET /api/megafamilia/embajadores/link
     * Retorna el código de referido y texto de compartir listo para usar.
     */
    public function link(): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        $profile = ClientReferralProfile::where('client_id', $client->id)->first();
        if (! $profile) {
            return response()->json(['error' => 'Aún no eres embajador.'], 404);
        }

        $setting  = ReferralSetting::current();
        $template = $setting->share_template_default
            ?: '¡Únete a Meganet con mi código {code} y disfruta de internet de calidad!';

        return response()->json([
            'referral_code' => $profile->referral_code,
            'share_url'     => url('/registro?ref=' . $profile->referral_code),
            'share_text'    => str_replace('{code}', $profile->referral_code, $template),
        ]);
    }

    /**
     * POST /api/megafamilia/embajadores/share-log
     * Body: { channel: "whatsapp"|"copy"|"instagram"|... }
     * Registra que el embajador compartió su enlace por un canal dado.
     */
    public function shareLog(Request $request): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        $profile = ClientReferralProfile::where('client_id', $client->id)->first();
        if (! $profile) {
            return response()->json(['error' => 'Aún no eres embajador.'], 404);
        }

        $data = $request->validate([
            'channel'        => 'required|string|max:50',
            'contacts_count' => 'sometimes|integer|min:1|max:500',
        ]);

        ReferralShareLog::create([
            'embajador_id'   => $client->id,
            'channel'        => $data['channel'],
            'contacts_count' => $data['contacts_count'] ?? 1,
            'shared_at'      => now(),
            'created_at'     => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/megafamilia/embajadores/dashboard
     * Resumen completo: referidos, comisiones por estado, recompensas, últimas 5 comisiones.
     */
    public function dashboard(): JsonResponse
    {
        $client = $this->resolveClient();
        if (! $client) return $this->noClient();

        $profile = ClientReferralProfile::where('client_id', $client->id)->first();
        if (! $profile) {
            return response()->json(['active' => false]);
        }

        $commByStatus = ReferralCommission::where('beneficiary_id', $client->id)
            ->selectRaw('status, SUM(commission_amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $recent = ReferralCommission::where('beneficiary_id', $client->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'commission_amount', 'status', 'period_month', 'period_year', 'level'])
            ->map(fn($c) => [
                'id'                => $c->id,
                'amount'            => (float) $c->commission_amount,
                'status'            => $c->status,
                'period'            => "{$c->period_month}/{$c->period_year}",
                'level'             => $c->level,
            ]);

        $setting = ReferralSetting::current();

        return response()->json([
            'active'            => true,
            'plan_type'         => $profile->plan_type,
            'plan_label'        => $this->planLabel($profile->plan_type),
            'is_eligible'       => $profile->is_eligible,
            'referral_code'     => $profile->referral_code,
            'total_referrals'   => $profile->total_referrals,
            'total_commissions' => (float) $profile->total_commissions_earned,
            'total_rewards'     => $profile->total_rewards_earned,
            'threshold_paid'    => (float) $profile->threshold_amount_paid,
            'threshold_total'   => (float) $setting->threshold_amount,
            'commissions_by_status' => [
                'pending'   => (float) ($commByStatus['pending']   ?? 0),
                'approved'  => (float) ($commByStatus['approved']  ?? 0),
                'applied'   => (float) ($commByStatus['applied']   ?? 0),
                'cancelled' => (float) ($commByStatus['cancelled'] ?? 0),
            ],
            'recent_commissions' => $recent,
        ]);
    }

    // ---- private -----------------------------------------------------------

    private function planLabel(string $plan): string
    {
        return $plan === 'multilevel' ? 'Multinivel' : 'Mensualidad gratis';
    }
}
