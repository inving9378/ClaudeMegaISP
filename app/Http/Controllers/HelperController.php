<?php

namespace App\Http\Controllers;

use App\Http\Base\Encryption;
use App\Http\Repository\TeamRepository;
use App\Models\Client;
use App\Models\Crm;
use App\Models\Location;
use App\Models\Module;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Traits\RouterConnection;
use App\Models\ClientMainInformation;
use App\Models\UserColumnDatatableExpand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HelperController extends Controller
{
    use RouterConnection;

    public function getNextUserId()
    {
        return User::get()->count() ?? 0;
    }

    public function getFieldsByModule(Request $request)
    {
        $module = Module::where('name', $request->module)->first();
        return $module->getfields();
    }

    public function getFieldsByModuleRelation(Request $request)
    {
        $module = Module::where('name', $request->module)->first();
        return $module->getfieldsRelation($request);
    }

    public function getFieldsByModuleWithModuleRequested(Request $request)
    {
        $modelRequested = $request->modelRequest;
        $idModelRequest = $request->idModelRequest;
        $resultRequested = $modelRequested::find($idModelRequest);

        $module = Module::where('name', $request->module)->first();
        $fields = $module->getfields();

        $relationTemp = $this->getRelationByModel($request->module);

        foreach ($fields as $key => $field) {
            if (isset($relationTemp[$key])) {
                $val = $resultRequested[$relationTemp[$key]];
                if ($key == $this->getRelationKeyByModel($request->module)) {
                    $val = (string)$val;
                }
                $fields[$key]['value'] = $val;
            }
        }

        $dependCheckboxFields = array_filter($fields, function ($field) {
            return isset($field['depend']) && $field['depend'] == "depend-checkbox";
        });
        $keysNamesFieldsDependCheckbox = array_keys($dependCheckboxFields);

        $fields = $this->setIncludeFalseIfFieldDependCheckIsFalse($keysNamesFieldsDependCheckbox, $resultRequested, $fields);

        return $fields;
    }

    public function isClientCustomServiceModule($module)
    {
        return $module == Module::CLIENT_CUSTOM_SERVICE_MODULE_NAME;
    }

    public function setIncludeFalseIfFieldDependCheckIsFalse($keysNamesFieldsDependCheckbox, $resultRequested, $fields)
    {
        $arrayAttributes = $resultRequested->getAttributes();
        $requestedCheckbox = array_filter($arrayAttributes, function ($key) {
            return substr($key, -7) === "_enable";
        }, ARRAY_FILTER_USE_KEY);

        foreach ($keysNamesFieldsDependCheckbox as $nameField) {
            $checkboxKey = $nameField . '_enable';
            if (array_key_exists($checkboxKey, $requestedCheckbox)) {
                $fields[$nameField]['include'] = $requestedCheckbox[$checkboxKey];
            }
        }

        if (isset($requestedCheckbox['router_id_enable'])) {
            $fields['ip']['include'] = $requestedCheckbox['router_id_enable'];
        }

        return $fields;
    }

    public function isClientInternetModule($module)
    {
        return $module == Module::CLIENT_INTERNET_SERVICE_MODULE_NAME;
    }

    public function getRelationByModel($model)
    {
        if ($model == 'ClientInternetService') {
            return [
                'internet_id' => 'id',
                'description' => 'title',
                'price' => 'price',
                'cost_activation' => 'cost_activation',
                'cost_instalation' => 'cost_instalation'
            ];
        }

        if ($model == 'ClientVozService') {
            return [
                'voz_id' => 'id',
                'description' => 'title',
                'price' => 'price',
                'cost_instalation' => 'cost_instalation'
            ];
        }

        if ($model == 'ClientCustomService') {
            return [
                'custom_id' => 'id',
                'description' => 'title',
                'price' => 'price'
            ];
        }
    }

    public function getRelationKeyByModel($model)
    {
        if ($model == 'ClientInternetService') {
            return 'internet_id';
        }

        if ($model == 'ClientVozService') {
            return 'voz_id';
        }
    }

    public function getFieldsEditedById(Request $request, $id)
    {
        $module = Module::where('name', $request->module)->first();
        return $module->getfields($id);
    }

    public function requestGeneralEditedFields(Request $request)
    {
        $module = Module::where('name', $request->module)->first();
        return $module->getGeneralEditedFields();
    }

    public function getColumnsByModule(Request $request)
    {
        $module = Module::where('name', $request->module)->first();
        return $module->getColumnsDatatable();
    }

    public function getColumnDtExpandByModule(Request $request)
    {
        $module = Module::where('name', $request->module)->first();
        $column_dt = UserColumnDatatableExpand::where('module_id', $module->id)->where('user_id', auth()->user()->id)->first();
        return response()->json(['column' => isset($column_dt) ? $column_dt->column : null]);
    }

    public function setColumnDtExpandByModule(Request $request)
    {
        $module = Module::where('name', $request->module)->first();
        $column_dt = UserColumnDatatableExpand::where('module_id', $module->id)->where('user_id', auth()->user()->id)->first();
        if (isset($column_dt)) {
            if (isset($request->column)) {
                $column_dt->column = $request->column;
            } else {
                $column_dt->remove();
                return null;
            }
        } else {
            $column_dt = new UserColumnDatatableExpand();
            $column_dt->user_id = auth()->user()->id;
            $column_dt->module_id = $module->id;
            $column_dt->column = $request->column;
        }
        $column_dt->save();
        return response()->json(['column' => $column_dt->column]);
    }

    public function getColumnsByModuleExceptColumns(Request $request)
    {
        $module = Module::where('name', $request->module)->first();
        $columns = $module->getColumnsDatatable();
        foreach ($request->columnsExcept as $column) {
            $columns = $columns->reject(function ($item) use ($column) {
                return $item->name === $column;
            });
        }
        return $columns->toArray();
    }

    public function getAllColumnsByModule(Request $request)
    {
        $cacheKey = 'datatable_columns_all' . $request->module;
        // Verificar si existe en caché primero
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        $module = Module::where('name', $request->module)->first();
        $data = $module->getColumnsDatatable(true);
        Cache::put($cacheKey, $data, now()->addHours(24));
        return $data;
    }

    public function getRandomPassword()
    {
        return Encryption::randomPassword();
    }

    public function saveRandomPassword(Request $request)
    {
        $request->validate([
            'module' => 'required',
            'id' => 'required',
            'field' => 'required',
            'val' => 'required'
        ]);

        $module = Module::where('name', $request->module)->first();
        return $module->saveRandomPasswordByIdAndField($request->id, $request->field, $request->val);
    }

    public function getRandomUser()
    {
        return Encryption::randomUser();
    }

    public function getGenerateUser($clientId = null, $newUser = null)
    {
        $preposition = 'Meganet';

        $userNameWithPreposition = ClientMainInformation::where('client_id', $clientId)->first();
        if (!$this->rutasForCreateClient(request()->location) && $userNameWithPreposition && !$newUser) {
            return $userNameWithPreposition->user;
        }

        if ($this->rutasForCreateClient(request()->location) || $newUser) {
            $preposition = 'Meganet';
            $existingNumbers = ClientMainInformation::pluck('user')->toArray();

            do {
                $randomNumber = mt_rand(10000, 99999); // Genera un número aleatorio de 5 dígitos
                $timestamp = time(); // Obtiene la marca de tiempo actual
                $combinedValue = $preposition . $randomNumber . $timestamp;
                $hashedValue = sha1($combinedValue); // Aplica una función de hash (SHA-1) al valor combinado

                $newUser = $preposition . substr($hashedValue, 0, 8); // Utiliza solo los primeros 8 caracteres del hash como número de usuario
            } while (in_array($newUser, $existingNumbers));

            return $newUser;
        }
        return $preposition . '1000001';
    }

    public function getGenerateUserExist()
    {
        $preposition = 'Meganet';
        $existingNumbers = ClientMainInformation::pluck('user')->toArray();
        do {
            $randomNumber = mt_rand(10000, 99999); // Genera un número aleatorio de 5 dígitos
            $timestamp = time(); // Obtiene la marca de tiempo actual
            $combinedValue = $preposition . $randomNumber . $timestamp;
            $hashedValue = sha1($combinedValue); // Aplica una función de hash (SHA-1) al valor combinado

            $newUser = $preposition . substr($hashedValue, 0, 8); // Utiliza solo los primeros 8 caracteres del hash como número de usuario
        } while (in_array($newUser, $existingNumbers));

        return $newUser;
    }

    public function getData(Request $request, $module)
    {
        $modules = [
            'Partner' => 'getPartnerInfo',
            'Location' => 'getLocationInfo'
        ];

        if (isset($modules[$module])) {
            $function = $modules[$module];
            return $this->$function($request->id);
        }
        return null;
    }

    public function getPartnerInfo($id)
    {
        return Partner::where('id', $id)->with('internet', 'voz', 'router')->first()->toArray();
    }

    public function getLocationInfo($id)
    {
        return Location::where('id', $id)->with('router')->first()->toArray();
    }

    public function getUserAuthenticated()
    {
        return $this->userAutenticated()->id;
    }

    public function updateColumnsByUser(Request $request)
    {
        if (isset($request->module) || isset($request->module_datatable)) {
            $module = Module::where('name', $request->module)->first() ?? Module::where('name', $request->module_datatable)->first();
            $modifiedInput = [];
            if ($module) {
                $input = $request->except(['module']);
                foreach ($input as $key => $value) {
                    $newKey = explode('/', $key);
                    $modifiedInput[$newKey[0]] = $value;
                }

                foreach ($module->columnsDatatable()->get() as $item) {
                    $column = $item->name;
                    if (!($modifiedInput[$column] ?? false)) {
                        if (
                            !($item->user_column_datatable_module()
                                ->where('user_id', $this->getUserAuthenticated())
                                ->first())
                        ) {
                            $item->user_column_datatable_module()->create([
                                'user_id' => $this->getUserAuthenticated(),
                                'active' => false
                            ]);
                        }
                    } else {
                        $item->user_column_datatable_module()->where('user_id', $this->getUserAuthenticated())->delete();
                    }
                }
            }
            return response()->json(['columns' => $modifiedInput]);
        }

        return false;
    }


    public function getCrmClientIfExist(Request $request)
    {
        $input = $request->all();
        $column = [
            "name",
            "father_last_name",
            "mother_last_name",
            "email",
            "phone",
            "phone2",
            "nif_pasaport",
            "user",
        ];

        if ($this->requestNotEmpty($input, $column)) {
            $crm = Crm::with('crm_main_information')->whereHas('crm_main_information', function ($query) use ($column, $input) {
                $column = array_diff($column, ["user"]);

                // Verificar si hay alguna condición de búsqueda
                $conditionsExist = false;

                if ($input) {
                    $query->where(function ($query) use ($input) {
                        if (isset($input['json']['name']) && isset($input['json']['father_last_name'])) {
                            $name = $input['json']['name'];
                            $father_last_name = $input['json']['father_last_name'];
                            $query
                                ->where('crm_main_information.name', 'like', '%' . $name . '%')
                                ->where('crm_main_information.father_last_name', 'like', '%' . $father_last_name . '%');
                        }
                    });
                    $query->where(function ($query) use ($column, $input, &$conditionsExist) {
                        foreach ($column as $value) {
                            if (isset($input['json'][$value])) {
                                $inputValue = $input['json'][$value];
                                if ($inputValue !== '' && $inputValue !== null) {
                                    $conditionsExist = true;
                                    if (in_array($value, ['phone', 'phone2'])) {
                                        // Crear fragmentos de las columnas phone y phone2
                                        $phoneFragment = DB::raw('RIGHT(phone, 6)');
                                        $phone2Fragment = DB::raw('RIGHT(phone2, 6)');
                                        $query->orWhere(function ($query) use ($inputValue, $phoneFragment, $phone2Fragment) {
                                            $query->where($phoneFragment, '=', substr($inputValue, -6))
                                                ->orWhere($phone2Fragment, '=', substr($inputValue, -6));
                                        });
                                    } else {
                                        // Otras condiciones
                                        $query->orWhere($value, '=', $inputValue);
                                    }
                                }
                            }
                        }
                    });
                }

                // Si no existen condiciones, devolver una consulta vacía
                if (!$conditionsExist) {
                    $query->whereRaw('1 = 0');
                }
            })->get();

            $client = Client::with('client_main_information')->whereHas('client_main_information', function ($query) use ($column, $input) {
                // Inicializar la bandera para controlar si hay condiciones
                $conditionsExist = false;

                if ($input) {
                    $query->where(function ($query) use ($input) {
                        if (isset($input['json']['name']) && isset($input['json']['father_last_name'])) {
                            $name = $input['json']['name'];
                            $father_last_name = $input['json']['father_last_name'];
                            $query
                                ->where('client_main_information.name', 'like', '%' . $name . '%')
                                ->where('client_main_information.father_last_name', 'like', '%' . $father_last_name . '%');
                        }
                    });
                    $query->where(function ($query) use ($column, $input, &$conditionsExist) {
                        foreach ($column as $value) {
                            if (isset($input['json'][$value])) {
                                $inputValue = $input['json'][$value];
                                if ($inputValue !== '' && $inputValue !== null) {
                                    $conditionsExist = true;
                                    if (in_array($value, ['phone', 'phone2'])) {
                                        // Crear fragmentos de las columnas phone y phone2
                                        $phoneFragment = DB::raw('RIGHT(phone, 6)');
                                        $phone2Fragment = DB::raw('RIGHT(phone2, 6)');
                                        $query->orWhere(function ($query) use ($inputValue, $phoneFragment, $phone2Fragment) {
                                            $query->where($phoneFragment, '=', substr($inputValue, -6))
                                                ->orWhere($phone2Fragment, '=', substr($inputValue, -6));
                                        });
                                    } else {
                                        // Otras condiciones
                                        $query->orWhere($value, '=', $inputValue);
                                    }
                                }
                            }
                        }
                    });
                }

                // Si no hay condiciones, evitar que se devuelvan registros
                if (!$conditionsExist) {
                    $query->whereRaw('1 = 0');
                }
            })->get();

            $crm = collect($crm)->toArray();
            $client = collect($client)->toArray();

            return array_merge($crm, $client);
        }
        return null;
    }

    public function requestNotEmpty($input, $columns)
    {
        foreach ($columns as $column) {
            if (isset($input['json'][$column]) && $input['json'][$column] != null) {
                return true;
            }
        }
        return false;
    }

    private function rutasForCreateClient($location)
    {
        return Str::contains($location, ['view-of-convert-crm-to-client', 'cliente/crear']);
    }

    public function getOptionsTeam()
    {
        $teamRepository = new TeamRepository();
        return $teamRepository->getTeamsWithUsersGroup();
    }
}
