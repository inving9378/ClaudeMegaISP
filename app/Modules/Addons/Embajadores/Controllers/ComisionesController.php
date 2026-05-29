<?php

namespace App\Modules\Addons\Embajadores\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Referrals\ReferralCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComisionesController extends Controller
{
    public function index()
    {
        return view('addon-embajadores::comisiones.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = ReferralCommission::with([
                'beneficiary:id',
                'beneficiary.client_main_information:client_id,name,father_last_name',
                'referral:id,referred_client_id',
            ]);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($year = $request->input('year')) {
            $query->where('period_year', $year);
        }

        if ($month = $request->input('month')) {
            $query->where('period_month', $month);
        }

        if ($level = $request->input('level')) {
            $query->where('level', $level);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $comisiones = $query->orderByDesc('id')->paginate($perPage);

        $totals = ReferralCommission::selectRaw('status, sum(commission_amount) as total, count(*) as count')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'data'   => $comisiones,
            'totals' => $totals,
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $commission = ReferralCommission::findOrFail($id);

        if ($commission->status !== 'pending') {
            return response()->json(['message' => 'Solo se pueden aprobar comisiones en estado pendiente.'], 422);
        }

        $commission->update(['status' => 'approved']);

        return response()->json(['message' => 'Comisión aprobada.', 'commission' => $commission]);
    }

    public function cancel(int $id): JsonResponse
    {
        $commission = ReferralCommission::findOrFail($id);

        if ($commission->status === 'applied') {
            return response()->json(['message' => 'No se puede cancelar una comisión ya aplicada.'], 422);
        }

        $commission->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Comisión cancelada.', 'commission' => $commission]);
    }
}
