<?php

namespace App\Modules\Addons\Embajadores\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientesController extends Controller
{
    public function index()
    {
        return view('addon-embajadores::clientes.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = ClientReferralProfile::with([
                'client:id',
                'client.client_main_information:client_id,name,father_last_name,phone,email,estado',
            ])
            ->whereNotNull('plan_type');

        if ($search = $request->input('search')) {
            $query->whereHas('client.client_main_information', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('father_last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($plan = $request->input('plan_type')) {
            $query->where('plan_type', $plan);
        }

        if ($eligible = $request->input('eligible')) {
            $query->where('is_eligible', (bool) $eligible);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $embajadores = $query->orderByDesc('total_commissions_earned')->paginate($perPage);

        return response()->json($embajadores);
    }

    public function tree(int $id): JsonResponse
    {
        $profile = ClientReferralProfile::where('client_id', $id)->first();
        if (! $profile) {
            return response()->json(['error' => 'Embajador no encontrado'], 404);
        }

        // Todos los referrals del subárbol, máximo 5 niveles
        $referrals = Referral::where(function ($q) use ($id) {
                $q->where('embajador_id', $id)
                  ->orWhere('chain_path', 'like', "%/{$id}/%");
            })
            ->where('chain_depth', '<=', 5)
            ->select('embajador_id', 'referred_client_id', 'chain_depth')
            ->get();

        // IDs únicos del árbol
        $clientIds = collect([$id])
            ->merge($referrals->pluck('embajador_id'))
            ->merge($referrals->pluck('referred_client_id'))
            ->unique()->values();

        // Nombres
        $namesData = DB::table('client_main_information')
            ->whereIn('client_id', $clientIds)
            ->select('client_id', 'name', 'father_last_name')
            ->get()->keyBy('client_id');

        // Perfiles (código + plan)
        $profiles = ClientReferralProfile::whereIn('client_id', $clientIds)
            ->select('client_id', 'referral_code', 'plan_type')
            ->get()->keyBy('client_id');

        // Totales de comisiones aprobadas/aplicadas por beneficiario
        $commissions = DB::table('referral_commissions')
            ->whereIn('beneficiary_id', $clientIds)
            ->whereIn('status', ['approved', 'applied'])
            ->groupBy('beneficiary_id')
            ->selectRaw('beneficiary_id, SUM(commission_amount) as total')
            ->pluck('total', 'beneficiary_id');

        // Mapa de hijos: embajador_id => [referred_client_id, ...]
        $childMap = [];
        foreach ($referrals as $r) {
            $childMap[$r->embajador_id][] = $r->referred_client_id;
        }

        $buildNode = function (int $clientId, int $depth) use (&$buildNode, $namesData, $profiles, $commissions, $childMap) {
            $info    = $namesData->get($clientId);
            $profile = $profiles->get($clientId);
            $name    = $info ? trim(($info->name ?? '') . ' ' . ($info->father_last_name ?? '')) : "#$clientId";

            $node = [
                'id'               => $clientId,
                'name'             => $name,
                'referral_code'    => $profile?->referral_code,
                'plan_type'        => $profile?->plan_type,
                'depth'            => $depth,
                'commission_total' => (float) ($commissions[$clientId] ?? 0),
                'children'         => [],
            ];

            if (isset($childMap[$clientId]) && $depth < 5) {
                foreach ($childMap[$clientId] as $childId) {
                    $node['children'][] = $buildNode($childId, $depth + 1);
                }
            }

            return $node;
        };

        return response()->json($buildNode($id, 0));
    }

    public function show(int $id): JsonResponse
    {
        $profile = ClientReferralProfile::with([
                'client:id',
                'client.client_main_information:client_id,name,father_last_name,phone,email,estado',
                'referredClients' => fn($q) => $q->with('referredClient:id')->limit(20),
                'commissions' => fn($q) => $q->orderByDesc('id')->limit(12),
                'rewards' => fn($q) => $q->orderByDesc('id')->limit(12),
            ])
            ->findOrFail($id);

        $redSize = Referral::where('chain_path', 'like', "%/{$profile->client_id}/%")->count();

        return response()->json([
            'profile'  => $profile,
            'red_size' => $redSize,
        ]);
    }
}
