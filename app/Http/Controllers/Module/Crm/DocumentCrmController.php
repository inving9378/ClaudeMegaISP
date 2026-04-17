<?php

namespace App\Http\Controllers\Module\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm;
use Illuminate\Http\Request;
use App\Http\HelpersModule\module\crm\DocumentCrmDatatableHelper;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\CrmRepository;
use App\Http\Repository\DocumentTemplateRepository;
use App\Http\Requests\module\client\GenerateContractRequest;
use App\Http\Requests\module\client\ShowPreviewContractRequest;
use App\Http\Requests\module\crm\DocumentCrmUpdateRequest;
use App\Http\Requests\module\crm\DocumentCrmCreateRequest;
use App\Services\ClientService\ContractClientService;
use App\Services\ContractCrmService;
use App\Services\DocumentTemplateService;
use Illuminate\Support\Str;
use function React\Promise\all;

class DocumentCrmController extends Controller
{
    private $helper;
    public function __construct(DocumentCrmDatatableHelper $helper)
    {
        $model = 'DocumentCrm';
        $this->data['url'] = 'meganet.module.' . Str::lower($model);
        $this->data['module'] = $model;
        $this->data['model'] = 'App\Models\\'.$model;
        $this->helper = $helper;
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
      //  return view($this->data['url'] . '.index',$this->data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.add',$this->data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(DocumentCrmCreateRequest $request, $idCrm)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        return Crm::findOrFail($idCrm)->createDocument($request);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\DocumentCrm  $documentCrm
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\DocumentCrm  $documentCrm
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;

        return view($this->data['url'] . '.edit',$this->data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\DocumentCrm  $documentCrm
     * @return \Illuminate\Http\Response
     */
    public function update(DocumentCrmUpdateRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        $input = $request->except('file');
        if (isset($input['show'])) $input['show'] = ($input['show'] == 'true');

        $model = $this->data['model']::find($id);
        $model->updateDocumentUpload($request->file('file'));

        return $model->update($input);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\DocumentCrm  $documentCrm
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $model = $this->data['model']::findOrFail($id);

        // Para que el observer de delete funcione, se debe llamar al metodo delete luego del first()
        $model->file()->delete();
        $model->delete();

        return redirect()->back()->with('message',$this->data['module'] . ' Eliminado Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }


    public function generateContract(GenerateContractRequest $request, $id)
    {
        $ContractCrmService = new ContractCrmService();
        $response = $ContractCrmService->generateContractClient($request, $id);
        return response()->json($response);
    }

    public function loadContentTemplate(ShowPreviewContractRequest $request)
    {
        $documentTemplateRepository = new DocumentTemplateRepository();
        $html = $documentTemplateRepository->getHtmlById($request->template);
        $documentTemplateService = new DocumentTemplateService();
        $variables = $documentTemplateService->getVariables('Crm');
        $variables = array_filter($variables['variables'], function ($value) {
            // Devuelve true solo si el array no está vacío
            return !empty($value);
        });
        return response()->json([
            'html' => $html,
            'variables' => $variables,
        ]);
    }
    public function showContentTemplate(Request $request)
    {
        $contractCrmService = new ContractCrmService();
        $crmRepository = new CrmRepository();
        $documentTemplateService = new DocumentTemplateService();
        $crm = $crmRepository->getModelById($request->idClient);
        $dataClient = $contractCrmService->getData($crm);
        $validation = $documentTemplateService->validateAndReplaceTemplate($request->html, $dataClient,'Crm');

        if ($validation['status'] == 'fail') {
            return response()->json([
                'status' => 'fail',
                'keys' => $validation['keys'],
            ]);
        }

        $documentTemplateRepository = new DocumentTemplateRepository();
        $nameTemplate = $documentTemplateRepository->getNameById($request->template);
        $filePath = 'crm/' . $request->idClient . '/document/temp_contract_template/' . $nameTemplate . '.pdf';
        return $documentTemplateService->saveTemporalTemplateAndReturnPath($validation['html'], $filePath);
    }
}
