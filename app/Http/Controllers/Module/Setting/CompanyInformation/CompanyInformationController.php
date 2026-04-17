<?php

namespace App\Http\Controllers\Module\Setting\CompanyInformation;

use App\Http\Controllers\Controller;
use App\Http\Repository\CommandConfigRepository;
use App\Http\Repository\FrequencyCommandRepository;
use App\Http\Repository\ModuleRepository;
use App\Http\Requests\module\setting\command_config\CommandConfigUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class CompanyInformationController extends Controller
{
    public function __construct()
    {
        $model = 'CompanyInformation';
        $this->data['url'] = 'meganet.module.setting.companyinformation';
        $this->data['title'] = 'Información de la empresa';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['module'] = 'CompanyInformation';
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic('CompanyInformation');
        return view($this->data['url'] . '.index', $this->data);
    }

    public function getModuleIdByName()
    {
        $moduleRepository = new ModuleRepository();
        return $moduleRepository->getModuleByName('CompanyInformation')->id;
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

    public function update(Request $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $model = $this->data['model']::find($id);
        $input = $request->all();
        if ($model->logo != $request->logo) {
            $logo = $this->saveLogo($request->logo);
            $input['logo'] = $logo['name'];
            $input['url_logo'] = $logo['url_logo'];
        }

        return $model->update($input);
    }

    public function saveLogo($file)
    {
        if (!$file) {
            return [
                'name' => null,
                'url_logo' => null
            ];
        }

        $extension = $file->getClientOriginalExtension();
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $name_to_save = $name . '.' . $extension;

        // Guardar en storage/app/public/logo_meganet
        $path = $file->storeAs('logo_meganet', $name_to_save, 'public');

        return [
            'name' => $name_to_save,
            'url_logo' => Storage::url($path) // Devuelve "/storage/logo_meganet/nombre.ext"
        ];
    }

    public function destroy($id) {}

    public function getDataCompany()
    {
        $data = $this->data['model']::first();
        return $data;
    }
}
