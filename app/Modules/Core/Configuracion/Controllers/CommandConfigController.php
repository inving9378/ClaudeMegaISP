<?php

namespace App\Modules\Core\Configuracion\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Configuracion\Repositories\CommandConfigRepository;
use App\Modules\Core\Configuracion\Repositories\FrequencyCommandRepository;
use App\Http\Requests\module\setting\command_config\CommandConfigUpdateRequest;
use Illuminate\Http\Request;


class CommandConfigController extends Controller
{
    public function __construct()
    {
        $model = 'CommandConfig';
        $this->data['url'] = 'core-configuracion::commandconfig';
        $this->data['title'] = 'Configuración de comandos';
        $this->data['model'] = 'App\Modules\Core\Configuracion\Models\\' . $model;
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
