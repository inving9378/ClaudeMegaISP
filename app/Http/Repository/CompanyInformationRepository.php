<?php

namespace App\Http\Repository;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Models\ClientUser;
use App\Models\CompanyInformation;

class CompanyInformationRepository
{
    protected $client;
    protected $model;

    public function __construct()
    {
        $this->model = CompanyInformation::query();
    }

    public function count()
    {
        return $this->model->count();
    }


    public function getDataCompany()
    {
        $dataCompanyInformation = $this->model->first();

        return [
            'company_name' => $dataCompanyInformation->company_name,
            'company_postal_code' => $dataCompanyInformation->company_postal_code,
            'country' => $dataCompanyInformation->country,
            'colony_id' => $dataCompanyInformation->colony_name,
            'state_id' => $dataCompanyInformation->state_name,
            'municipality_id' => $dataCompanyInformation->municipality_name,
            'email' => $dataCompanyInformation->email,
            'atention_client_phone' => $dataCompanyInformation->atention_client_phone,
            'rfc' => $dataCompanyInformation->rfc,
            'iva' => $dataCompanyInformation->iva,
            'bank_name' => $dataCompanyInformation->bank_name,
            'bank_account' => $dataCompanyInformation->bank_account,
            'url_portal' => $dataCompanyInformation->url_portal,
            'url_logo' => $dataCompanyInformation->logo ? $this->getUrlLogo($dataCompanyInformation->url_logo) : ComunConstantsController::URL_LOGO_DEFAULT,
            'company_street' => $dataCompanyInformation->company_street,
            'company_external_number' => $dataCompanyInformation->company_external_number,
            'company_internal_number' => $dataCompanyInformation->company_internal_number
        ];
    }

    public function getUrlLogo($url_logo)
    {
        $url_logo = str_replace("public", "storage", $url_logo);
        $url_logo = str_replace("\\", "/", $url_logo);
        return asset($url_logo);
    }
}
