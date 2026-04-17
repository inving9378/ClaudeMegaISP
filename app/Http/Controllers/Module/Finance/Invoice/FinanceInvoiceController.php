<?php

namespace App\Http\Controllers\Module\Finance\Invoice;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\finance\FinanceInvoiceDatatableHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FinanceInvoiceController extends Controller
{

    private $helper;
    public function __construct(FinanceInvoiceDatatableHelper $helper)
    {
        $model = 'Invoice';
        $this->data['url'] = 'meganet.module.finance.invoice';
        $this->data['module'] = 'Invoice';
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
        $this->includeLibraryDinamic('App\Models\FinanceInvoice');
        return view($this->data['url'] . '.index',$this->data);
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request, $this->data['model']);
    }
}
