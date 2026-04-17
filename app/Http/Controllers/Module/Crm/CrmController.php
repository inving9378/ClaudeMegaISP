<?php

namespace App\Http\Controllers\Module\Crm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Module\Client\ClientHelperController;
use App\Http\Requests\module\crm\ConvertToClientRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Client;
use App\Models\ClientAdditionalInformation;
use App\Models\ClientMainInformation;
use App\Models\CrmLeadInformation;
use App\Models\Module;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\HelpersModule\module\crm\CrmDatatableHelper;
use App\Http\Requests\module\crm\CrmCreateRequest;
use App\Models\Crm;
use Illuminate\Support\Facades\Auth;
use App\Events\ProspectRegistered;
use Spatie\Activitylog\Models\Activity;

class CrmController extends Controller
{
    private $helper;

    public function __construct(CrmDatatableHelper $helper)
    {
        $model = 'Crm';
        $this->data['url'] = 'meganet.module.crm';
        $this->data['module'] = 'Crm';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['group'] = 'crm';
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
        return redirect('/crm/editar/' . $id);
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
    public function store(CrmCreateRequest $request)
    {
        try {
            DB::beginTransaction();
            $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
            $model = $this->data['model']::create($request->only('enable_same_name_or_rfc'))
                ->createMainInformation($request)
                ->createLeadInformation($request);

            $prospect = $model->crm_lead_information()->first();
            event(new ProspectRegistered($prospect));

            $model->log_activities()->create([
                'user_id' => Auth::user()->id,
                'type' => 'create_crm',
                'data' => json_encode([
                    'crm' => $model->toArray(),
                    'crm_main_information' => $model->crm_main_information()->first()->toArray(),
                    'crm_lead_information' => $model->crm_lead_information()->first()->toArray()
                ])
            ]);

            DB::commit();
            return $model;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
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
        $this->data['tabs'] = $this->getTabs();
        $crmLeadInformation = CrmLeadInformation::where('crm_id', $id)->first();
        if ($crmLeadInformation) {
            $crmLeadInformation->score++;
            $crmLeadInformation->update();
            return view($this->data['url'] . '.edit', $this->data);
        }
        return view('meganet.pages.404');
    }

    public function getTabs()
    {
        $tabs = [];
        if ($this->userAutenticated()->hasPermissionTo('crm_information_view_tab_crm') || $this->userAutenticated()->isAdmin()) $tabs[] = 'information';
        if ($this->userAutenticated()->hasPermissionTo('crm_document_view_tab_crm') || $this->userAutenticated()->isAdmin()) $tabs[] = 'documents';
        return json_encode($tabs);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function ver($id)
    {
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;

        return view($this->data['url'] . '.ver', $this->data);
    }


    public function viewOfConvertCrmToClient($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;
        return view($this->data['url'] . '.convert', $this->data);
    }

    public function convertToClient(ConvertToClientRequest $request, $crmId)
    {
        // Inicia la transacción
        return DB::transaction(function () use ($request, $crmId) {
            $crm = Crm::findOrFail($crmId); // Si no existe, lanza excepción y revierte
            $client = Client::create();

            // 1. Crear ClientMainInformation
            $modules = Module::where('name', 'ClientMainInformation')->first()->fields->pluck('name')->toArray();
            $input = $request->only($modules);
            $input['client_id'] = $client->id;
            $new_client = ClientMainInformation::create($input);

            // 2. Crear ClientAdditionalInformation
            $modules = Module::where('name', 'ClientAdditionalInformation')->first()->fields->pluck('name')->toArray();
            $input = $request->only($modules);
            $input['client_id'] = $client->id;
            ClientAdditionalInformation::create($input);

            // 3. Procesar documentos y pasos adicionales
            $client->addDocumentFromOlderCrm($crm);

            $clientHelperController = new ClientHelperController($client);
            $clientHelperController->stepNeededWhenNewClientIsCreated();

            // 4. Registrar actividad y eliminar CRM
            activity()->tap(function (Activity $activity) use ($client) {
                $activity->client_id = $client->id;
            })->log('Cliente #' . $client->id . ' creado desde el convertir crm to client por ' . Auth::user()->name);

            $crm->delete();

            return $client->id;
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $model = $this->data['model']::findOrFail($id);
        $model->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }

    public function updateLastContacted($id)
    {
        $this->data['model']::findOrFail($id)->crm_lead_information()->update(['last_contacted' => Carbon::now()->toDateString()]);
    }

    public function getCrmMainInformationIdAndCrmLeadInformationId($crmId)
    {
        $model = $this->data['model']::findOrFail($crmId);
        return [
            'crmMainInformationId' => $model->crm_main_information->id,
            'crmLeadInformationId' => $model->crm_lead_information->id,
        ];
    }
}
