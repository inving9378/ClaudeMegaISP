<?php

namespace App\Http\Controllers\Module\Vendors\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CommissionRule;
use App\Models\Seller;

class CommissionRuleController extends Controller
{
    public function index()
    {
        return view('meganet.module.setting.commission.index');
    }

    public function create()
    {
        return view('meganet.module.setting.commission.add');
    }

    public function edit($id_rule)
    {
        return view('meganet.module.setting.commission.edit', compact('id_rule'));
    }


    public function showVendors($id_rule)
    {
        return view('meganet.module.setting.commission.vendors', compact('id_rule'));
    }

    public function getAllRules()
    {
        $rules = CommissionRule::withCount('sellers')->orderBy('id')->paginate(49);
        return response()->json($rules);
    }

    public function getRuleWithSellers($id)
    {
        $rule = CommissionRule::with(['sellers.user'])->findOrFail($id);

        $response = $rule->sellers->map(function ($seller) use ($rule) {
            return [
                'id' => $rule->id,
                'name' => $rule->name,
                'amount' => $rule->amount,
                'fixed_sales_commission' => $rule->fixed_sales_commission,
                'number_of_prospects' => $rule->number_of_prospects,
                'period' => $rule->period,
                'zone' => $rule->zone,
                'iva' => $rule->iva,
                'minimum_sales' => $rule->minimum_sales,
                'total_bonus' => $rule->total_bonus,
                'number_sales_required' => $rule->number_sales_required,
                'conditions' => $rule->conditions,
                'commission_percentage_additional' => $rule->commission_percentage_additional,
                'fixed_sales_commission_additional' => $rule->fixed_sales_commission_additional,
                'installation_cost' => $rule->installation_cost,
                'commission_percentage' => $rule->commission_percentage,
                'seller' => $seller->user->name . ' ' . $seller->user->father_last_name . ' ' . $seller->user->mother_last_name,
                'seller_id' => $seller->id,
                'user_id' => $seller->user_id
            ];
        });

        return response()->json($response);
    }

    public function getSellersByType($type_id)
    {
        $sellers = Seller::where('type_id', $type_id)
            ->whereDoesntHave('commissionRules')
            ->join('users', 'sellers.user_id', '=', 'users.id')
            ->get([
                'sellers.*',
                DB::raw('CONCAT(users.name, " ", users.father_last_name, " ", users.mother_last_name) as full_name')
            ]);

        return response()->json($sellers);
    }


    public function getRuleByIdSeller($seller_id)
    {
        $seller = Seller::find($seller_id);

        $commission_rule = $seller->commissionRules()->first();

        return response()->json($commission_rule);
    }

    public function getRuleById($id)
    {
        $rule = CommissionRule::with('sellers:id')->findOrFail($id);
        $sellerIds = $rule->sellers->pluck('id');

        return response()->json([
            'rule' => $rule,
            'seller_ids' => $sellerIds
        ]);
    }


    public function store(Request $request)
    {
        $conditions = $request->conditions ? json_encode($request->conditions) : '[]';

        $rule = new CommissionRule();
        $rule->name = $request->name;
        $rule->amount = $request->amount;
        $rule->fixed_sales_commission = $request->fixed_sales_commission;
        $rule->number_of_prospects = $request->number_of_prospects;
        $rule->period = $request->period;
        $rule->zone = $request->zone;
        $rule->iva = $request->iva;
        $rule->minimum_sales = $request->minimum_sales;
        $rule->total_bonus = $request->total_bonus;
        $rule->number_sales_required = $request->number_sales_required;
        $rule->conditions = $conditions;
        $rule->commission_percentage_additional = $request->commission_percentage_additional;
        $rule->fixed_sales_commission_additional = $request->fixed_sales_commission_additional;
        $rule->installation_cost = $request->installation_cost;
        $rule->commission_percentage = $request->commission_percentage;

        //distribuidores
        $conditionsComission = $request->conditions_comission ? json_encode($request->conditions_comission) : '[]';

        $rule->total_comission = $request->total_comission;
        $rule->number_sales_bonus_commission_required = $request->number_sales_bonus_commission_required;
        $rule->penalty = $request->penalty;
        $rule->fixed_sales_commission_distribuitors = $request->fixed_sales_commission_distribuitors;
        $rule->fixed_sales_commission_distribuitors_percent = $request->fixed_sales_commission_distribuitors_percent;
        $rule->conditions_comission = $conditionsComission;

        $rule->save();

        $rule->sellers()->attach($request->sellers);

        return response()->json(['status' => 200, 'message' => 'Registro guardado correctamente']);
    }

    public function update(Request $request, $id)
    {
        $conditions = $request->conditions ? json_encode($request->conditions) : '[]';

        $rule = CommissionRule::findOrFail($id);
        $rule->name = $request->name;
        $rule->amount = $request->amount;
        $rule->fixed_sales_commission = $request->fixed_sales_commission;
        $rule->number_of_prospects = $request->number_of_prospects;
        $rule->period = $request->period;
        $rule->zone = $request->zone;
        $rule->iva = $request->iva;
        $rule->minimum_sales = $request->minimum_sales;
        $rule->total_bonus = $request->total_bonus;
        $rule->number_sales_required = $request->number_sales_required;
        $rule->conditions = $conditions;
        $rule->commission_percentage_additional = $request->commission_percentage_additional;
        $rule->fixed_sales_commission_additional = $request->fixed_sales_commission_additional;
        $rule->installation_cost = $request->installation_cost;
        $rule->commission_percentage = $request->commission_percentage;

        //distribuidores
        $conditionsComission = $request->conditions_comission ? json_encode($request->conditions_comission) : '[]';

        $rule->total_comission = $request->total_comission;
        $rule->number_sales_bonus_commission_required = $request->number_sales_bonus_commission_required;
        $rule->penalty = $request->penalty;
        $rule->fixed_sales_commission_distribuitors = $request->fixed_sales_commission_distribuitors;
        $rule->fixed_sales_commission_distribuitors_percent = $request->fixed_sales_commission_distribuitors_percent;
        $rule->conditions_comission = $conditionsComission;

        $rule->save();

        $rule->sellers()->sync($request->seller_id);

        return response()->json(['status' => 200, 'message' => 'Registro actualizado correctamente']);
    }

    public function destroy($id)
    {
        $rule = CommissionRule::find($id);
        $rule->delete();

        return response()->json(['status' => 200, 'message' => 'Regla de comisión eliminada correctamente']);
    }
}
