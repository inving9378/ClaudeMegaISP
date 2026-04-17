<?php

namespace App\Http\Controllers\Module\Setting\Rules;


use App\Http\Controllers\Base\CrudModalController;
use App\Http\HelpersModule\module\setting\rules\RuleDatatableHelper;
use App\Http\Requests\module\base\CrudModalValidationRequest;
use App\Models\CommissionRule;
use App\Models\DurationContract;
use App\Models\GeneralConfigurationRule;
use App\Models\HistoryGeneralConfigurationRule;
use App\Models\Seller;
use App\Models\SellerType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RuleController extends CrudModalController
{
    public function __construct(RuleDatatableHelper $helper)
    {
        parent::__construct($helper, new CrudModalValidationRequest());
        $this->data['model'] = 'App\Models\CommissionRule';
        $this->data['url'] = 'meganet.module.setting.rules';
        $this->data['module'] = 'CommissionRule';
    }

    public function getSellersByType($type)
    {
        $sellers = Seller::doesntHaveRules($type)
            ->get();
        return response()->json($sellers);
    }

    public function getContractsDurations()
    {
        $list = DurationContract::select('id as value', 'name as label')->orderBy('duration', 'ASC')->get();
        return $list;
    }

    public function create()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['sellers_type'] = SellerType::select('id as value', 'name as label')->get();
        $this->data['contracts_durations'] = $this->getContractsDurations();
        return view($this->data['url'] . '.create', $this->data);
    }

    public function store(Request $request)
    {
        $this->validate($request, $this->crudValidationRequest->storeRules(), $this->crudValidationRequest->storeMessageRules());
        try {
            $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
            $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
                $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
                $request->all();

            $model = $this->data['model']::create($input);
            $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);
            $model->updateRulesHistory();
            return response()->json([
                'success' => true,
                'message' => 'Los datos se han guardado con éxito.',
                'model' => $model
            ], 200);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud',
            ], 500);
        }
    }

    public function edit(Request $request, $id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $model = $this->data['model']::find($id);
        $this->data['model'] = $model;
        $this->data['sellers_type'] = SellerType::select('id as value', 'name as label')->get();
        $this->data['contracts_durations'] = $this->getContractsDurations();
        $sellers_doesnt_have_commission_rules = Seller::doesntHaveRules($model->type_of_seller);
        $sellers_commission_rules = Seller::hasRules($model->type_of_seller, $id);
        $this->data['sellers_list'] = $sellers_doesnt_have_commission_rules->union($sellers_commission_rules)->get();
        return view($this->data['url'] . '.edit', $this->data);
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, $this->crudValidationRequest->updateRules(), $this->crudValidationRequest->updateMessageRules());
        try {
            $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
            $model = $this->data['model']::find($id);
            $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
                $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
                $request->all();
            $oldSellers =  $model->sellers()->get();
            $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');
            $objet = $model;
            $model = $model->update($input);
            $objet->updateRulesHistory($oldSellers);
            return response()->json([
                'success' => true,
                'message' => 'Los datos se ha actualizado con éxito.',
                'model' => $model
            ], 200);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud',
            ], 500);
        }
    }

    public function generalConfig()
    {
        $rule = GeneralConfigurationRule::first();
        return response()->json($rule);
    }

    public function saveGeneralConfig(Request $request)
    {
        $rule = GeneralConfigurationRule::find($request->id);
        $rule->installation_cost = $request->installation_cost;
        $rule->iva = $request->iva;
        $rule->save();
        return response()->json($rule);
    }

    public function getAll(Request $request)
    {
        $rules = CommissionRule::all();
        return response()->json($rules);
    }
}
