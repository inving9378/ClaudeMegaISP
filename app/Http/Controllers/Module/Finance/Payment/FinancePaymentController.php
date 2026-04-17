<?php

namespace App\Http\Controllers\Module\Finance\Payment;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\finance\FinancePaymentDatatableHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FinancePaymentController extends Controller
{

    private $helper;
    public function __construct(FinancePaymentDatatableHelper $helper)
    {
        $model = 'Payment';
        $this->data['url'] = 'meganet.module.finance.payment';
        $this->data['module'] = 'FinancePayment';
        $this->data['model'] = 'App\Models\\'.$model;
        $this->data['group'] = 'finance';
        $this->helper = $helper;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic('App\Models\FinancePayment');
        return view($this->data['url'] . '.index',$this->data);
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }
}
