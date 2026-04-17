<?php

namespace App\Http\Controllers\Module\Message\PaymentEmail;


use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\message\payment_email\PaymentEmailDatatableHelper;
use App\Http\Requests\module\message\payment_email\PaymentEmailCreateRequest;
use App\Services\EmailConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentEmailController extends Controller
{
    protected $helper;
    protected $crudValidationRequest;
    public function __construct(PaymentEmailDatatableHelper $helper)
    {
        $this->crudValidationRequest = new PaymentEmailCreateRequest();
        $this->helper = $helper;

        $this->data['model'] = 'App\Models\PaymentEmail';
        $this->data['url'] = 'meganet.module.message.payment_email';
        $this->data['module'] = 'PaymentEmail';

        $this->includeLibraryDinamic($this->data['model']);
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.listar', $this->data);
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function sendMessage(Request $request){
        $id = $request->id;
        $message = $this->data['model']::find($id);
        try {
            $emailConfigService = new EmailConfigService();
            $emailConfigService->sendEmail('payment', $message);
            return response()->json([
                'success' => true,
                'message' => 'El mensaje se ha enviado con éxito.',
            ], 200);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud',
            ], 500);
        }
    }

}
