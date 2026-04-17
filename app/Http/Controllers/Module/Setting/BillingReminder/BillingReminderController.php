<?php

namespace App\Http\Controllers\Module\Setting\BillingReminder;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


class BillingReminderController extends Controller
{
    public function __construct()
    {
        $model = 'BillingReminder';
        $this->data['url'] = 'meganet.module.setting.billing_reminder';
        $this->data['title'] = 'Configuración de recordatorios';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['module'] = 'BillingReminder';
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
        $this->validateFieldByRulesInTableFiledModules($this->data['module'], $request);
        $model = $this->data['model']::find($id);
        $input = $request->all();
        return $model->update($input);
    }
}
