<?php

namespace App\Http\Controllers\Module\Setting;

use App\Http\Controllers\Controller;
use App\Models\SettingDebtPaymentClientRecurrent;
use Illuminate\Http\Request;


class SettingController extends Controller
{
    public function __construct()
    {
        $this->data['url'] = 'meganet.module.setting';
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic('SettingDebtPaymentClientRecurrent');
        return view($this->data['url'] . '.index',$this->data);
    }

    public function debtPaymentClientRecurrent(Request $request){
        $setting = SettingDebtPaymentClientRecurrent::first();
        return $setting ? $setting->update($request->all()) :
            SettingDebtPaymentClientRecurrent::create($request->all());
    }
}
