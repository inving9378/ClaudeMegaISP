<?php


namespace App\Http\HelpersModule\module\finance;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\Module;
use App\Models\Payment;
use App\Http\HelpersModule\module\HelperDatatable;
use App\Models\ClientMainInformation;
use App\Models\User;
use App\Services\FormatDateService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Symfony\Component\Uid\NilUlid;

class FinancePaymentDatatableHelper extends HelperDatatable
{
    private $model, $columns;


    public function __construct()
    {
        $this->model = Payment::class;
        $moduleName = 'FinancePayment';
        $this->columns = Module::where('name', $moduleName)->first()
            ->columnsDatatable->where('name', '!=', 'action')->pluck('name')->toArray();
    }

    public function count($filters = null, $search = null)
    {
        if (!empty($filters)) {
            $query = $this->queryCustomFilter($this->model, $filters, $search);
            $count = $query->count();
            return $count;
        }
        return $this->model::count();
    }



    public function ordering_query($start, $limit, $order, $dir, $filters)
    {
        $columnsSelected = $this->columnsSelected();

        $query = $this->model::select(...$columnsSelected)
            ->leftJoin('client_main_information', 'payments.paymentable_id', '=', 'client_main_information.id')
            ->leftJoin('method_of_payments', 'payments.payment_method_id', '=', 'method_of_payments.id')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);

        if (!empty($filters)) {
            $query = $query->filters($this->columns, null, $filters);
        }

        return $query->get();
    }

    public function columnsSelected()
    {
        return [
            'payments.id',
            'payments.date',
            'method_of_payments.type as payment_method_id',
            'payments.amount',
            'payments.payment_period',
            'payments.comment',
            'payments.paymentable_id'
        ];
    }

    public function searching_query($start, $limit, $order, $dir, $search, $filters = null, $columns = null)
    {
        $columnsSelected = $this->columnsSelected();
        $query = $this->model::filters($this->columns, $search, $filters)
            ->select(...$columnsSelected)
            ->leftJoin('client_main_information', 'payments.paymentable_id', '=', 'client_main_information.client_id')
            ->leftJoin('method_of_payments', 'payments.payment_method_id', '=', 'method_of_payments.id')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);
        return $query->get();
    }

    public function filtering_query($search)
    {
        return $this->model::filters($this->columns, $search)
            ->count();
    }

    public function queryCustomFilter($model, $filters)
    {
        $query = $model::query();
        $query->filters($this->columns, null, $filters);

        return $query;
    }

    public function transform($request)
    {
        $data = array();

        $type_modal_edit = '';

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    if ($val == 'date') {
                        $value->date = (new FormatDateService($value->date))->formatDate();
                    }

                    if ($val == 'add_by') {
                        $value->add_by = $this->getNameAddBy($id);
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
                    'module' => 'FinancePayment',
                    'group' => 'finance',
                    'submodule' => 'FinancePayment',
                    'document' => $document,
                    'documentslip' => $document_slip
                ];
                
                $nestedData['created_at'] = view('meganet.shared.table.column_timestamp', [
                    'value' => $value,
                    'column' => 'created_at',
                ])->toHtml();

                $info = Arr::add($info, 'modal', 'modalpayment');
                $nestedData['action'] = view('meganet.shared.table.actions_type_modal', $info)->toHtml();

                $nestedData['paymentable_id'] = view('meganet.shared.table.module.finance_payment.paymentable_id', [
                    'dir' => '/cliente/editar/' . $value->paymentable_id,
                    'name' => $value->paymentable_name,
                ])->toHtml();

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

    public function objectToArray($object)
    {
        return collect($object)->map(function ($item, $key) {
            $result = [];
            foreach ($item as $type => $values) {
                $result[$type] = [];
                foreach ($values as $key => $val) {
                    $result[$type][$key] = $val;
                }
            }
            return $result;
        })->toArray();
    }
    public function isNotAdmin($column)
    {
        return $column->name != 'Administrador';
    }

    public function transformFilter($object)
    {
        if (is_null($object) || (is_array($object) && in_array(null, $object, true))) {
            return [];
        }
        return collect($object)->map(function ($item, $key) {
            $result = [];
            foreach ($item as $type => $values) {
                $result[$type] = $values;
            }
            return $result;
        })->toArray();
    }
}
