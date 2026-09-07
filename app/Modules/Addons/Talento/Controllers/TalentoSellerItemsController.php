<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de artículos de vendedor (solo lectura, catálogo global).
 * Reusa inventory_item_stocks/inventory_items (mismos datos que el catálogo
 * legacy "selleritems" de Vendedores) sin tabla ni backend propio. Item #9990450.
 */
class TalentoSellerItemsController extends Controller
{
    public function index()
    {
        $this->authorize('talento.selleritems.view');
        return view('addon-talento::talento.articulos-vendedor');
    }

    public function data(Request $request)
    {
        $this->authorize('talento.selleritems.view');

        $q = DB::table('inventory_item_stocks as s')
            ->join('inventory_items as i', 'i.id', '=', 's.inventory_item_id')
            ->leftJoin('inventory_item_types as t', 't.id', '=', 'i.inventory_item_type_id')
            ->join('sellers as sel', 'sel.user_id', '=', 's.modelable_id')
            ->join('users as u', 'u.id', '=', 'sel.user_id')
            ->where('s.modelable_type', 'App\\Models\\User')
            ->whereNull('s.deleted_at')
            ->where('s.current_stock', '>', 0)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($w) use ($search) {
                    $w->where('i.name', 'like', "%{$search}%")
                        ->orWhere('u.name', 'like', "%{$search}%");
                });
            })
            ->select(
                's.id as stock_id',
                'i.id as item_id',
                'i.name as item_name',
                't.type as tipo',
                't.categoria as categoria',
                's.current_stock',
                's.condition',
                'sel.id as seller_id',
                DB::raw("TRIM(CONCAT_WS(' ', u.name, u.father_last_name, u.mother_last_name)) as seller_name"),
                's.created_at as assigned_at'
            )
            ->orderBy('seller_name')
            ->orderBy('i.name')
            ->paginate($request->per_page ?? 50);

        return response()->json($q);
    }
}
