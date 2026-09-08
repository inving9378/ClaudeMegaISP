<?php

namespace App\Modules\Core\Documentos\Controllers\DocumentTemplate;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\administration\document_template\DocumentTemplateDatatableHelper;
use App\Http\Repository\ClientRepository;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Modules\Core\CRM\Repositories\CrmRepository;
use App\Http\Repository\DocumentTemplateRepository;
use App\Http\Requests\module\administration\document_template\DocumentTemplateCreateRequest;
use App\Http\Requests\module\administration\document_template\DocumentTemplateUpdateRequest;
use App\Services\ClientService\ContractClientService;
use App\Modules\Core\CRM\Services\ContractCrmService;
use App\Services\DocumentTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'created_by' => auth()->user()?->id
        ]);

        return response()->json([
            'status' => 'ok',
            'file_path' => '/storage/' . $filePath
        ]);
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
        // No se borra el archivo físico: el registro usa soft deletes (recuperable),
        // y borrar el PDF rompería esa recuperación.
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


    /**
     * Acuse de avance en PDF del catálogo de plantillas (item roadmap #9990572, seguimiento
     * de #9990551). `document_templates` no tiene columna `status`/`estado`
     * ni ningún campo de avance (verificado: solo name/html/type/created_by, ver migración
     * archivada en migrations_old/2024_07_23_150303_create_document_templates_table.php) —
     * "Publicada"/"Borrador" se deriva de si `html` tiene contenido, y "avance global" es el
     * % de plantillas publicadas sobre el total. `created_by` es un id de usuario guardado
     * como string (sin FK), se resuelve a nombre si el usuario existe.
     */
    public function exportarAcuse(Request $request)
    {
        $fechaCorte = $request->filled('fecha_corte')
            ? Carbon::parse($request->query('fecha_corte'))->endOfDay()
            : now();

        $plantillas = DocumentTemplate::with('type_template')
            ->orderBy('name')
            ->get()
            ->map(function (DocumentTemplate $t) {
                $publicada = trim((string) $t->html) !== '';
                $usuario = is_numeric($t->created_by) ? User::find($t->created_by) : null;

                return [
                    'nombre'      => $t->name,
                    'tipo'        => optional($t->type_template)->name ?? '—',
                    'estado'      => $publicada ? 'Publicada' : 'Borrador',
                    'publicada'   => $publicada,
                    'autor'       => $usuario->name ?? ('Usuario #' . $t->created_by),
                    'actualizado' => $t->updated_at,
                ];
            });

        $total      = $plantillas->count();
        $publicadas = $plantillas->where('publicada', true)->count();

        $global = [
            'total'      => $total,
            'publicadas' => $publicadas,
            'borrador'   => $total - $publicadas,
            'porcentaje' => $total > 0 ? (int) round($publicadas / $total * 100) : 0,
        ];

        $nombreBase = 'plantillas-acuse-avance-' . now()->format('Ymd-His') . '-' . Str::random(6);

        $pdf = Pdf::loadView('meganet.module.administration.document_template.export.acuse', [
            'plantillas' => $plantillas,
            'global'     => $global,
            'fechaCorte' => $fechaCorte,
            'generado'   => now(),
        ]);

        $destino = $this->rutaTemporalAcuse($nombreBase);
        file_put_contents($destino, $pdf->output());

        return response()->download($destino, "{$nombreBase}.pdf")->deleteFileAfterSend(true);
    }

    private function rutaTemporalAcuse(string $nombreBase): string
    {
        $dir = storage_path('app/document_template/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0770, true);
        }

        return $dir . '/' . $nombreBase . '.pdf';
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
