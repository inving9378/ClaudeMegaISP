<?php

namespace App\Http\Controllers\Module\Message\InvoiceEmail;


use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\message\invoice_email\InvoiceEmailDatatableHelper;
use App\Http\Requests\module\message\invoice_email\InvoiceEmailCreateRequest;
use App\Services\EmailConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceEmailController extends Controller
{
    protected $helper;
    protected $crudValidationRequest;
    public function __construct(InvoiceEmailDatatableHelper $helper)
    {
        $this->crudValidationRequest = new InvoiceEmailCreateRequest();
        $this->helper = $helper;

        $this->data['model'] = 'App\Models\InvoiceEmail';
        $this->data['url'] = 'meganet.module.message.invoice_email';
        $this->data['module'] = 'InvoiceEmail';

        $this->includeLibraryDinamic($this->data['module']);
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['module']);
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
            $emailConfigService->sendEmail('invoice', $message);
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
