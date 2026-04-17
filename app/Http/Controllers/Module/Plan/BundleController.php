<?php

namespace App\Http\Controllers\Module\Plan;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\planes\BundleDatatableHelper;
use App\Http\Requests\module\plan\BundleCreateRequest;
use App\Http\Requests\module\plan\BundleUpdateRequest;
use App\Http\Traits\ValidationImportModuleTrait;
use App\Models\Bundle;
use App\Models\Custom;
use App\Models\Internet;
use App\Models\Module;
use App\Models\Partner;
use App\Models\TypeBilling;
use App\Models\Voise;
use App\Services\ImportdDBService;
use App\Services\PromotionService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BundleController extends Controller
{
    private $helper;
    use ValidationImportModuleTrait;

    public function __construct(BundleDatatableHelper $helper)
    {
        $model = 'Bundle';
        $this->data['url'] = 'meganet.module.paquetes';
        $this->data['module'] = 'Bundle';
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
        $message =  'Plan de Paquetes ' . ($id == 'null' ? 'Creado' : 'Actualizado') . ' Correctamente';
        return redirect()->route('paquetes')->with(['message' => $message]);
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
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(BundleCreateRequest $request)
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
            $request->all();

        $planes = ['planes_internet', 'planes_voz', 'planes_custom'];
        $input = collect($input)->except($planes)->toArray();
        $input['cost_instalation'] = $input['cost_instalation'] ?? '0.00';

        $input = (new PromotionService())->createPromotionIfPromotionEnable($input);

        if ($request->import) {
            $this->imporDataToTable($input, $request);
        } else {
            $bundle = $this->data['model']::create($input);
            foreach ($planes as $value) {
                foreach (collect($request->$value)->groupBy("value") as $key => $val) {
                    $bundle->$value()->attach($key, ['cant' => count($val)]);
                }
            }
            $this->saveRelationMultipleIfExist($this->data['model'], $bundle, $request);
            return $bundle;
        }
    }

    public function imporDataToTable($input, $request)
    {
        $newImportDbService = new ImportdDBService();
        $module = Module::where('name', Module::BUNDLE_SERVICE_MODULE_NAME)->first();
        $input = $newImportDbService->processInputImportByModule($input, $module);
        unset($input['import']);

        $input['id'] = $input['id_old'];
        unset($input['id_old']);

        $planInternetRequest = explode(',', $request->planes_internet);
        $internets = [];
        if (!empty($planInternetRequest)) {
            foreach ($planInternetRequest as $planTitle) {
                $internet = Internet::where('title', $planTitle)->first();
                if ($internet) {
                    $internets[] = [
                        'title' => $internet->title,
                        'value' => $internet->id
                    ];
                }
            }
        }


        $planCustomRequest = explode(',', $request->planes_custom);
        $customs = [];
        if (!empty($planCustomRequest)) {
            foreach ($planCustomRequest as $planTitle) {
                $custom = Custom::where('title', $planTitle)->first();
                if ($custom) {
                    $customs[] = [
                        'title' => $custom->title,
                        'value' => $custom->id
                    ];
                }
            }
        }


        $planVozRequest = explode(',', $request->planes_voz);
        $voises = [];
        if (!empty($planVozRequest)) {
            foreach ($planVozRequest as $planTitle) {
                $voz = Voise::where('title', $planTitle)->first();
                if ($voz) {
                    $voises[] = [
                        'title' => $voz->title,
                        'value' => $voz->id
                    ];
                }
            }
        }

        DB::table('bundles')->insert($input);
        $model = $this->data['model']::where('id', $input['id'])->first();
        $this->processItems($request->partners, Partner::class, 'name', $model, 'partners');
        $this->processItems($request->types_of_billing, TypeBilling::class, 'type', $model, 'billings');

        foreach (collect($internets)->groupBy("value") as $key => $val) {
            $model->planes_internet()->attach($key, ['cant' => count($val)]);
        }

        foreach (collect($customs)->groupBy("value") as $key => $val) {
            $model->planes_custom()->attach($key, ['cant' => count($val)]);
        }

        foreach (collect($voises)->groupBy("value") as $key => $val) {
            $model->planes_voz()->attach($key, ['cant' => count($val)]);
        }
        return $model;
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
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
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(BundleUpdateRequest $request, $id)
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
        $planes = ['planes_internet', 'planes_voz', 'planes_custom'];
        $input = collect($input)->except($planes)->toArray();

        $input = (new PromotionService())->updateAndReturnInput($input, $model);

        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');

        foreach ($planes as $value) {
            $model->$value()->detach();
            foreach (collect($request->$value)->groupBy("value") as $key => $val) {
                $model->$value()->attach($key, ['cant' => count($val)]);
            }
        }
        return $model->update($input);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->data['model']::findOrFail($id)
            ->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }
}
