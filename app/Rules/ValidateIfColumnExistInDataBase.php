<?php

namespace App\Rules;

use App\Http\Repository\ModuleRepository;
use App\Models\NetworkIp;
use App\Services\FieldModuleService;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class ValidateIfColumnExistInDataBase implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */

    protected $request;
    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $fieldModuleService = new FieldModuleService();

        $moduleID = $this->request->input('module_id');
        $moduleRepository = new ModuleRepository();
        $module = $moduleRepository->getModuleById($moduleID);
        $model = $module->getNameModel();
        $nameColumn = strtolower(str_replace(" ", "_", $value));
        $nameTable = $fieldModuleService->getTableForThisModel($model, 'create');
        
        if (is_array($nameTable)) {
            foreach ($nameTable as $table) {
                if (Schema::hasColumn($table, $nameColumn)) {
                    return false;
                }
                return true;
            }
        }

        if (Schema::hasColumn($nameTable, $nameColumn)) {
            return false;
        }
        return true;
    }

    public function message()
    {
        return 'Este campo o columna ya existe para este modulo';
    }
}
