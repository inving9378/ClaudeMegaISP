<?php

namespace App\Http\Controllers\Module\Message\ProformaInvoiceEmail;


use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\message\proforma_invoice_email\ProformaInvoiceEmailDatatableHelper;
use App\Http\Requests\module\message\invoice_email\InvoiceEmailCreateRequest;
use App\Services\EmailConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProformaInvoiceEmailController extends Controller
{
    protected $helper;
    protected $crudValidationRequest;
    public function __construct(ProformaInvoiceEmailDatatableHelper $helper)
    {
        $this->crudValidationRequest = new InvoiceEmailCreateRequest();
        $this->helper = $helper;

        $this->data['model'] = 'App\Models\ProformaInvoiceEmail';
        $this->data['url'] = 'meganet.module.message.proforma_invoice_email';
        $this->data['module'] = 'ProformaInvoiceEmail';

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
            $emailConfigService->sendEmail('proforma_invoice', $message);
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
