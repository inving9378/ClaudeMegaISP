<?php


namespace App\Http\Traits\Models\Crm;

use App\Http\Controllers\HelperController;
use App\Http\Repository\ModuleRepository;
use App\Models\Module;

trait CrmTrait
{
    public function createMainInformation($request)
    {
        $moduleRepository = new ModuleRepository();
        $module = $moduleRepository->getModuleByName(Module::CRM_MAIN_INFORMATION_MODULE_NAME);
        $key = $module->fields()->pluck('name')->toArray();
        $input = $request->all();
        $util = new HelperController();
        $input["user"] = $util->getGenerateUser('user');
        $this->crm_main_information()->create(\Illuminate\Support\Arr::only($input, $key));
        return $this;
    }

    public function createLeadInformation($request)
    {
        $moduleRepository = new ModuleRepository();
        $module = $moduleRepository->getModuleByName(Module::CRM_LEAD_INFORMATION_MODULE_NAME);
        $key = $module->fields()->pluck('name')->toArray();
        $this->crm_lead_information()->create(\Illuminate\Support\Arr::only($request->all(), $key));
        return $this;
    }


}
