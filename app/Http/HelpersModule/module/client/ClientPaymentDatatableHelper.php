<?php


namespace App\Http\HelpersModule\module\client;

use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\ClientMainInformation;
use App\Models\Module;
use App\Models\Payment;
use App\Models\User;
use App\Services\FormatDateService;
use Illuminate\Support\Arr;

class ClientPaymentDatatableHelper extends HelperDatatable
{
    public $model;
    public $columns;
    public function __construct()
    {
        $this->model = Payment::class;
        $moduleName = 'ClientPayment';
        $this->columns = Module::where('name', $moduleName)->first()->columnsDatatable->pluck('name')->toArray();
    }

    public function count($idModule)
    {
        return $this->model::where('paymentable_id', $idModule)
            ->count();
    }

    public function ordering_query($start, $limit, $order, $dir, $idModule)
    {
        return $this->model::select('payments.id', 'payments.date', 'method_of_payments.type as payment_method_id', 'payments.amount', 'payments.payment_period', 'payments.comment')
            ->with('file')
            ->leftJoin('method_of_payments', 'payments.payment_method_id', '=', 'method_of_payments.id')
            ->where('paymentable_id', $idModule)
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $idModule)
    {
        return $this->model::filters($this->columns, $search)
            ->select('payments.id', 'payments.date', 'method_of_payments.type as payment_method_id', 'payments.amount', 'payments.payment_period', 'payments.comment')
            ->with('file')
            ->leftJoin('method_of_payments', 'payments.payment_method_id', '=', 'method_of_payments.id')
            ->where('payments.paymentable_id', $idModule)  // Especificar la tabla aquí
            ->offset($start)
            ->limit($limit)
            ->orderBy('payments.' . $order, $dir)  // Especificar la tabla aquí
            ->get();
    }

    public function filtering_query($search, $idModule)
    {
        return $this->model::filters($this->columns, $search)
            ->leftJoin('method_of_payments', 'payments.payment_method_id', '=', 'method_of_payments.id')
            ->where('paymentable_id', $idModule)
            ->count();
    }

    public function transform($request)
    {
        $data = array();

        $type_modal_edit = $this->includeButtonEditTypeModalIfIsRequested($request)
            ? '_type_modal' : '';

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    if ($val == 'add_by') {
                        $value->add_by = $this->getNameAddBy($id);
                    }
                    if ($val == 'date') {
                        $value->date = (new FormatDateService($value->date))->formatDate();
                    }

                     if ($val == 'payment_period') {
                        $value->payment_period = $value->getPaymentPeriodByInvoice();
                    }


                    $nestedData[$val] = $value->$val;
                }

                $document = null;
                if ($value->file) {
                    $document = url($value->file->path);
                }

                $document_slip = null;

                if ($value->id) {
                    $document_slip = url('/cliente/billing/payment/pdf/' . $value->id);
                }

                $info = [
                    'id' => $id,
                    'module' => 'ClientPayment',
                    'group' => 'client_payroll_payment',
                    'submodule' => 'client',
                    'document' => $document,
                    'documentslip' => $document_slip
                ];

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                $nestedData['action'] = view('meganet.shared.table.actions' . $type_modal_edit, $info)->toHtml();
                $data[] = $nestedData;
            }
        }

        return [
            "draw" => intval($request['request']->input('draw')),
            "recordsTotal" => intval($request['totalData']),
            "recordsFiltered" => intval($request['totalFiltered']),
            "data" => $data
        ];
    }

    public function getNameAddBy($id)
    {
        $addById = $this->model::find($id)->add_by;
        $user = User::findOrFail($addById);
        if ($user) {
            return $user->getClientNameWithFathersNamesAttribute();
        }
        return '';
    }

    public function includeButtonEditTypeModalIfIsRequested($request)
    {
        return isset($request['request']->modal);
    }
}
