<?php

namespace App\Http\Controllers\Module\Finance\GeneralAccounting;

use App\Http\Controllers\Controller;
use App\Models\GeneralAccountingCategory;
use App\Models\GeneralAccountingExpense;
use App\Models\GeneralAccountingIncome;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GeneralAccountingController extends Controller
{
    protected $data = array();
    public function __construct()
    {
        $this->data['url'] = 'meganet.module.finance.general_accounting';
        $this->includeLibraryDinamic('GeneralAccounting');
    }

    public function index()
    {
        $this->includeLibraryDinamic('GeneralAccounting');
        return view('meganet.module.finance.general_accounting.index', $this->data);
    }


    public function getBarData(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $ingresosQuery = GeneralAccountingIncome::query();
        $egresosQuery  = GeneralAccountingExpense::query();

        if ($startDate && $endDate) {
            $ingresosQuery->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
            $egresosQuery->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }

        $ingresos = $ingresosQuery
            ->selectRaw('MONTH(created_at) as mes, SUM(amount) as total')
            ->groupBy('mes')->orderBy('mes')->pluck('total', 'mes');

        $egresos = $egresosQuery
            ->selectRaw('MONTH(created_at) as mes, SUM(amount) as total')
            ->groupBy('mes')->orderBy('mes')->pluck('total', 'mes');

        $meses = [
            'Ene',
            'Feb',
            'Mar',
            'Abr',
            'May',
            'Jun',
            'Jul',
            'Ago',
            'Sep',
            'Oct',
            'Nov',
            'Dic'
        ];

        $ingresosPorMes = [];
        $egresosPorMes = [];

        for ($i = 1; $i <= 12; $i++) {
            $ingresosPorMes[] = isset($ingresos[$i]) ? (float) $ingresos[$i] : 0.0;
            $egresosPorMes[]  = isset($egresos[$i]) ? (float) $egresos[$i] : 0.0;
        }

        return response()->json([
            'success'    => true,
            'categories' => $meses,
            'bar'        => [
                'ingresos' => $ingresosPorMes,
                'egresos'  => $egresosPorMes,
            ]
        ]);
    }

    public function getDonutData(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        if (!$startDate || !$endDate) {
            $startDate = now()->startOfMonth();
            $endDate   = now()->endOfMonth();
        } else {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate   = Carbon::parse($endDate)->endOfDay();
        }

        $ingresos = (float) GeneralAccountingIncome::whereBetween('created_at', [$startDate, $endDate])->sum('amount');
        $egresos  = (float) GeneralAccountingExpense::whereBetween('created_at', [$startDate, $endDate])->sum('amount');

        return response()->json([
            'success' => true,
            'donut'   => [
                'ingresos' => $ingresos,
                'egresos'  => $egresos,
            ]
        ]);
    }

    public function getData(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        if (!$startDate || !$endDate) {
            $startDate = now()->startOfMonth();
            $endDate   = now()->endOfMonth();
        } else {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate   = Carbon::parse($endDate)->endOfDay();
        }

        // Traer categorías dinámicamente
        $categoriesTypeIncome  = GeneralAccountingCategory::income()->get();
        $categoriesTypeExpense = GeneralAccountingCategory::expense()->get();

        // ---- Egresos dinámicos ----
        $selectExpense = [];
        foreach ($categoriesTypeExpense as $cat) {
            // genera alias limpio: "Gastos Corrientes" -> "gastos_corrientes"
            $alias = Str::slug($cat->name, '_');
            $selectExpense[] = "SUM(CASE WHEN category = '{$cat->name}' THEN amount ELSE 0 END) as {$alias}";
        }
        $selectExpense[] = "SUM(amount) as total_gastos";

        $egresos = GeneralAccountingExpense::selectRaw(implode(', ', $selectExpense))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->first();

        // ---- Ingresos dinámicos ----
        $selectIncome = [];
        foreach ($categoriesTypeIncome as $cat) {
            $alias = Str::slug($cat->name, '_');
            $selectIncome[] = "SUM(CASE WHEN category = '{$cat->name}' THEN amount ELSE 0 END) as {$alias}";
        }
        $selectIncome[] = "SUM(amount) as total_ingresos";

        $ingresos = GeneralAccountingIncome::selectRaw(implode(', ', $selectIncome))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->first();

        return response()->json([
            'success'  => true,
            'ingresos' => $ingresos,
            'egresos'  => $egresos,
            'balance'  => ($ingresos->total_ingresos ?? 0) - ($egresos->total_gastos ?? 0),
        ]);
    }
}
