<?php

namespace App\Http\Controllers\Module\Finance\Transaction;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\finance\FinanceTransactionDatatableHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FinanceTransactionController extends Controller
{

    private $helper;
    public function __construct(FinanceTransactionDatatableHelper $helper)
    {
        $model = 'Transaction';
        $this->data['url'] = 'meganet.module.finance.' . Str::lower($model);
        $this->data['module'] = 'FinanceTransaction';
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
        $this->includeLibraryDinamic('App\Models\FinanceTransaction');
        return view($this->data['url'] . '.index',$this->data);
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }
}
