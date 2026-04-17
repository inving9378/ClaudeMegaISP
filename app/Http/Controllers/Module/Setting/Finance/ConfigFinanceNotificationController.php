<?php

namespace App\Http\Controllers\Module\Setting\Finance;

use App\Http\Controllers\Controller;
use App\Http\Repository\ConfigFinanceNotificationRepository;
use App\Http\Repository\ModuleRepository;
use App\Http\Requests\module\setting\config_finance_notification\ConfigFinanceNotificationUpdateRequest;
use App\Models\ConfigFinanceNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConfigFinanceNotificationController extends Controller
{
    public function __construct()
    {
        $model = 'ConfigFinanceNotification';
        $this->data['url'] = 'meganet.module.setting.finance.notifications';
        $this->data['title'] = 'Configuración de las Notificaciones de Finanzas';
        $this->data['model'] = 'App\Models\\' . $model;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic('ConfigFinanceNotification');
        return view($this->data['url'] . '.index', $this->data);
    }

    public function getModuleIdByName()
    {
        $moduleRepository = new ModuleRepository();
        return $moduleRepository->getModuleByName('ConfigFinanceNotification')->id;
    }

    public function edit($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;

        return view($this->data['url'] . '.edit', $this->data);
    }

    public function store(Request $request)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $model = $this->data['model']::create();
        return $model;
    }

    public function update(ConfigFinanceNotificationUpdateRequest $request, $id)
    {

        // Validar los campos según las reglas definidas en el módulo
       // $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);

        // Buscar el modelo o fallar con 404 si no existe
        $model = $this->data['model']::findOrFail($id);

        try {
            // Obtener todos los datos del request (considerar usar $request->validated() si usas FormRequest)
            $input = $request->all();

            // Actualizar el modelo
            $model->update($input);

            // Respuesta exitosa
            return response()->json([
                'success' => true,
                'message' => 'Los datos se han guardado correctamente.',
                'data' => $model->fresh() // Devuelve el modelo actualizado desde la BD
            ], 200);

        } catch (\Exception $e) {
            // Log del error con más contexto
            Log::error("Error al actualizar registro ID {$id} en " . get_class($model) . ": " . $e->getMessage(), [
                'exception' => $e,
                'input_data' => $request->all()
            ]);

            // Respuesta de error
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null // Solo muestra el error en desarrollo
            ], 500);
        }
    }

    public function getDataTabs()
    {
        try {
            $configFinanceRepository = new ConfigFinanceNotificationRepository();
            $data = $configFinanceRepository->getAll();
            return response()->json([
                'success' => true,
                'message' => 'Los datos se ha guardado con éxito.',
                'tabs' => $data
            ], 200);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud',
            ], 500);
        }
    }
}
