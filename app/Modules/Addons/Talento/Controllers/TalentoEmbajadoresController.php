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
     * Read-only summary from CommissionRule / TransactionSeller tables.
     */
    public function sellerData(int $colaboradorId)
    {
        $this->authorize('talento.view');

        $col = TalentoColaborador::with('user')->findOrFail($colaboradorId);

        // Find seller record by user_id (assumes sellers table has user_id FK)
        $seller = DB::table('sellers')
            ->where('user_id', $col->user_id)
            ->first(['id', 'name', 'commission_percentage']);

        if (!$seller) {
            return response()->json(['is_seller' => false, 'message' => 'No está registrado como vendedor']);
        }

        // Last 4 weeks commission summary (read-only)
        $since = now()->subWeeks(4)->toDateString();

        $commissions = DB::table('transaction_sellers')
            ->where('seller_id', $seller->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) as total_txns, SUM(commission_amount) as total_commission')
            ->first();

        return response()->json([
            'is_seller'          => true,
            'seller_id'          => $seller->id,
            'seller_name'        => $seller->name,
            'commission_pct'     => $seller->commission_percentage,
            'last_4w_txns'       => (int)($commissions->total_txns ?? 0),
            'last_4w_commission' => round((float)($commissions->total_commission ?? 0), 2),
        ]);
    }
}
