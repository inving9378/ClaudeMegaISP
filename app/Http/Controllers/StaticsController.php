<?php

namespace App\Http\Controllers;

use App\Models\ClientMainInformation;
use App\Models\CrmLeadInformation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaticsController extends Controller
{
    public function salesAndProspects(Request $request, $id = null)
    {
        $salesByDateRange = ClientMainInformation::select('activation_date as date', DB::raw('count(*) as sales'))
            ->whereNotNull('activation_date');
        $prospectsByDateRange = CrmLeadInformation::select(DB::raw('DATE(last_contacted) as date'), DB::raw('count(*) as prospects'))
            ->whereNotNull(DB::raw('DATE(last_contacted)'));
        if ($id != null) {
            $salesByDateRange = $salesByDateRange->where('seller_id', $id);
            $prospectsByDateRange = $prospectsByDateRange->where('owner_id', $id);
        }
        $range = $request->range;
        if (isset($range)) {
            if (isset($range[0]) && isset($range[1])) {
                $salesByDateRange = $salesByDateRange->whereBetween('activation_date', $range);
                $prospectsByDateRange = $prospectsByDateRange->whereBetween('last_contacted', $range);
            } else {
                $salesByDateRange = $salesByDateRange->where('activation_date', substr($range[0], 0, 10));
                $prospectsByDateRange = $prospectsByDateRange->where(DB::raw("STR_TO_DATE(last_contacted,'%Y-%m-%d')"), '=', substr($range[0], 0, 10));
            }
        } else {
            $salesByDateRange = $salesByDateRange->where('activation_date', '>=', '2024-06-01');
            $prospectsByDateRange = $prospectsByDateRange->whereDate('last_contacted', '>=', '2024-06-01');
        }
        $salesByDateRange = $salesByDateRange->groupBy('activation_date')->orderBy('activation_date')->get();
        $prospectsByDateRange =  $prospectsByDateRange->groupBy(DB::raw('DATE(last_contacted)'))->orderBy('date')->get();
        $salesAndProspectsByDateRange = [
            'sales' => $salesByDateRange,
            'prospects' => $prospectsByDateRange
        ];
        return response()->json($salesAndProspectsByDateRange);
    }

    public function salesByMedium(Request $request, $id = null)
    {
        $salesQuery = ClientMainInformation::query();
        $salesByMedium = ClientMainInformation::join('medium_sales', 'client_main_information.medium_id', '=', 'medium_sales.id')
            ->select('medium_sales.name', DB::raw('count(*) as total'));
        if (isset($id)) {
            $salesQuery->where('seller_id', $id);
            $salesByMedium = $salesByMedium->where('client_main_information.seller_id', $id);
        }
        $range = $request->range;
        if (isset($range)) {
            if (isset($range[0]) && isset($range[1])) {
                $salesQuery->whereBetween('activation_date', $range);
                $salesByMedium = $salesByMedium->whereBetween('client_main_information.activation_date', $range);
            } else {
                $salesQuery->where('activation_date', '=', substr($range[0], 0, 10));
                $salesByMedium = $salesByMedium->where(DB::raw("STR_TO_DATE(client_main_information.activation_date,'%Y-%m-%d')"), '=', substr($range[0], 0, 10));
            }
        } else {
            $salesQuery->where('activation_date', '>=', '2024-06-01');
            $salesByMedium = $salesByMedium->whereDate('client_main_information.activation_date', '>=', '2024-06-01');
        }
        $totalSales = $salesQuery->count();
        $salesByMedium = $salesByMedium->groupBy('medium_sales.name')
            ->get()
            ->map(function ($sale) use ($totalSales) {
                $sale->percentage = ($sale->total / $totalSales) * 100;
                return $sale;
            });
        return response()->json($salesByMedium);
    }

    public function getSalesByMonth($id = null, $month)
    {
        $query = ClientMainInformation::query();
        $query->select(DB::raw('DAY(activation_date) as day'), DB::raw('count(*) as sales'));
        if (isset($id)) {
            $query->where('seller_id', $id);
        }
        return $query->whereMonth('activation_date', $month)
            ->groupBy(DB::raw('DAY(activation_date)'))
            ->orderBy('day')
            ->get();
    }

    public function compareSales($id = null)
    {
        $currentMonthSales = $this->getSalesByMonth($id, Carbon::now()->month);
        $previousMonthSales = $this->getSalesByMonth($id, Carbon::now()->subMonth()->month);
        $salesComparison = [
            'current_month' => $currentMonthSales,
            'previous_month' => $previousMonthSales
        ];
        return response()->json($salesComparison);
    }

    public function prospectsByStatus(Request $request, $id = null)
    {
        $query = CrmLeadInformation::query();
        if (isset($id)) {
            $query->where('owner_id', $id);
        }

        $range = $request->range;
        if (isset($range)) {
            if (isset($range[0]) && isset($range[1])) {
                $query->whereBetween('created_at', $range);
            } else {
                $query->where(DB::raw("STR_TO_DATE(created_at,'%Y-%m-%d')"), '=', substr($range[0], 0, 10));
            }
        } else {
            $query->whereDate('created_at', '>=', '2024-06-01');
        }

        $totalProspects = $query->count();

        $prospectsByStatus = $query->select('crm_status', DB::raw('count(*) as total'))
            ->groupBy('crm_status')
            ->get()
            ->map(function ($prospect) use ($totalProspects) {
                $prospect->percentage = round(($prospect->total / $totalProspects) * 100);
                return $prospect;
            });

        return response()->json($prospectsByStatus);
    }

    public function rankingSales(Request $request)
    {
        $query = ClientMainInformation::query();
        $query->join('users', 'users.id', '=', 'client_main_information.seller_id');
        $range = $request->range;
        if (isset($range)) {
            if (isset($range[0]) && isset($range[1])) {
                $query->whereBetween('client_main_information.activation_date', $range);
            } else {
                $query->where('activation_date', substr($range[0], 0, 10));
            }
        } else {
            $query->whereDate('client_main_information.activation_date', '>=', '2024-06-01');
        }
        $query = $query->select('users.name', DB::raw('count(*) as sales'))->groupBy('users.name')->orderBy('sales', 'desc')->get();
        return response()->json($query);
    }

    public function getTotalProspects()
    {
        $total_prospects = CrmLeadInformation::whereDate('last_contacted', '>=', '2024-06-01')->count();
        return response()->json($total_prospects);
    }

    public function getTotalSales()
    {
        $total_sales = ClientMainInformation::where('activation_date', '>=', '2024-06-01')->count();
        return response()->json($total_sales);
    }

    public function getLostSales()
    {
        $total_lost_sales = ClientMainInformation::where('activation_date', '>=', '2024-06-01')->where('estado', 'Perdido')->count();
        return response()->json($total_lost_sales);
    }
}
