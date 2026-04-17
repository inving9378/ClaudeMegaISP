<?php

namespace App\Http\Controllers\Module\Shared;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\BundleRepository;
use App\Http\Repository\ClientMainInformationRepository;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\CustomRepository;
use App\Http\Repository\InternetRepository;
use App\Http\Repository\ModuleRepository;
use App\Http\Repository\VoiseRepository;
use App\Models\ClientMainInformation;
use App\Models\CrmMainInformation;
use App\Models\Task;
use Illuminate\Http\Request;

class ComponentSearchServiceController extends Controller
{
    public function getServiceByClientMainInformationId(Request $request)
    {
        $clientRepository = new ClientRepository();
        if (isset($request->idToFind) && $request->idToFind != "null" && $request->idToFind != 'undefined') {
            $clientMainInformation = ClientMainInformation::where('id', $request->idToFind)->first();
            $clientWithServices = $clientRepository->getServicesForClient($clientMainInformation->client_id);
            $services = ComunConstantsController::ALL_CLIENT_SERVICE;
            $serviceId = [];
            foreach ($services as $service) {
                foreach ($clientWithServices->$service as $clientService) {
                    $serviceId[] = $clientService->id;
                }
            }

            if (!empty($serviceId)) {
                return response()->json($serviceId[0]);
            }
        }

        return response()->json(null);
    }
}
