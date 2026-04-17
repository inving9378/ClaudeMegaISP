<?php

namespace App\Http\Controllers\Module\Plan;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\planes\InternetDatatableHelper;
use App\Http\Requests\module\plan\InternetCreateRequest;
use App\Http\Requests\module\plan\InternetUpdateRequest;
use App\Http\Traits\ValidationImportModuleTrait;
use App\Models\ClientInternetService;
use App\Models\Internet;
use App\Models\Module;
use App\Models\Partner;
use App\Models\TypeBilling;
use App\Services\ImportdDBService;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InternetController extends Controller
{

    private $helper;
    use ValidationImportModuleTrait;
    public function __construct(InternetDatatableHelper $helper)
    {
        $model = 'Internet';
        $this->data['url'] = 'meganet.module.' . Str::lower($model);
        $this->data['module'] = 'internet';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'plan';
        $this->helper = $helper;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.index', $this->data);
    }

    public function success($id)
    {
        $message =  'Plan de Internet ' . ($id == 'null' ? 'Creado' : 'Actualizado') . ' Correctamente';
        return redirect()->route('internet')->with(['message' => $message]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.add', $this->data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(InternetCreateRequest $request)
    {
        // Trunca el precio a 2 decimales SIN redondear
        $rawPrice = (float)$request->input('price');
        $truncatedPrice = floor($rawPrice * 100) / 100; // Ej: 499.9999 → 499.99

        $request->merge([
            'price' => (string)$truncatedPrice, // Guarda como "499.99"
        ]);
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->except('id_old');

        $input['cost_activation'] = $input['cost_activation'] ?? '0.00';
        $input['cost_instalation'] = $input['cost_instalation'] ?? '0.00';

        $input = (new PromotionService())->createPromotionIfPromotionEnable($input);

        if ($request->import) {
            $this->imporDataToTable($input, $request);
        } else {
            $model = $this->data['model']::create($input, $request);
            $this->saveRelationMultipleIfExist($this->data['model'], $model, $request);
            return $model;
        }
    }

    public function imporDataToTable($input, $request)
    {
        $newImportDbService = new ImportdDBService();
        $module = Module::where('name', Module::PLAN_INTERNET_MODULE_NAME)->first();
        $input = $newImportDbService->processInputImportByModule($input, $module);
        $input['created_at'] = now();
        $input['updated_at'] = now();
        $input['cost_activation'] = $input['cost_activation'] ?? '0.00';
        $input['cost_instalation'] = $input['cost_instalation'] ?? '0.00';
        $input['id'] = $input['id_old'];
        unset($input['import']);
        unset($input['id_old']);

        DB::table('internets')->insert($input);
        $model = $this->data['model']::where('id', $input['id'])->first();
        $this->processItems($request->partners, Partner::class, 'name', $model, 'partners');
        $this->processItems($request->types_of_billing, TypeBilling::class, 'type', $model, 'billings');
        $this->processItems($request->rates_to_change, Internet::class, 'title', $model, 'plan_internet_client');
        return $model;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;
        return view($this->data['url'] . '.edit', $this->data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(InternetUpdateRequest $request, $id)
    {
        // Trunca el precio a 2 decimales SIN redondear
        $rawPrice = (float)$request->input('price');
        $truncatedPrice = floor($rawPrice * 100) / 100; // Ej: 499.9999 → 499.99

        $request->merge([
            'price' => (string)$truncatedPrice, // Guarda como "499.99"
        ]);
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $model = $this->data['model']::find($id);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();

        $input = (new PromotionService())->updateAndReturnInput($input, $model);

        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');
        return $model->update($input);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        ClientInternetService::where('internet_id', $id)->with('ClientIntenetService')->delete();
        $this->data['model']::findOrFail($id)->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }
}
