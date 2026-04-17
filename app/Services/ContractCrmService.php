<?php

namespace App\Services;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\ColonyRepository;
use App\Http\Repository\CrmRepository;
use App\Http\Repository\DocumentClientRepository;
use App\Http\Repository\DocumentCrmRepository;
use App\Http\Repository\DocumentTemplateRepository;
use App\Http\Repository\MunicipalityRepository;
use App\Http\Repository\StateRepository;
use App\Services\DocumentTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ContractCrmService
{
    public function generateContractClient($request, $idCrm)
    {
        $documentTemplateRepository = new DocumentTemplateRepository();
        $crmRepository = new CrmRepository();
        $crm = $crmRepository->getModelById($idCrm);
        $dataCrm = $this->getData($crm);

        $dataTemplate = $request->html ?? $documentTemplateRepository->getHtmlById($request->template);
        $documentTemplateService = new DocumentTemplateService();
        $validation = $documentTemplateService->validateAndReplaceTemplate($dataTemplate, $dataCrm);

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

        $filePath = 'client/' . $idCrm . '/document/' . $nameTemplate . '.pdf';
        $output = $pdf->output();
        Storage::disk('public')->put($filePath, $output);

        $DocumentCrmRepository = new DocumentCrmRepository();
        $document = [
            'title' => $nameTemplate,
            'crm_id' => $idCrm,
            'description' => $nameTemplate,
            'added_by_id' => auth()->user()->id,
            'show' => true,
        ];
        $documentClient = $DocumentCrmRepository->create($document);
        $documentClient->file()->create([
            'name' => $nameTemplate . '.pdf',
            'path' => '/storage/' . $filePath,
            'type' => 'pdf',
            'size' => Storage::disk('public')->size($filePath),
            'preview' => false,
            'fileable_id' => $documentClient->id,
            'fileable_type' => 'App\Models\DocumentClient',
        ]);

        $temporalTemplatePath = 'client/' . $idCrm . '/document/temp_contract_template/' . $nameTemplate . '.pdf';

        $documentTemplateService->deleteTemplateFile($temporalTemplatePath);

        return [
            'status' => 'ok',
            'file_path' => '/storage/' . $filePath
        ];
    }


    public function getData($crm)
    {
        $crmMainInformation = $crm->crm_main_information;
        $crmLeadInformation = $crm->crm_lead_information;
        $colonyRepository = new ColonyRepository();
        $municipalityRepository = new MunicipalityRepository();
        $stateRepository = new StateRepository();

        $data = [
            'image_src' => public_path(),
            'now' => Carbon::now()->toDateString(),
            'id' => $crm->id,
            'name' => $crmMainInformation->name,
            'father_last_name' => $crmMainInformation->father_last_name,
            'mother_last_name' => $crmMainInformation->mother_last_name,
            'email' => $crmMainInformation->email,
            'phone' => $crmMainInformation->phone,
            'phone2' => $crmMainInformation->phone2,
            'phone3' => $crmMainInformation->phone3,
            'ift' => $crmMainInformation->ift,
            'partner' => $crmMainInformation->partner_id,
            'geo_data' => $crmMainInformation->geodata,
            'created_by' => $crm->created_by,

            'external_number' => $crmMainInformation->external_number,
            'internal_number' => $crmMainInformation->internal_number,
            'zip' => $crmMainInformation->zip,
            'created_at' => $crm->created_at,
            'street' => $crmMainInformation->street,
            'colony' => $colonyRepository->getColonyNameById($crmMainInformation->colony_id),
            'municipality' => $municipalityRepository->getMunicipalityNameById($crmMainInformation->municipality_id),
            'state' => $stateRepository->getStateNameById($crmMainInformation->state_id),
            'nif_pasaport' => $crmMainInformation->nif_pasaport,
            'type_of_billing_id' => $crmMainInformation->type_of_billing_id,
        ];
        return $data;
    }
}
