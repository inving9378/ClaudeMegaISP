<?php

namespace App\Http\Controllers\Module\Vendors\Prospects;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CrmLeadInformation;
use Illuminate\Support\Facades\DB;

class ProspectController extends Controller
{
    public function index()
    {
        $prospects = CrmLeadInformation::all();
        return response()->json($prospects);
    }

    // Obtener los prospectos por vendedor
    public function getById($id)
    {
        $prospects = DB::table('crm_main_information')
        ->join('crm_lead_information', 'crm_main_information.crm_id', '=', 'crm_lead_information.crm_id')
        ->where('crm_lead_information.owner_id', $id)
        ->select('crm_main_information.*', 'crm_lead_information.*')
        ->get();
        return response()->json($prospects);
    }

    // Obtener los prospectos por status 
    public function statusProspects($startDate = null, $endDate = null)
    {
        $query = CrmLeadInformation::query();

        if ($startDate && $endDate) {
            $query->whereBetween('last_contacted', [$startDate, $endDate]);
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
}
