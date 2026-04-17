<?php

namespace App\Http\Controllers\Module\Setting;

use App\Http\Controllers\Controller;
use App\Http\Repository\CommandConfigRepository;
use App\Http\Repository\FrequencyCommandRepository;
use App\Http\Requests\module\setting\command_config\CommandConfigUpdateRequest;
use Illuminate\Http\Request;


class CommandConfigController extends Controller
{
    public function __construct()
    {
        $model = 'CommandConfig';
        $this->data['url'] = 'meganet.module.setting.commandconfig';
        $this->data['title'] = 'Configuración de comandos';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['module'] = 'CommandConfig';
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic('CommandConfig');

        $commandConfigRepository = new CommandConfigRepository();
        $frequencyCommandRepository = new FrequencyCommandRepository();
        $this->data['commands'] = $commandConfigRepository->all();
        $this->data['frequency_has_time'] = $frequencyCommandRepository->getArrayAllIdHasTimeFrequencies();

        return view($this->data['url'] . '.index', $this->data);
    }

    public function update(CommandConfigUpdateRequest $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        $model = $this->data['model']::find($id);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');

        return $model->update($input);
    }
}
