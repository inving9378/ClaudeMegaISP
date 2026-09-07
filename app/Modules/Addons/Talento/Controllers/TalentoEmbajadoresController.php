<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCommission;
use App\Models\Referrals\ReferralReward;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use Illuminate\Support\Facades\DB;

class TalentoEmbajadoresController extends Controller
{
    public function index()
    {
        $this->authorize('talento.embajadores.view');
        return view('addon-talento::talento.embajadores');
    }

    /**
     * Cross-link: collaborator who is also an ambassador (client).
     * Read-only view of their referral data. Does NOT touch Referrals tables.
     */
    public function embajadorData(int $colaboradorId)
    {
        $this->authorize('talento.view');

        $col = TalentoColaborador::with('user')->findOrFail($colaboradorId);

        // Find the client record linked to this user (same email)
        $client = DB::table('clients')
            ->where('email', $col->user?->email)
            ->first(['id', 'name', 'email']);

        if (!$client) {
            return response()->json(['is_ambassador' => false, 'message' => 'No está registrado como cliente/embajador']);
        }

        // Check if this client has referrals as ambassador
        $referralCount = Referral::where('embajador_id', $client->id)->count();

        if ($referralCount === 0) {
            return response()->json([
                'is_ambassador' => false,
                'client_id'     => $client->id,
                'client_name'   => $client->name,
                'message'       => 'Es cliente pero no tiene referidos como embajador',
            ]);
        }

        // Summary: referrals + commissions
        $referrals = Referral::where('embajador_id', $client->id)
            ->with('referredClient:id,name,email')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'referred_client_id', 'status', 'commissions_paid_count', 'commission_window_start']);

        $totalCommissions = ReferralCommission::whereIn(
            'referral_id', Referral::where('embajador_id', $client->id)->pluck('id')
        )->sum('amount');

        $pendingRewards = ReferralReward::where('embajador_id', $client->id)
            ->where('status', 'pending')
            ->sum('amount');

        return response()->json([
            'is_ambassador'      => true,
            'client_id'          => $client->id,
            'client_name'        => $client->name,
            'total_referrals'    => $referralCount,
            'total_commissions'  => round((float)$totalCommissions, 2),
            'pending_rewards'    => round((float)$pendingRewards, 2),
            'recent_referrals'   => $referrals,
        ]);
    }

    /**
     * Cross-link: collaborator who is also a seller.
     * Read-only summary from CommissionRule / PaymentByRule tables.
     */
    public function sellerData(int $colaboradorId)
    {
        $this->authorize('talento.view');

        $col = TalentoColaborador::with('user')->findOrFail($colaboradorId);

        try {
            // sellers no tiene name/commission_percentage propios: el nombre vive en users
            // (join por user_id) y el % de comisión en la regla vigente asignada (pivot
            // commissions_rules_sellers -> commissions_rules.commission_percentage).
            $seller = DB::table('sellers')
                ->join('users', 'sellers.user_id', '=', 'users.id')
                ->where('sellers.user_id', $col->user_id)
                ->first([
                    'sellers.id',
                    DB::raw("CONCAT(users.name, ' ', users.father_last_name, ' ', users.mother_last_name) as name"),
                ]);

            if (!$seller) {
                return response()->json(['is_seller' => false, 'message' => 'No está registrado como vendedor']);
            }

            $commissionPct = DB::table('commissions_rules_sellers')
                ->join('commissions_rules', 'commissions_rules.id', '=', 'commissions_rules_sellers.commission_rule_id')
                ->where('commissions_rules_sellers.seller_id', $seller->id)
                ->value('commissions_rules.commission_percentage');

            // Last 4 weeks commission summary (read-only). transactions_sellers es un ledger
            // de saldo (dormido, sin columna de comisión); el dinero real pagado a vendedores
            // vive en payment_by_rule (seller_id, amount, payment_date).
            $since = now()->subWeeks(4)->toDateString();

            $commissions = DB::table('payment_by_rule')
                ->where('seller_id', $seller->id)
                ->where('payment_date', '>=', $since)
                ->selectRaw('COUNT(*) as total_txns, SUM(amount) as total_commission')
                ->first();

            return response()->json([
                'is_seller'          => true,
                'seller_id'          => $seller->id,
                'seller_name'        => $seller->name,
                'commission_pct'     => $commissionPct,
                'last_4w_txns'       => (int)($commissions->total_txns ?? 0),
                'last_4w_commission' => round((float)($commissions->total_commission ?? 0), 2),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('TalentoEmbajadoresController::sellerData falló', [
                'colaborador_id' => $colaboradorId,
                'error'          => $e->getMessage(),
            ]);
            return response()->json(['is_seller' => false, 'message' => 'No se pudo obtener información de vendedor']);
        }
    }
}
