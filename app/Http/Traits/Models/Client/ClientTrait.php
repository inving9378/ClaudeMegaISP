<?php


namespace App\Http\Traits\Models\Client;

use App\Http\Controllers\FileController;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Controllers\Utils\ReceiptController;
use App\Http\Repository\ClientMainInformationRepository;
use App\Models\ClientMainInformation;
use App\Models\ClientUser;
use App\Models\Payment;
use App\Models\ClientInvoice;
use App\Models\MethodOfPayment;
use App\Models\Module;
use App\MyLibrary\Utility;
use App\Services\Finance\Invoice\InvoiceService;
use App\Services\ImportdDBService;
use App\Services\LogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


trait ClientTrait
{
    public function clientCreateClientMainInformation($request)
    {
        $module = Module::where('name', Module::CLIENT_MAIN_INFORMATION_MODULE_NAME)->first();
        $key = $module->fields()->pluck('name')->toArray();

        $input = $request->except('import');

        if ($request->import) {
            $newImportDbService = new ImportdDBService();
            $input = $newImportDbService->processInputImportByModule($input, $module);
        }
        $this->client_main_information()->create(\Illuminate\Support\Arr::only($input, $key));
        return $this;
    }

    /**
     * @param $request
     * @return $this
     */
    public function clientCreateClientAdditionalInformation($request)
    {
        $module = Module::where('name', Module::CLIENT_ADDITIONAL_INFORMATION_MODULE_NAME)->first();
        $key = $module->fields()->pluck('name')->toArray();
        $input = $request->except('user', 'import');
        if ($request->import) {
            $newImportDbService = new ImportdDBService();
            $input = $newImportDbService->processInputImportByModule($input, $module);
        }
        $this->client_additional_information()->create(\Illuminate\Support\Arr::only($input, $key));
        return $this;
    }

    public function clientCreateClientUser($clientId)
    {
        $clientMainInformationRepository = new ClientMainInformationRepository();
        $clientMainInformation = $clientMainInformationRepository->getClientMainInformationByClientId($clientId);
        ClientUser::create([
            'client_id' => $this->id,
            'user' => $clientMainInformation->user
        ]);
    }

    /**
     * @param $field
     * @param $val
     */
    public function clientSavePassword($field, $val)
    {
        $this->client_main_information()->update([
            $field => $val
        ]);
    }

    /**
     * @param $field
     * @param $val
     */
    public function clientSaveUser($field, $val)
    {
        $this->client_main_information()->update([
            $field => $val
        ]);
    }

    public function clientFullName()
    {
        if ($this->client_main_information && $this->client_main_information->name) {
            return $this->client_main_information->name . ' ' . $this->client_main_information->father_last_name . ' ' . $this->client_main_information->mother_last_name;
        }
        return $this->client_main_information->name ?? '';
    }

    public function clientGetName()
    {
        return $this->client_main_information()->first()->name;
    }

    public function clientGetUser()
    {
        return $this->client_main_information()->first()->user;
    }

    public function clientGetPassword()
    {
        return $this->client_main_information()->first()->password;
    }

    public function clientGetStatus()
    {
        return $this->client_main_information()->first()->status;
    }

    public function clientCreatePayment($request)
    {
        $input = $request->except(['file', 'date_payment', 'client_id', 'import']);
        $input['number'] = $this->setPaymentNumber();
        $input['date'] = $request->date_payment ?? Carbon::now()->toDateTimeString();
        if ($request->import) {
            $newImportDbService = new ImportdDBService();
            $module = Module::where('name', Module::CLIENT_PAYMENT_MODULE_NAME)->first();
            $input = $newImportDbService->processInputImportByModule($input, $module);
        }

        $input['send_receipt_after_payment'] = isset($request->send_receipt_after_payment) && $request->send_receipt_after_payment == true;
        $input['enabled_payment_promise'] = isset($request->enabled_payment_promise) && $request->enabled_payment_promise == true;

        if (!$request->import) {
            $this->receipt()->create(['receipt' => $input['receipt']]);
        }
        $input = Utility::modifyValueForCheckbox($input, 'ClientPayment');

        $periods = is_array($input['payment_period'])
            ? $input['payment_period']
            : explode(',', $input['payment_period']);

        $normalizedPeriods = collect($periods)
            ->filter()
            ->unique()
            ->values();

        $strPeriod = $normalizedPeriods->implode(',');
        $input['payment_period'] = $strPeriod;

        if ($request->import) {
            $input['add_by'] = 1;
            $this->imporDataToTableClientPayment($input, $request);
        } else {
            $input['add_by'] = Auth::user()->id;
            $input['receipt'] = ReceiptController::getStaticReceiptForClient();
            $payment = $this->payments()->create($input);
            if ($request->file) $payment->uploadFile($request->file);
            return $payment;
        }
    }

    public function imporDataToTableClientPayment($input, $request)
    {
        $newImportDbService = new ImportdDBService();
        $module = Module::where('name', Module::CLIENT_PAYMENT_MODULE_NAME)->first();
        $input = $newImportDbService->processInputImportByModule($input, $module);
        $input['id'] = $input['id_old'];
        $input['paymentable_id'] = $request->client_id;
        unset($input['import']);
        unset($input['id_old']);
        unset($input['client_id']);
        $input['date'] = $this->formatCustomDate($request->date_payment);
        $input['paymentable_type'] = 'App\Models\Client';
        if (empty($request->enabled_payment_promise)) {
            $input['enabled_payment_promise'] = false;
        }
        if ($input['payment_method_id'] == null) {
            $input['payment_method_id'] = MethodOfPayment::first()->id;
        }
        $this->receipt()->create(['receipt' => $input['receipt']]);
        $idModel = DB::table('payments')->insertGetId($input);
        $model = Payment::where('id', $idModel)->first();
        return $model;
    }

    public function formatCustomDate($fecha)
    {
        // Crear un objeto Carbon con los componentes de fecha
        $fechaCarbon = Carbon::createFromFormat('m/d/Y', $fecha);
        // Formatear la fecha al formato deseado (día-mes-año)
        $fechaFormateada = $fechaCarbon->format('Y-m-d');
        return $fechaFormateada;
    }

    public function formatearFecha($date)
    {
        $fechaFormateada = Carbon::createFromFormat('d/m/Y', $date)
            ->format('Y-m-d');
        return $fechaFormateada;
    }

    public function clientCreatePaymentAgreement($values)
    {
        $values['add_by'] = Auth::user()->id;
        $values['date'] = Carbon::now()->toDateTimeString();
        $values['number'] = $this->setPaymentNumber();
        $values['receipt'] = ReceiptController::getStaticReceiptForClient();
        $this->receipt()->create(['receipt' => $values['receipt']]);
        $payment = $this->payments()->create($values);

        return $payment;
    }

    public function setPaymentNumber()
    {
        $count = Payment::count();
        if ($count) {
            return Carbon::now()->format('ym') . '000' . $count + 1;
        }
        return Carbon::now()->format('ym') . '000' . '1';
    }

    public function clientCreateTransaction($request)
    {
        $input = $request->except('input-price-transaction', 'import', 'client_id');
        if ($request->import) {
            $newImportDbService = new ImportdDBService();
            $module = Module::where('name', Module::CLIENT_TRANSACTION_MODULE_NAME)->first();
            $input = $newImportDbService->processInputImportByModule($input, $module);
            $input['total'] = $this->calculateTotal($input);
            $input['date'] = $this->formatearFecha($input['date']);
        }
        $input = Utility::modifyValueForCheckbox($input, 'ClientTransaction');
        $input['iva'] = $input['iva'] ?? 0;
        $balance = $this->balance()->first();
        $input[$input['type']] = $input['total'];
        $input['price'] = $this->formatearNumerosFloat($input['price']);
        $balance->amount += ($input['type'] == 'credit') ? $input['total'] : -$input['total'];
        $balance->update();
        $input['account_balance'] = $balance->amount;
        $input['company_balance'] = $input['company_balance'] ?? 0;
        $input['client_id'] = $input['client_id'] ?? $this->id;
        $input['movement'] = $input['movement'] ?? '';
        $input['description'] = $input['description'] ?? '';
        $input['cantidad'] = $input['cantidad'] ?? 1;

        $this->transactions()->create($input);


        return $this;
    }

    public function calculateTotal($input)
    {
        $total = $input['total'] ?? null;
        if ($total == null) {
            $iva = $input['iva'] ?? 0;
            $price = $input['price'] ?? 0;
            if ($iva > 1) {
                $iva = $iva / 100;
            }
            if ($iva > 0) {
                $total = $price + ($price * $iva);
            } else {
                $total = $price;
            }
        }

        // Eliminar caracteres especiales y espacios en blanco
        $total = $this->formatearNumerosFloat($total);

        return $total;
    }

    public function formatearNumerosFloat($valor)
    {
        $valor = preg_replace('/[^0-9.]/', '', $valor);
        $formattedValor = floatval($valor);
        return $formattedValor;
    }

    public function clientCreateBalance()
    {
        return $this->balance()->create();
    }

    public function updateClientBalance(Payment $payment)
    {
        $balance = $this->balance()->first();
        if ($balance) {
            if ($payment->payment_method()->first() && $payment->payment_method()->first()->type === 'Acuerdo de Pago') {
                $balance->amount = 0;
            }
            $balance->amount += $payment->amount;
            $balance->update();

            $logService = new LogService();
            $logService->log($balance->client, 'El cliente #' . $balance->client->id . ' se actualiza balance, Anterior: ' . $balance->amount - $payment->amount . ' Nuevo: ' . $balance->amount);
        }
    }

    public function addTransaction($payment, $amountBalance)
    {
        return $this->transactions()->create([
            'date' => Carbon::now()->toDateTimeString(),
            'credit' => $payment->amount,
            'account_balance' => $amountBalance,
            'description' => $payment->getPaymentMethod(),
            'category' => 'Pago',
            'cantidad' => '1',
            'client_id' => $this->id,
            'type' => 'credit',
            'price' => $payment->amount,
            'iva' => 0,
            'total' => $payment->amount,
            'from_date' => null,
            'to_date' => null,
            'comment' => $payment->comment,
            'period' => $payment->payment_period,
            'add_to_invoice' => false,
            'company_balance' => $amountBalance,
            'movement' => '+ ' . $payment->amount,
            'service_name' => null,
            'invoice' => null,
            'transactionable_id' => $this->id,
            'transactionable_type' => 'App\Models\Client',
            'is_payment' => true,
            'payment_id' => $payment->id,
        ]);
    }

    public function clientCreateDocument($request)
    {
        $document = $this->createClientDocument($request->except('file'));
        return $this->uploadAndSaveDocumentUploaded($request->file, $document, $this->id);
    }

    public function createClientDocument($input)
    {
        if (isset($input['show'])) $input['show'] = ($input['show'] == 'true');
        $input = $this->addCreatorId($input);
        return $this->documents()->create($input);
    }

    public function addCreatorId($input)
    {
        $input['added_by_id'] = Auth::user()->id;
        return $input;
    }

    public function uploadAndSaveDocumentUploaded($file, $document, $idClient)
    {
        $file_process = new FileController;
        $module_path = 'client/' . $idClient . '/document';
        $properties = $file_process->processSingleFileAndReturnProperties($file, $module_path, $document->id);

        $document->file()->create($properties);
        $file->storeAs('/public/' . $module_path . '/' . $document->id, $properties['name']);

        return true;
    }

    public function clientCreateReminderConfiguration()
    {
        return $this->reminder_configuration()->create([
            'activate_reminders' => '1',
            'type_of_message' => 'Mail + SMS',
            'reminder_1_days' => '5',
            'reminder_2_days' => '3',
            'reminder_3_days' => '1'
        ]);
    }

    public function clientCreateBillingConfiguration()
    {
        return $this->billing_configuration()->create([
            'billing_activated' => true,
            'type_billing_id' => $this->client_main_information()->first()->type_of_billing_id ?? 1,
            'period' => '1',
            'billing_date' => Carbon::now()->subDay()->day,
            'billing_expiration' => 1,
            'grace_period' => '90',
            'autopay_invoice' => true,
            'send_financial_notification' => true,
        ]);
    }

    public function deleteTransaction($payment)
    {
        return $this->transactions()->where('payment_id', $payment->id)->delete();
    }

    public function deleteTransactionWhenDeleteAgreement($payment)
    {
        return $this->transactions()
            ->where('description', 'Rectificación de debito por Acuerdo de Pago')
            ->orWhere('description', 'Acuerdo de Pago')
            ->where('price', $payment->amount)
            ->orderBy('created_at', 'desc')
            ->take(2)
            ->delete();
    }

    public function lastTransaction()
    {
        return $this->transactions()->orderBy('created_at', 'desc')->first();
    }

    public function deleteInvoiceWhenDeleteAgreement($payment)
    {
        $clientInvoiceAgreement = $this->client_invoices()
            ->where('estado', 'Pagado')
            ->where('total', $payment->amount)
            ->orderBy('created_at', 'desc')
            ->first();

        $clientInvoiceCancelByAgreements = ClientInvoice::where('note', $clientInvoiceAgreement->number)->get();

        foreach ($clientInvoiceCancelByAgreements as $clientInvoiceCancelByAgreement) {
            $clientInvoiceCancelByAgreement->update(['estado' => 'Pagar (del saldo de la cuenta)', 'note' => null]);
        }
        $clientInvoiceAgreement->delete();
    }

    public function isPromisePayment()
    {
        return $this->payments()->where('payment_promise', true)
            ->where(function ($query) {
                $query->whereDate('first_court_date', '>=', \Carbon\Carbon::now()->format('Y-m-d'))
                    ->orWhereDate('second_court_date', '>=', \Carbon\Carbon::now()->format('Y-m-d'))
                    ->orWhereDate('third_court_date', '>=', \Carbon\Carbon::now()->format('Y-m-d'));
            })
            ->first();
    }

    public function scopeStatusClients()
    {
        $status = [];
        $clientMainInformation = ClientMainInformation::all();

        foreach ($clientMainInformation as $client) {
            $status[$client->client_id] = $client->estado;
        }
        return $status;
    }

    public function activarCliente(): void
    {
        if ($this->client_main_information()->first()->estado !== ComunConstantsController::STATE_ACTIVE) {
            $this->client_main_information()->update(['estado' => ComunConstantsController::STATE_ACTIVE]);
        }
    }
}
