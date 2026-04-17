<?php

namespace App\Services;

use App\Http\Repository\AppLayoutConfigurationRepository;

class AppLayoutConfigurationService
{
    public function createOrUpdateClientDatatableColor($colorDatatable)
    {
        $appLayoutConfigurationRepository = new AppLayoutConfigurationRepository();
        $colorActivatedUser = $appLayoutConfigurationRepository->getModelByAuthUserId();

        if ($colorActivatedUser) {
            $colorActivatedUser->update([
                'client_datatable_color' => $colorDatatable
            ]);
        } else {
            $appLayoutConfigurationRepository->create([
                'user_id' => auth()->user()->id,
                'client_datatable_color' => $colorDatatable,
                'color_mode' => 'light'
            ]);
        }
    }
}
