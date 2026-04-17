<?php

namespace App\Http\Controllers\Module\Setting\EmailSetting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailSettingController extends Controller
{
    public function __construct()
    {
        $model = 'EmailSetting';
        $this->data['url'] = 'meganet.module.setting.email_setting';
        $this->data['title'] = 'Configuración de correo';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['module'] = 'EmailSetting';
    }


    public function edit($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $this->data['id'] = $id;

        return view($this->data['url'] . '.edit', $this->data);
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'limit_per_hour' => [
                'required',
                'integer',
                'max:1000',
            ],
        ]);
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $model = $this->data['model']::find($id);
        $input = $request->all();
        return $model->update($input);
    }
}
