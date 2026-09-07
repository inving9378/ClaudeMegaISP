<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Support\Actor;
use App\Modules\Core\Dashboard\Controllers\StaticsController;
use Illuminate\Http\Request;

/**
 * Estadísticas de ventas del colaborador vendedor, SOLO LECTURA y self-scoped.
 *
 * No recalcula nada: reusa StaticsController (motor real detrás del dashboard de
 * Vendedores, /vendedores/dashboard) pasándole el user_id del colaborador
 * autenticado. client_main_information.seller_id y crm_lead_information.owner_id
 * guardan el user_id del vendedor (no sellers.id) — mismo criterio que
 * Seller::getSales() y SaleController::rankingSales.
 */
class TalentoVentasController extends Controller
{
    public function index()
    {
        $this->authorize('talento.ventas.view');

        return view('addon-talento::talento.ventas');
    }

    public function misVentas(Request $request, StaticsController $statics)
    {
        $this->authorize('talento.ventas.view');

        $seller = Actor::for(auth()->user())->seller();
        if (! $seller) {
            return response()->json([
                'es_vendedor' => false,
                'message'     => 'Este colaborador no tiene una cuenta de vendedor asociada.',
            ]);
        }

        $id = $seller->user_id;

        return response()->json([
            'es_vendedor'         => true,
            'seller_id'           => $seller->id,
            'sales_and_prospects' => $statics->salesAndProspects($request, $id)->getData(true),
            'sales_by_medium'     => $statics->salesByMedium($request, $id)->getData(true),
            'compare_sales'       => $statics->compareSales($id)->getData(true),
            'prospects_by_status' => $statics->prospectsByStatus($request, $id)->getData(true),
            'lost_sales'          => $statics->getLostSales($id)->getData(true),
        ]);
    }
}
