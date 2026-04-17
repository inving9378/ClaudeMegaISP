<?php


namespace App\Http\Controllers\Module\Client;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\client\ClientTransactionDatatableHelper;
use App\Http\Repository\ClientRepository;
use App\Http\Requests\module\client\ClientTransactionRequest;
use App\Models\Client;
use App\Models\Transaction;
use App\MyLibrary\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientTransactionController extends Controller
{
    private $helper;

    public function __construct(ClientTransactionDatatableHelper $helper)
    {
        $this->helper = $helper;
        $this->data['model'] = 'App\Models\Transaction';
        $this->data['module'] = 'Transaction';

    }

    public function store(ClientTransactionRequest $request, $id)
    {
        $clientRepository = new ClientRepository();
        $client = $clientRepository->getClientById($id);
        if($client){
            return $client->clientCreateTransaction($request);
        }
        return null;
    }

    public function update(Request $request, $id)
    {
        $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        $model = $this->data['model']::find($id);
        $model->updateFileUpload($request->file('file'));

        $request = collect($request->except('file'));
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();

        $input = Utility::modifyValueForCheckbox($input, 'ClientTransaction');
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');

        return $model->update($input);
    }

    public function destroy($id)
    {
        Transaction::findOrFail($id)->delete();
        return redirect()->back()->with('message', 'Transaccion Eliminada Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function getTotals($clientId)
    {
        $client = Client::where('id', $clientId)->first();
        $transactionsForClient = $client->transactions()->get();
        $credit = $transactionsForClient->where('type','credit')->sum('credit');
        $debit = $transactionsForClient->where('type','debit')->sum('debit');

        return[
            'debit' => $debit,
            'credit' => $credit,
            'total' => $credit + $debit
        ];
    }
}
