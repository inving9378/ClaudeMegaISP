<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Support\Actor;
use App\Modules\Core\Dashboard\Controllers\StaticsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function indexRanking()
    {
        $this->authorize('talento.ventas.view');

        return view('addon-talento::talento.ventas_ranking');
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

    /**
     * Ranking de ventas de TODOS los colaboradores-vendedor (admin-wide). Mismo
     * criterio que SaleController::rankingSales() de Vendedores (join por
     * client_main_information.seller_id = users.id, sin filtro de vendedor) — solo
     * se agrega users.id al select para que el frontend pueda enlazar a la ficha del
     * colaborador correspondiente.
     */
    public function rankingAdmin(Request $request)
    {
        $this->authorize('talento.ventas.view');

        $range = $request->range;
        // DB::table() (no el modelo Eloquent) a propósito: ClientMainInformation
        // agrega varios accessors pesados vía $appends (client_name_with_fathers_names,
        // partner_name, contract_months, etc.) que se recalculan por fila y no aplican
        // a un agregado — el mismo patrón que ya se usó en prospectosAdmin() arriba.
        $query = DB::table('client_main_information')
            ->join('users', 'users.id', '=', 'client_main_information.seller_id')
            ->select('users.id as user_id', 'users.name', DB::raw('count(*) as sales'))
            ->whereNotNull('client_main_information.activation_date');

        if (is_array($range) && isset($range[0], $range[1])) {
            $query->whereBetween('client_main_information.activation_date', $range);
        }

        $ranking = $query->groupBy('users.id', 'users.name')
            ->orderByDesc('sales')
            ->get();

        return response()->json(['ranking' => $ranking]);
    }

    /**
     * Prospectos CRM de TODOS los colaboradores-vendedor (admin-wide), PAGINADO
     * (2200+ filas reales en dev — mandarlas todas de un golpe al navegador no es
     * viable). Misma query que PortalTecnicoController::prospectos() (self-scoped),
     * sin el filtro por owner_id — con filtro OPCIONAL por vendedor puntual
     * (?user_id=, el mismo id que ya trae rankingAdmin() para el selector).
     */
    public function prospectosAdmin(Request $request)
    {
        $this->authorize('talento.ventas.view');

        $query = DB::table('crm_main_information')
            ->join('crm_lead_information', 'crm_main_information.crm_id', '=', 'crm_lead_information.crm_id')
            ->join('users', 'users.id', '=', 'crm_lead_information.owner_id')
            ->select(
                'crm_main_information.crm_id',
                'crm_main_information.name',
                'crm_main_information.father_last_name',
                'crm_main_information.mother_last_name',
                'crm_main_information.email',
                'crm_main_information.phone',
                'crm_main_information.phone2',
                'crm_main_information.address',
                'crm_lead_information.crm_status',
                'crm_lead_information.score',
                'crm_lead_information.source',
                'crm_lead_information.last_contacted',
                'crm_lead_information.instalation_date',
                'crm_lead_information.created_at',
                'crm_lead_information.owner_id as user_id',
                'users.name as owner_name'
            );

        if ($request->filled('user_id')) {
            $query->where('crm_lead_information.owner_id', $request->integer('user_id'));
        }

        $prospectos = $query->orderByDesc('crm_lead_information.created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($prospectos);
    }
}
