<?php

namespace App\Http\Controllers\Module\Message\Inbox;

use App\Http\Controllers\Controller;
use App\Http\Repository\ConfigFinanceNotificationRepository;
use Illuminate\Support\Facades\Log;

class InboxController extends Controller
{
    public function __construct()
    {
        $this->data['url'] = 'meganet.module.message.inbox';
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic('Inbox');
        return view($this->data['url'] . '.index', $this->data);
    }



    public function getDataTabs()
    {
        try {
            $configFinanceRepository = new ConfigFinanceNotificationRepository();
            $data = $configFinanceRepository->getAll();
            return response()->json([
                'success' => true,
                'message' => 'Los datos se ha guardado con éxito.',
                'tabs' => $data
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
