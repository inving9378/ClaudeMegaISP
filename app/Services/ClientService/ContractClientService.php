<?php

namespace App\Services\ClientService;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientBundleServiceRepository;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\ClientVozServiceRepository;
use App\Http\Repository\ColonyRepository;
use App\Http\Repository\DocumentClientRepository;
use App\Http\Repository\DocumentTemplateRepository;
use App\Http\Repository\MunicipalityRepository;
use App\Http\Repository\StateRepository;
use App\Models\ClientInternetService;
use App\Models\ClientVozService;
use App\Services\DocumentTemplateService;
use App\Services\NetworkIpService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ContractClientService
{
    public function generateContractClient($request, $idClient)
    {
        $documentTemplateRepository = new DocumentTemplateRepository();
        $clientRepository = new ClientRepository();
        $client = $clientRepository->getClientById($idClient);
        $dataClient = $this->getDataClient($client);

        $dataTemplate = $request->html ?? $documentTemplateRepository->getHtmlById($request->template);
        $documentTemplateService = new DocumentTemplateService();
        $validation = $documentTemplateService->validateAndReplaceTemplate($dataTemplate, $dataClient);

        if ($validation['status'] == 'fail') {
            return [
                'status' => 'fail',
                'keys' => $validation['keys'],
            ];
        }

        ini_set('memory_limit', '-1');
        // Convertir la vista a PDF
        $html = str_replace('\n', '', $validation['html']);

        $pdf = Pdf::loadHTML($html);
        $documentTemplateRepository = new DocumentTemplateRepository();
        if (isset($request->name) && !empty($request->name)) {
            $nameTemplate = $request->name;
        } else {
            $nameTemplate = $documentTemplateRepository->getNameById($request->template);
        }

        $filePath = 'client/' . $idClient . '/document/' . $nameTemplate . '.pdf';
        $output = $pdf->output();
        Storage::disk('public')->put($filePath, $output);

        $documentClientRepository = new DocumentClientRepository();
        $document = [
            'title' => $nameTemplate,
            'client_id' => $idClient,
            'description' => $nameTemplate,
            'added_by_id' => auth()->user()->id,
            'show' => true,
        ];
        $documentClient = $documentClientRepository->create($document);
        $documentClient->file()->create([
            'name' => $nameTemplate . '.pdf',
            'path' => '/storage/' . $filePath,
            'type' => 'pdf',
            'size' => Storage::disk('public')->size($filePath),
            'preview' => false,
            'fileable_id' => $documentClient->id,
            'fileable_type' => 'App\Models\DocumentClient',
        ]);

        $temporalTemplatePath = 'client/' . $idClient . '/document/temp_contract_template/' . $nameTemplate . '.pdf';

        $documentTemplateService->deleteTemplateFile($temporalTemplatePath);

        return [
            'status' => 'ok',
            'file_path' => '/storage/' . $filePath
        ];
    }


    public function getDataClient($client)
    {
        $clientMainInformation = $client->client_main_information;
        $clientAdditionalInformation = $client->client_additional_information;
        $colonyRepository = new ColonyRepository();
        $municipalityRepository = new MunicipalityRepository();
        $stateRepository = new StateRepository();
        $clientService = new ClientService($client);
        $dataServices = $clientService->getDataServicesForClient();
        $dataPendingPayments = $clientService->getDataPendingPayments();
        $costAllServices = $clientService->getCostAllServices();

        $data = [
            'image_src' => public_path(),
            'now' => Carbon::now()->toDateString(),
            'id' => $client->id,
            'name' => $clientMainInformation->name,
            'father_last_name' => $clientMainInformation->father_last_name,
            'mother_last_name' => $clientMainInformation->mother_last_name,
            'street' => $clientMainInformation->street,
            'user' => $clientMainInformation->user,
            'estado' => $clientMainInformation->estado,
            'type_of_billing_id' => $clientMainInformation->type_of_billing_id,
            'ift' => $clientMainInformation->ift,
            'email' => $clientMainInformation->email,
            'phone' => $clientMainInformation->phone,
            'phone2' => $clientMainInformation->phone2,
            'phone3' => $clientMainInformation->phone3,
            'nif_pasaport' => $clientMainInformation->nif_pasaport,
            'external_number' => $clientMainInformation->external_number,
            'internal_number' => $clientMainInformation->internal_number,
            'zip' => $clientMainInformation->zip,
            'colony' => $colonyRepository->getColonyNameById($clientMainInformation->colony_id),
            'municipality' => $municipalityRepository->getMunicipalityNameById($clientMainInformation->municipality_id),
            'state' => $stateRepository->getStateNameById($clientMainInformation->state_id),
            'password' => $clientMainInformation->password,
            'full_name' => $clientMainInformation->client_name_with_fathers_names,
            'category' => $clientAdditionalInformation->category,
            'partner' => $clientMainInformation->partner_name,
            'geo_data' => $clientMainInformation->geo_data,
            'created_at' => $client->created_at,
            'created_by' => $client->created_by,
            'fecha_corte' => $client->fecha_corte,
            'fecha_pago' => $client->fecha_pago,
            'power_dbm' => $clientAdditionalInformation->power_dbm,
            'original_password' => $clientAdditionalInformation->original_password,

            'user_film' => $clientAdditionalInformation->user_film,
            'password_film' => $clientAdditionalInformation->password_film,
            'password_wifi' => $clientAdditionalInformation->password_wifi,
            'reinstatement' => $clientAdditionalInformation->reinstatement,
            'installation_on_time' => $clientAdditionalInformation->installation_on_time,
            'technician_attencion' => $clientAdditionalInformation->technician_attencion,
            'amount_technician_and_why' => $clientAdditionalInformation->amount_technician_and_why,
            'comment' => $clientAdditionalInformation->comment,
            'doubt_signed_contract' => $clientAdditionalInformation->doubt_signed_contract,
            'box_nomenclator' => $clientAdditionalInformation->box_nomenclator,
            'social_id' => $clientAdditionalInformation->social_id,
            'modem_sn' => $clientAdditionalInformation->modem_sn,
            'gpon_ont' => $clientAdditionalInformation->gpon_ont,
            'conection_type' => $clientAdditionalInformation->connection_type,
            'amount' => $client->balance->amount,
            'table_client_services' => $dataServices,
            'client_bundle_service_name' => $this->getServiceNameFromData($dataServices, 'bundle_service'),
            'client_voz_service_name' => $this->getServiceNameFromData($dataServices, 'voz_service'),
            'client_voz_service_phone' => $this->getServicePhonesFromData($client),
            'client_internet_service_name' => $this->getServiceNameFromData($dataServices, 'internet_service'),
            'client_internet_service_ip' => $this->getServiceIPFromData($client),
            'client_custom_service_name' => $this->getServiceNameFromData($dataServices, 'custom_service'),
            'table_client_pending_payments' => $dataPendingPayments,
            'cost_all_services' => $costAllServices . ' $'
        ];
        return $data;
    }

    private function getServiceNameFromData($dataServices, $service)
    {
        $indexedServices = collect($dataServices)->keyBy('relation');
        return $indexedServices[$service]['service_name'] ?? null;
    }
    private function getServiceIPFromData($client)
    {
        $ips = [];
        $clientInternetServices = (new ClientInternetServiceRepository())->getServiceFilterByClientId($client->id);
        foreach ($clientInternetServices as $clientInternetService) {
            $networkIpService = new NetworkIpService($clientInternetService);
            $ips[] = $networkIpService->getClientIp();
        }
        return implode(', ', $ips);
    }

    private function getServicePhonesFromData($client)
    {
        $phones = [];
        $clientVozServices = (new ClientVozServiceRepository())->getServiceFilterByClientId($client->id);
        foreach ($clientVozServices as $clientVozService) {
            $phones[] = $clientVozService->phone;
        }
        return implode(', ', $phones);
    }
}
