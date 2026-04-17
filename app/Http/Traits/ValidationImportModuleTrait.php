<?php

namespace App\Http\Traits;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\RouterRepository;
use App\Rules\EnumSearchByIdValue;
use App\Rules\ValidateEmailImportClient;
use App\Rules\ValidateIfUserIsDisponible;
use App\Rules\ValidateIpv4IfNotUsed;
use App\Rules\ValidateIpv4ImportIfNotUsed;
use App\Rules\ValidateIpv4ImportPertenceAlRouter;
use App\Rules\ValidateIpv4PollImportPertenceAlRouter;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

trait ValidationImportModuleTrait
{
    public function validateDataToImport($data)
    {
        $module = null;
        set_time_limit(0);
        $errores = [];
        $translatedColumns = trans('translation_columns_table');

        foreach ($data as $tabla => $infoTabla) {
            $columnas = $infoTabla['columns'];
            $items_to_delete = ['id', 'ID', 'user_id'];
            $columnas = array_diff($columnas, $items_to_delete);
            $columnas = array_values($columnas);
            $translatedColumns = $translatedColumns[$tabla];
            $translate = array_flip($translatedColumns);
            foreach ($columnas as $key => $value) {
                $value = $translate[$value] ?? $value;
                $columnas[$key] = $value;
            }
            $filas = $infoTabla['rows'];
            $model = $infoTabla['model'];
            $module = $model;
            if (empty($filas)) {
                $errores[] = [
                    'columna' => '',
                    'mensaje' => 'El Archivo no contiene datos. No se importará'
                ];
                return $errores;
            }
            $filas = array_filter($filas, function ($fila) {
                return !empty(array_filter($fila, function ($valor) {
                    return !is_null($valor);
                }));
            });
            foreach ($filas as $index => $fila) {
                $input = array_combine($columnas, $fila);
                $rules = $this->getRulesWithModel($model, $input);
                $messages = $this->getMessagesWithModel($model);
                $validator = Validator::make($input, $rules, $messages);
                if ($validator->fails()) {
                    $errores[] = [
                        'columna' => null,
                        'fila' => $index + 2,
                        'error' => true,
                        'valor' => null,
                        'mensaje' => $validator->errors()
                    ];
                } else {
                    $this->insertDataInDB($input, $model);
                }
            }
        }
        Log::info("Has Terminado de Importar: " . $module);

        return $errores;
    }

    protected function setDefaultValuesByModel($model, $value)
    {
        if ($model === 'Client') {
            $value['estado'] = 'Nuevo';
        }
        return $value;
    }

    public function insertDataInDB($data, $model)
    {

        $model = 'App\Models\\' . $model;
        $data['import'] = true;
        $modelInstance = new $model();
        $arrayModelGet = $modelInstance->getRequestAndStoreMethod();
        $storeMethod = $arrayModelGet['storeMethod'];
        if (isset($arrayModelGet['request'])) {
            $request = $arrayModelGet['request'];
            $request->merge($data);
            if (isset($arrayModelGet['parameter_id'])) {
                $id = $arrayModelGet['parameter_id'];
                App::call($storeMethod, ['request' => $request, 'id' => $data[$id]]);
            } else {
                App::call($storeMethod, ['request' => $request]);
            }
        } else {
            $request = new HttpRequest($data);
            App::call($storeMethod, ['request' => $request]);
        };
    }


    public function getMessagesWithModel($model)
    {
        $messages = [
            'ClientMainInformation' => [
                'estado.in' => 'El campo estado solo puede ser :values.',
                'ift.in' => 'El campo estado solo puede ser :values.',
            ],
        ];


        if (isset($messages[$model])) {
            return $messages[$model];
        } else {
            return [];
        }
    }


    public function getRulesWithModel($model, $input)
    {
        $rules = ComunConstantsController::RULES;
        if (isset($rules[$model])) {
            if ($model == 'Client') {
                //$rules[$model]['email'] = new ValidateEmailImportClient($input);
            }
            if ($model == 'ClientInternetService') {
                if ($input['ipv4_assignment'] == ComunConstantsController::IPV4_ASSIGNMENT_POOL_IP) {
                    $rules[$model]['ipv4_pool'] = new ValidateIpv4PollImportPertenceAlRouter($input);
                }
                if ($input['ipv4_assignment'] == ComunConstantsController::IPV4_ASSIGNMENT_STATIC) {
                    $rules[$model]['ipv4'] = new ValidateIpv4ImportPertenceAlRouter($input);
                }

                /* $routerRepository = new RouterRepository();
                $routerId = $routerRepository->getRouterByTitle($input['router_id'])->id ?? null;
                $rules[$model]['user'] = new ValidateIfUserIsDisponible($routerId); */
            }
            if ($model == 'ClientCustomService' && $input['router_id'] != null) {
                $rules[$model]['ipv4'] = new ValidateIpv4ImportPertenceAlRouter($input);
                $rules[$model]['ipv4_pool'] = new ValidateIpv4PollImportPertenceAlRouter($input);

                /* $routerRepository = new RouterRepository();
                $routerId = $routerRepository->getRouterByTitle($input['router_id'])->id ?? null;
                $rules[$model]['user'] = new ValidateIfUserIsDisponible($routerId); */
            }
            return $rules[$model];
        } else {
            return [];
        }
    }


    public function processItems($data, $model, $columnName, $eloquent_model, $relation)
    {
        $items = explode(',', $data);
        $items = array_map('strtolower', $items); // Convertir los nombres de elementos a minúsculas

        $itemIds = $model::whereIn(DB::raw('LOWER(' . $columnName . ')'), $items)
            ->pluck('id')
            ->toArray();

        $eloquent_model->$relation()->attach($itemIds);
    }
}
