<?php

namespace App\Http\Controllers\Module\Administration\DocumentTemplate;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\administration\document_template\DocumentTemplateDatatableHelper;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\CrmRepository;
use App\Http\Repository\DocumentTemplateRepository;
use App\Http\Requests\module\administration\document_template\DocumentTemplateCreateRequest;
use App\Http\Requests\module\administration\document_template\DocumentTemplateUpdateRequest;
use App\Models\DocumentTemplate;
use App\Services\ClientService\ContractClientService;
use App\Services\ContractCrmService;
use App\Services\DocumentTemplateService;
use Illuminate\Http\Request;

class DocumentTemplateController extends Controller
{
    private $helper;

    public function __construct(DocumentTemplateDatatableHelper $helper)
    {
        $model = 'DocumentTemplate';
        $this->data['url'] = 'meganet.module.administration.document_template';
        $this->data['module'] = 'DocumentTemplate';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['filters'] = $this->filters();
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
    public function store(DocumentTemplateCreateRequest $request)
    {
        if (isset($request->name) && $request->name == '' || $request->name == null) {
            return response()->json([
                'status' => 'fail',
                'keys' => ['Debe seleccionar un nombre para la plantilla'],
            ]);
        }
        $documentTemplateService = new DocumentTemplateService();
        $validation = $documentTemplateService->validateAndReplaceTemplate($request->html);
        if ($validation['status'] == 'fail') {
            return response()->json([
                'status' => 'fail',
                'keys' => $validation['keys'],
            ]);
        }
        $nameTemplate = $request->name;
        $filePath = 'document_template/document/' . $nameTemplate . '.pdf';
        $documentTemplateService->saveDocumentTemplate($filePath, $validation['html']);


        $documentTemplateRepository = new DocumentTemplateRepository();
        $documentTemplateRepository->createDocumentTemplate([
            'name' => $nameTemplate,
            'html' => $request->html,
            'type' => $request->type,
            'created_by' => auth()->user()->id
        ]);

        return response()->json([
            'status' => 'ok',
            'file_path' => '/storage/' . $filePath
        ]);
    }

    public function show(DocumentTemplate $log)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Client $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);

        return view($this->data['url'] . '.edit', $this->data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Client $client
     * @return \Illuminate\Http\Response
     */
    public function update(DocumentTemplateUpdateRequest $request)
    {

        $documentTemplateService = new DocumentTemplateService();
        $template = $this->data['model']::find($request->template);

        $validation = $documentTemplateService->validateAndReplaceTemplate($request->html);
        if ($validation['status'] == 'fail') {
            return response()->json([
                'status' => 'fail',
                'keys' => $validation['keys'],
            ]);
        }

        $nameTemplate = null;
        if (isset($request->name) && $request->name != null && $request->name != '' && $request->name != 'null') {
            $nameTemplate = $request->name;
        } else {
            $nameTemplate = $template->name;
        }

        $filePath = 'document_template/document/' . $template->name . '.pdf';
        $documentTemplateService->deleteTemplateFile($filePath);

        $filePath = 'document_template/document/' . $nameTemplate . '.pdf';
        $template->name = $nameTemplate;
        $template->html = $validation['html'];
        $template->type = $request->type;
        $template->save();
        $documentTemplateService->saveDocumentTemplate($filePath, $validation['html']);
        return response()->json([
            'status' => 'ok',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Client $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $template = $this->data['model']::find($id);
        $filePath = 'document_template/document/' . $template->name . '.pdf'; //TODO Preguntar si eliminamos el archivo ya que se usa soft deletes
        $template->delete();
        return response()->json([
            'status' => 'ok',
        ]);
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function filters()
    {
        $filters[] = json_encode(
            [
                'search' =>
                [
                    'label' => 'Tipo de Documento',
                    'field' => 'type',
                    'model' => 'App\Models\DocumentTypeTemplate',
                    'id' => 'id',
                    'text' => 'name'
                ]
            ]

        );
        return $filters;
    }


    public function loadContentTemplate(Request $request)
    {
        $documentTemplateRepository = new DocumentTemplateRepository();
        if ($request->template == null || $request->template == 'null') {
            $html = $request->html;
        } else {
            $html = $documentTemplateRepository->getHtmlById($request->template);
        }
        return response()->json([
            'html' => $html,
        ]);
    }
    public function showContentTemplate(Request $request)
    {
        $data = null;
        if (isset($request->module)) {
            $data = $this->getDataByModule($request);
        }

        $documentTemplateService = new DocumentTemplateService();
        $validation = $documentTemplateService->validateAndReplaceTemplate($request->html, $data, $request->module);
        if ($validation['status'] == 'fail') {
            return response()->json([
                'status' => 'fail',
                'keys' => $validation['keys'],
            ]);
        }
        return $documentTemplateService->returnPath($validation['html']);
    }

    public function showContentTemplateById(Request $request, $id)
    {
        $html = $request->html;
        $documentTemplateService = new DocumentTemplateService();
        $validation = $documentTemplateService->validateAndReplaceTemplate($html, null, 'DocumentTemplate');
        if ($validation['status'] == 'fail') {
            return response()->json([
                'status' => 'fail',
                'keys' => $validation['keys'],
            ]);
        }
        $documentTemplateService = new DocumentTemplateService();
        return $documentTemplateService->returnPath($validation['html']);
    }


    public function getVariables(Request $request)
    {
        $module = $request->module;
        $documentTemplateService = new DocumentTemplateService();
        $variables = $documentTemplateService->getVariables($module);

        $variables = array_filter($variables['variables'], function ($value) {
            // Devuelve true solo si el array no está vacío
            return !empty($value);
        });

        return response()->json([
            'variable' => $variables
        ]);
    }

    public function getDataTemplate($id)
    {
        $documentTemplateRepository = new DocumentTemplateRepository();
        $documentTemplate = $documentTemplateRepository->getModelById($id);
        return response()->json([
            'html' => $documentTemplate->html,
            'name' => $documentTemplate->name
        ]);
    }


    public function getDataByModule($request)
    {
        $data = null;
        if ($this->moduleIsClient($request->module)) {
            $clientRepository = new ClientRepository();
            $client = $clientRepository->getClientById($request->idClient);
            $contractClientService = new ContractClientService();
            $data = $contractClientService->getDataClient($client);
        }

        if ($this->moduleIsCrm($request->module)) {
            $crmRepository = new CrmRepository();
            $crm = $crmRepository->getModelById($request->idClient);
            $contractClientService = new ContractCrmService();
            $data = $contractClientService->getData($crm);
        }

        return $data;
    }

    public function moduleIsClient($module)
    {
        return $module == "Client";
    }

    public function moduleIsCrm($module)
    {
        return $module == "Crm";
    }
}
