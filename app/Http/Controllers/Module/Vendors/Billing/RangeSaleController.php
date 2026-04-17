<?php

namespace App\Http\Controllers\Module\Vendors\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\RangeSale;

class RangeSaleController extends Controller
{
    public function index()
    {
        return view('meganet.module.setting.range-sales.index');
    }

    public function getListRangesSales()
    {
        $ranges_sales = DB::table('ranges_of_sales_sectors')
            ->get();
        return response()->json($ranges_sales);
    }

    public function getSectorOne()
    {
        $sector_one = DB::table('ranges_of_sales_sectors')
            ->where('sector', 'A')
            ->get();
        return response()->json($sector_one);
    }

    public function getSectorTwo()
    {
        $sector_two = DB::table('ranges_of_sales_sectors')
            ->where('sector', 'B')
            ->get();
        return response()->json($sector_two);
    }

    public function getSectorThree()
    {
        $sector_three = DB::table('ranges_of_sales_sectors')
            ->where('sector', 'C')
            ->get();
        return response()->json($sector_three);
    }

    public function edit($id)
    {
        $range_sale = DB::table('ranges_of_sales_sectors')
            ->where('id', $id)
            ->first();
        return response()->json($range_sale);
    }

    public function update(Request $request, $id)
    {
        $range_sale = RangeSale::find($id);
        $range_sale->sector = $request->sector;
        $range_sale->range = $request->range;
        $range_sale->number_of_prospects = $request->number_of_prospects;
        $range_sale->number_of_sales = $request->number_of_sales;
        $range_sale->save();
        
        return response()->json(['status' => 200, 'message' => 'Registo actualizado correctamente']);
    }
}
