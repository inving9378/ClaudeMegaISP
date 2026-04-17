<?php

namespace App\Http\Controllers\Module\Setting\Tools;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FileController;
use App\Http\HelpersModule\module\setting\tools\SettingTollsImportDatatableHelper;
use App\Http\Repository\ModuleRepository;
use App\Http\Requests\module\setting\tools\ImportControllerCreateRequest;
use App\Http\Traits\ValidationImportModuleTrait;
use App\Services\ImportdDBService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportController extends Controller
{
    private $helper;
    use ValidationImportModuleTrait;

    public function __construct(SettingTollsImportDatatableHelper $helper)
    {
        $this->data['model'] = 'App\Models\SettingToolsImport';
        $this->data['url'] = 'meganet.module.setting.tools.import';
        $this->data['module'] = "SettingToolsImport";
        $this->data['modules'] = $this->getAllModules();
        $this->helper = $helper;
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic('FieldModule');
        return view('meganet.module.setting.tools.import.listar', $this->data);
    }

    public function create()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.add', $this->data);
    }

    public function store(ImportControllerCreateRequest $request)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $input = $request->all();
        $errors = $this->insertRegisterInDB($input);
        if (!empty($errors) && isset($errors['errors'])) {
            $formatedError = [];
            $formatedError['file'] = $errors['errors'];
            throw ValidationException::withMessages($formatedError);
        }
        $input['file'] = $this->uploadAndSaveDocumentUploaded($input['file']);
        $input['created_by'] = auth()->user()->id;
        $this->data['model']::create($input);
        $errorsPass = $errors['errorsPass'];
        if (empty($errorsPass)) {
            $errorsPass = null;
        }
        return response()->json(['success' => true, 'errorsPass' => $errorsPass]);
    }

    public function storeImport($request)
    {
        $input = $request;
        $errors = $this->insertRegisterInDBImported($input);

        if (!empty($errors) && isset($errors['errors'])) {
            $formatedError = [];
            $formatedError['file'] = $errors['errors'];
            throw ValidationException::withMessages($formatedError);
        }

        // Asegúrate de manejar correctamente la instancia de UploadedFile        
        $input['created_by'] = 1;

        $this->data['model']::create($input);

        $errorsPass = $errors['errorsPass'];
        if (empty($errorsPass)) {
            $errorsPass = null;
        }

        return response()->json(['success' => true, 'errorsPass' => $errorsPass]);
    }

    public function insertRegisterInDBImported($input)
    {
        $data = $this->getDataToFileImport($input['file'], $input['module_id']);
        $errors = $this->validateData($data);
        $rowsToDeleteData = [];
        $ipmortErrors = [];
        if (!empty($errors)) {
            $erroresAMostrar = [];
            foreach ($errors as $key => $value) {
                if ($value['columna'] == 'email') {
                    $ipmortErrors[] = $value;
                    $rowsToDeleteData[] = $value['fila'] - 2; //TODO Se usa -2 debido a que en el excel la fila 0 es la segunda
                } else {
                    $erroresAMostrar[] = $value;
                }
            }
            if ($erroresAMostrar != []) {
                return [
                    'errors' => $erroresAMostrar,
                    'errorsPass' => $ipmortErrors
                ];
            }
        }

        return ['errorsPass' => $ipmortErrors];
    }

    public function insertRegisterInDB($input)
    {
        $data = $this->getDataToFile($input['file'], $input['module_id']);
        $errors = $this->validateData($data);
        $rowsToDeleteData = [];
        $ipmortErrors = [];
        if (!empty($errors)) {
            $erroresAMostrar = [];
            foreach ($errors as $key => $value) {
                if ($value['columna'] == 'email') {
                    $ipmortErrors[] = $value;
                    $rowsToDeleteData[] = $value['fila'] - 2; //TODO Se usa -2 debido a que en el excel la fila 0 es la segunda
                } else {
                    $erroresAMostrar[] = $value;
                }
            }
            if ($erroresAMostrar != []) {
                return [
                    'errors' => $erroresAMostrar,
                    'errorsPass' => $ipmortErrors
                ];
            }
        }

        return ['errorsPass' => $ipmortErrors];
    }

    public function getDataToFile($file, $moduleId)
    {
        $moduleRepository = new ModuleRepository();
        $module =  $moduleRepository->getModuleById($moduleId);
        $filePath = $file->getRealPath();
        $spreadsheet = IOFactory::load($filePath);
        $data = [];
        $tabla = [];
        $array = [];
        $instanceModel = new ($module->getNameModel());
        $tabla = $instanceModel->getTable();
        $sheet = $spreadsheet->getSheet(0);
        $model = $module->getNameModel();
        $model = class_basename($model);
        $data = $sheet->toArray();
        $columns = array_shift($data);
        $array[$tabla] = [
            'columns' => $columns,
            'rows' => $data,
            'model' => $model
        ];
        return $array;
    }

    public function getDataToFileImport($file, $moduleId)
    {
        $moduleRepository = new ModuleRepository();
        $module =  $moduleRepository->getModuleById($moduleId);
        $filePath = $file;
        $spreadsheet = IOFactory::load($filePath);
        $data = [];
        $tabla = [];
        $array = [];
        $instanceModel = new ($module->getNameModel());
        $tabla = $instanceModel->getTable();
        $sheet = $spreadsheet->getSheet(0);
        $model = $module->getNameModel();
        $model = class_basename($model);
        $data = $sheet->toArray();
        $columns = array_shift($data);
        $array[$tabla] = [
            'columns' => $columns,
            'rows' => $data,
            'model' => $model
        ];
        return $array;
    }

    public function update(Request $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $model = $this->data['model']::find($id);
        $input = $request->all();

        return $model->update($input);
    }

    public function destroy($id)
    {
        $import = $this->data['model']::findOrFail($id);
        $filePath = 'public/' . $import->file;
        if (Storage::exists($filePath)) {
            Storage::delete($filePath);
        }
        $import->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function getAllModules()
    {
        $moduleRepository = new ModuleRepository();
        $arrayModules = $moduleRepository->getModulesForImport()->groupBy('group')->map(function ($modules) {
            return $modules->mapWithKeys(function ($module) {
                return [
                    $module->id => $module->name
                ];
            });
        })->toArray();

        $modulesNotShowInSelect = [];

        $filteredModules = collect($arrayModules)->map(function ($group) use ($modulesNotShowInSelect) {
            return array_filter($group, function ($moduleName, $moduleId) use ($modulesNotShowInSelect) {
                return !in_array($moduleName, $modulesNotShowInSelect) && !in_array($moduleId, $modulesNotShowInSelect);
            }, ARRAY_FILTER_USE_BOTH);
        })->toArray();

        $translateModules = trans('translation_modules');
        $arrayReturn = [];

        foreach ($filteredModules as $groupKey => $group) {
            foreach ($group as $moduleId => $moduleName) {
                $translatedName = $translateModules[$moduleName] ?? $moduleName;
                $arrayReturn[$translateModules[$groupKey]][$moduleId] = $translatedName;
            }
        }
        return $arrayReturn;
    }


    public function uploadAndSaveDocumentUploaded($file)
    {
        $idClient = auth()->user()->id;
        $file_process = new FileController;
        $module_path = 'settingImport/' . $idClient . '/document_import';
        $properties = $file_process->processSingleFileImporAndReturnProperties($file, $module_path);

        $uniqueFileName = uniqid() . '_' . $properties['name']; // Generar un nombre único para el archivo
        $file->storeAs('/public/' . $module_path, $uniqueFileName);

        return $module_path . '/' . $uniqueFileName;
    }


    public function getExampleForThisModule(Request $request)
    {
        $idModule = $request->id_module;
        $moduleRepository = new ModuleRepository();
        $module = $moduleRepository->getModuleById($idModule);
        $service = new ImportdDBService();
        return $service->exportExample($module);
    }

    public function getTableForThisModel($model)
    {
        $modelo = new $model();
        return $modelo->getTable();
    }


    public function validateData($data)
    {
        return $this->validateDataToImport($data);
    }
}
