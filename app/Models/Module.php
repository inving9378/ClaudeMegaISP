<?php

namespace App\Models;

use App\Http\Repository\DefaultValueRepository;
use App\Http\Repository\FieldTypeRepository;
use App\Http\Repository\ModuleRepository;
use App\Http\Traits\IncludeFieldsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends BaseModel
{
    use HasFactory, IncludeFieldsTrait;

    // TODO eliminar constantes de module id
    const CLIENT_MAIN_INFORMATION_MODULE_ID = 13;
    const CLIENT_ADDITIONAL_INFORMATION_MODULE_ID = 14;
    const CLIENT_INTERNET_SERVICE_MODULE_ID = 17;
    const CRM_MAIN_INFORMATION_MODULE_ID = 9;
    const NETWORK_MODULE_ID = 32;
    const NETWORK_EDIT_MODULE_ID = 31;

    //Modules
    const PACKAGE_MODULE_NAME = 'Package';
    const CRM_MODULE_NAME = 'Crm';
    const CLIENT_BUNDLE_SERVICE_MODULE_NAME = 'ClientBundleService';
    const CLIENT_INTERNET_SERVICE_MODULE_NAME = 'ClientInternetService';
    const CLIENT_MODULE_NAME = 'Client';
    const CLIENT_MAIN_INFORMATION_MODULE_NAME = 'ClientMainInformation';
    const CLIENT_ADDITIONAL_INFORMATION_MODULE_NAME = 'ClientAdditionalInformation';
    const CRM_MAIN_INFORMATION_MODULE_NAME = 'CrmMainInformation';
    const CRM_LEAD_INFORMATION_MODULE_NAME = 'CrmLeadInformation';
    const COMMAND_CONFIG_MODULE_NAME = 'CommandConfig';
    const CLIENT_CUSTOM_SERVICE_MODULE_NAME = 'ClientCustomService';
    const FIELD_MODULE_MODULE_NAME = 'FieldModule';
    const CUSTOM_MODULE_NAME = 'Custom';
    const CLIENT_BILLING_CONFIGURATION_RECURRENT = 'ClientBillingConfigurationRecurrent';
    const CLIENT_BILLING_CONFIGURATION_CUSTOM = 'ClientBillingConfigurationCustom';
    const SETTING_TOOLS_IMPORT = 'SettingToolsImport';
    const BUNDLE_SERVICE_MODULE_NAME = 'Bundle';
    const STATE_MODULE_NAME = "State";
    const MUNICIPALITY_MODULE_NAME = "Municipality";
    const COLONY_MODULE_NAME = "Colony";

    const FINANCE_PAYMENT_MODULE_NAME = "FinancePayment";


    const PLAN_INTERNET_MODULE_NAME = "Internet";
    const PLAN_VOZ_MODULE_NAME = "Voise";


    const CLIENT_PAYMENT_MODULE_NAME = 'ClientPayment';
    const CLIENT_TRANSACTION_MODULE_NAME = 'ClientTransaction';
    const CLIENT_INVOICE_MODULE_NAME = "ClientInvoice";

    const CLIENT_VOZ_SERVICE_MODULE_NAME = 'ClientVozService';
    const NETWORK_MODULE_NAME = 'Network';
    const NETWORK_IP_MODULE_NAME = 'NetworkIp';


    //
    const MODULES_TO_SELECT_TO_SETTING_IMPORT = [
        'Package',
    ];


    protected $guarded = [];

    public function packages()
    {
        return $this->morphToMany(
            Package::class,
            'crud_package',
            'crud_packages'
        )->withTimestamps();
    }

    public function fields()
    {
        return $this->hasMany(FieldModule::class)->orderBy('position');
    }

    public function columnsDatatable()
    {
        return $this->hasMany(ColumnDatatableModule::class);
    }

    public function getfields($id = null)
    {
        $fields = [];
        foreach ($this->fields()->get() as $field) {
            $fields[$field->name] = $this->transformToFieldsModel($field);
        }

        if ($id) {
            $fields = $this->assignValue($fields, $id);
        }
        return $fields;
    }

    public function getGeneralEditedFields()
    {
        $fields = [];
        foreach ($this->fields()->get() as $field) {
            $fields[$field->name] = $this->transformToFieldsModel($field);
        }
        return $this->assignValue($fields, 1);
    }

    public function getfieldsRelation($request)
    {
        $fields = [];
        foreach ($this->fields()->get() as $field) {
            $fields[$field->name] = $this->transformToFieldsModel($field);
        }

        $parent_module = 'App\Models\\' . $request->parent_module;
        $id = $request->id;
        $relation = $request->relation;
        $model = $parent_module::find($id);
        $result = $model->$relation;

        if ($result) return $this->includeFields('App\Models\\' . $this->name, $fields, $result);
        return $fields;
    }

    public function getColumnsDatatable($isAll = false, $columnsFiltered = null)
    {
        $query = $this->columnsDatatable()
            ->orderBy('order');

        if ($isAll) {
            return $query->with('user_column_datatable_module')->get();
        }

        if (auth()->check()) {
            $query->whereDoesntHave('user_column_datatable_module', function ($query) {
                $query->where('user_id', auth()->user()->id);
            });
        }

        // Aplicar el filtro de columnas directamente en la consulta
        if (!empty($columnsFiltered)) {
            $query->whereIn('name', $columnsFiltered);
        }

        $columns = $query->get();

        // Si no hay columnas para el usuario autenticado, obtener todas (sin el filtro de usuario)
        if ($columns->isEmpty() && auth()->check()) {
            $query = $this->columnsDatatable()
                ->orderBy('order');

            if (!empty($columnsFiltered)) {
                $query->whereIn('name', $columnsFiltered);
            }

            $columns = $query->get();
        }

        return $columns;
    }


    public function transformToFieldsModel($field)
    {
        return [
            'field' => $field->name,
            'type' => $this->getTypeField($field->type),
            'label' => $field->label,
            'placeholder' => $field->placeholder,
            'partition' => $field->partition,
            'include' => (bool)$field->include,
            'hint' => $field->hint,
            'search' => [
                'model' => $field->search ? (json_decode($field->search)->model ?? null) : null,
                'id' => $field->search ? (json_decode($field->search)->id ?? null) : null,
                'text' => $field->search ? (json_decode($field->search)->text ?? null) : null,
                'filter' => $field->search ? (json_decode($field->search)->filter ?? null) : null,
                'scope' => $field->search ? (json_decode($field->search)->scope ?? null) : null,
                'append' => $field->search ? (json_decode($field->search)->append ?? null) : null,

            ],
            'options' => $field->search ? null : json_decode($field->options),
            'inputGroup' => $field->inputGroup,
            'inputGroupEnd' => $field->inputGroupEnd,
            'depend' => $field->depend,
            'inputs_depend' => json_decode($field->inputs_depend),
            'value' => $field->value ? $this->isJson($field->value) ? json_decode($field->value) : $field->value : null,
            'default_value' =>  $this->getDefultValueForThisUserIfExist($field),
            'disabled' => $field->disabled,
            'position' => $field->position,
            'rule' => $field->rule,
            'module_id' => $field->module_id,
            'step' => $field->step,
            'checked' => $this->getIsCheck($field),
            'additional_field' => $field->additional_field,
            'class_col' => $field->class_col,
            'class_field' => $field->class_field,
            'class_label' => $field->class_label,
        ];
    }



    public function getTypeField($id)
    {
        $fieldTypeRepository = new FieldTypeRepository();
        return $fieldTypeRepository->getNameById($id);
    }


    public function getIsCheck($field)
    {
        $defalutVal = null;
        $defalutValueRepository = new DefaultValueRepository();
        $moduleId = $field->module_id;
        $fieldName = $field->name;
        $register = $defalutValueRepository->getDefaultValueFilteredByModuleIdAndField($moduleId, $fieldName);
        if ($register) {
            return true;
        } elseif (isset($field->default_value)) {
            return true;
        }
        return false;
    }

    public function getDefultValueForThisUserIfExist($field)
    {
        $defalutVal = null;
        $defalutValueRepository = new DefaultValueRepository();
        $moduleId = $field->module_id;
        $fieldName = $field->name;
        $register = $defalutValueRepository->getDefaultValueFilteredByModuleIdAndField($moduleId, $fieldName);
        if ($register) {
            $defalutVal = $register->value;
        } elseif (isset($field->default_value)) {
            $defalutVal = $this->isJson($field->default_value)
                ? json_decode($field->default_value)
                : $field->default_value;
        }
        return $defalutVal;
    }

    private function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public function assignValue($fields, $id)
    {
        $model = $this->getNameModel();
        $result = $model::find($id);
        if ($result) return $this->includeFields($model, $fields, $result);
        return $fields;
    }

    public function getNameModel()
    {
        return $this->is_main ? 'App\Models\\' . $this->name : $this->main;
    }
}
