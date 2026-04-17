<?php

namespace App\Http\Controllers\Module\Setting\Commission;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SellerType;
use App\Models\Seller;
use App\Models\Commission;

class ComissionController extends Controller
{
    public function getTypeSeller()
    {
        $types = SellerType::all();
        return response()->json($types);
    }

    public function getSellerByType($id)
    {
        $sellers = Seller::where('type_id', $id)
            ->join('users', 'sellers.user_id', '=', 'users.id')
            ->get([
                'sellers.*',
                DB::raw('CONCAT(users.name, " ", users.father_last_name, " ", users.mother_last_name) as full_name')
            ]);
        return response()->json($sellers);
    }

    // Obtener el numero de comisiones
    public function countPendingCommissions($seller_id)
    {
        $count = Commission::where('status', 'Por pagar')
            ->where('seller_id', $seller_id)
            ->count();

        return response()->json(['commissions' => $count]);
    }

    // Obtener las comisiones pendientes por pagar al vendedor
    public function getCommissionsNotPaidToTheSeller($id)
    {
        $commissions = DB::table('commissions')
            ->where('commissions.seller_id', $id)
            ->whereIn('commissions.status', ['Por pagar', 'Pendiente'])
            ->join('commissions_details', 'commissions.id', '=', 'commissions_details.commission_id')
            ->leftJoin('client_main_information', 'commissions_details.client_id', '=', 'client_main_information.id')
            ->leftJoin('crm_main_information', 'commissions_details.prospect_id', '=', 'crm_main_information.crm_id')
            ->select(
                'commissions.*',
                'client_main_information.name as client_name',
                'client_main_information.father_last_name as client_father_last_name',
                'client_main_information.mother_last_name as client_mother_last_name',
                'crm_main_information.name as prospect_name',
                'crm_main_information.father_last_name as prospect_father_last_name',
                'crm_main_information.mother_last_name as prospect_mother_last_name'
            )
            ->distinct()
            ->get();

        $commissions->transform(function ($commission) {
            $commission->name = $commission->client_name ?? $commission->prospect_name;
            $commission->father_last_name = $commission->client_father_last_name ?? $commission->prospect_father_last_name;
            $commission->mother_last_name = $commission->client_mother_last_name ?? $commission->prospect_mother_last_name;

            unset($commission->client_name);
            unset($commission->client_father_last_name);
            unset($commission->client_mother_last_name);
            unset($commission->prospect_name);
            unset($commission->prospect_father_last_name);
            unset($commission->prospect_mother_last_name);

            return $commission;
        });

        return response()->json($commissions);
    }



    // Obtener lista de comisiones pendientes por vendedor
    public function getListOfCommissionsToBePaid($seller_id)
    {

        $batchSize = 500; // Tamaño del lote
        $commissionDetails = collect();

        // Obtener las comisiones
        $commissions = DB::table('commissions')
            ->where('commissions.seller_id', $seller_id)
            ->whereIn('commissions.status', ['Por pagar', 'Pendiente'])
            ->get();

        // Obtener los IDs de las comisiones
        $commissionIds = $commissions->pluck('id');

        // Procesar los IDs en lotes para evitar el error de demasiados placeholders
        $commissionIds->chunk($batchSize)->each(function ($chunk) use (&$commissionDetails) {
            $details = DB::table('commissions_details')
                ->whereIn('commission_id', $chunk)
                ->get();
            $commissionDetails = $commissionDetails->merge($details);
        });

        // Agrupar los detalles por commission_id
        $groupedDetails = $commissionDetails->groupBy('commission_id');

        // Transformar las comisiones para incluir los detalles agrupados
        $commissions->transform(function ($commission) use ($groupedDetails) {
            $commission->details = $groupedDetails->get($commission->id, collect());
            return $commission;
        });

        // Retornar la respuesta JSON
        return response()->json($commissions);
    }


    public function getCommissionWithDetails($commissionId)
    {
        // Obtener la comisión principal
        $commission = DB::table('commissions')
            ->where('id', $commissionId)
            ->first();

        // Obtener las ventas asociadas a la comisión
        $sales = DB::table('commissions_details')
            ->join('client_main_information', 'commissions_details.client_id', '=', 'client_main_information.id')
            ->join('client_bundle_services', 'commissions_details.bundle_id', '=', 'client_bundle_services.id')
            ->where('commissions_details.commission_id', $commissionId)
            ->where('commissions_details.type', 'Venta')
            ->select(
                'client_main_information.name',
                'client_main_information.father_last_name',
                'client_main_information.mother_last_name',
                'client_bundle_services.description',
                'client_bundle_services.price'
            )
            ->get();

        // Obtener los prospectos asociados a la comisión
        $prospects = DB::table('commissions_details')
            ->join('crm_main_information', 'commissions_details.prospect_id', '=', 'crm_main_information.id')
            ->leftJoin('colonies', 'crm_main_information.colony_id', '=', 'colonies.id')
            ->leftJoin('municipalities', 'crm_main_information.municipality_id', '=', 'municipalities.id')
            ->leftJoin('states', 'crm_main_information.state_id', '=', 'states.id')
            ->where('commissions_details.commission_id', $commissionId)
            ->where('commissions_details.type', 'Prospecto')
            ->select(
                'crm_main_information.name',
                'crm_main_information.father_last_name',
                'crm_main_information.mother_last_name',
                'colonies.name as colony',
                'municipalities.name as municipality',
                'states.name as state'
            )
            ->get();

        $commission_details = DB::table('commissions_details')
            ->where('commissions_details.commission_id', $commissionId)
            ->get();

        // Añadir los detalles a la comisión
        $commission->details = [
            'sales' => $sales,
            'prospects' => $prospects,
            'commission_details' => $commission_details
        ];

        return response()->json($commission);
    }

    // sum total commission
    public function getTotalAmountOfTheCommission($id)
    {
        $balance = DB::table('sellers')
            ->where('id', $id)
            ->select('balance')
            ->get();

        return response()->json($balance);
    }
}
