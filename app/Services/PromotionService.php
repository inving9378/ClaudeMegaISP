<?php

namespace App\Services;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\BundleRepository;
use App\Http\Repository\CustomRepository;
use App\Http\Repository\InternetRepository;
use App\Http\Repository\VoiseRepository;
use App\Models\Bundle;
use App\Models\ClientBundleService;
use App\Models\ClientCustomService;
use App\Models\ClientInternetService;
use App\Models\ClientVozService;
use App\Models\Custom;
use App\Models\Internet;
use App\Models\Voise;
use Carbon\Carbon;
use Livewire\Features\SupportConsoleCommands\Commands\Upgrade\ThirdPartyUpgradeNotice;

class PromotionService
{

    public function createPromotionIfPromotionEnable($input)
    {
        if (isset($input['promotion_enable']) && $input['promotion_enable'] == true) {
            $input['init_date_discount'] = $this->getDateInitAndFinishPromotion($input)['init_date_discount'];
            $input['end_date_discount'] = $this->getDateInitAndFinishPromotion($input)['end_date_discount'];
        }
        return $input;
    }

    public function updateAndReturnInput($input, $model)
    {

        if ($this->promotionIsActiveAndNotInitDateDiscount($input, $model)) {
            $input['init_date_discount'] = $this->getDateInitAndFinishPromotion($input)['init_date_discount'];
            $input['end_date_discount'] = $this->getDateInitAndFinishPromotion($input)['end_date_discount'];
        }
        //Si viene activo y cambio el discount_period se debe actualizar la fecha de terminacion de la promocion
        if ($this->promotionIsActiveAndChangeDiscountPeriod($input, $model)) {
            $input['end_date_discount'] = $this->updateFinishPromotion($input, $model)['end_date_discount'];
        }
        if (isset($input['promotion_enable']) && $input['promotion_enable'] == false) {
            $input['init_date_discount'] = null;
            $input['end_date_discount'] = null;
        }

        return $input;
    }

    public function promotionIsActiveAndNotInitDateDiscount($input, $model)
    {
        return $input['promotion_enable'] && !$model->promotion_enable && !$model->init_date_discount;
    }

    public function promotionIsActiveAndChangeDiscountPeriod($input, $model)
    {
        return $input['promotion_enable'] && $model->promotion_enable && $input['discount_period'] != $model->discount_period;
    }


    public function getDateInitAndFinishPromotion($input)
    {
        $now = Carbon::now();
        $monthsToAdd = $input['discount_period'];
        $initDateDiscount = $now->toDateTimeString();
        $endDateDiscount = $now->addMonths($monthsToAdd)->toDateTimeString();
        return ['init_date_discount' => $initDateDiscount, 'end_date_discount' => $endDateDiscount];
    }

    public function updateFinishPromotion($input, $model)
    {
        $initDateDiscount = $model->init_date_discount;
        $monthsToAdd = $input['discount_period'];
        $endDateDiscount = $initDateDiscount->addMonths($monthsToAdd)->toDateTimeString();
        return ['end_date_discount' => $endDateDiscount];
    }


    public function getIfServiceHasPromotion($service)
    {
        $model = $this->getModelByService($service);
        if (!$model) {
            return false;
        }
        $promotion = $model->promotion_enable;
        return $promotion;
    }


    private function getModelByService($service)
    {
        $model = null;
        if ($service instanceof ClientInternetService) {
            $model = (new InternetRepository())->getModelFilterById($service->internet_id);
        }
        if ($service instanceof ClientBundleService) {
            $model = (new BundleRepository())->getModelFilterById($service->bundle_id);
        }

        if ($service instanceof ClientVozService) {
            $model = (new VoiseRepository())->getModelFilterById($service->voz_id);
        }

        if ($service instanceof ClientCustomService) {
            $model = (new CustomRepository())->getModelFilterById($service->custom_id);
        }

        return $model;
    }

    public function getPriceServiceIfHasPromotion($service)
    {
        $price = $service->sum('price');
        if ($this->getIfServiceHasPromotion($service)) {
            $price = $this->getDiscountValuePromotion($service, $price);
        }
        return $price;
    }

    public function getDiscountValuePromotion($service, $price)
    {
        $discount = 0;
        $model = $this->getModelByService($service);
        // Verificar si el modelo es válido
        if (!$model) {
            return $price;
        }
        $discount = $this->getDiscount($model);
        // Aplicar el descuento al precio
        $price = $price - $discount;
        // Retornar el precio final
        return $price;
    }

    public function getServicesHasPromotionByClient($clientWithServices)
    {
        $servicesHasPromotion = [];
        $allServices = ComunConstantsController::ALL_CLIENT_SERVICE;
        foreach ($allServices as $service) {
            foreach ($clientWithServices->$service as $clientService) {
                if ($this->getIfServiceHasPromotion($clientService)) {
                    $model = $this->getModelByService($clientService);
                    $rutaUrl = $this->getRutaEditarByModel($model);
                    $servicesHasPromotion[$rutaUrl] = $clientService->service_name;
                }
            }
        }

        return $servicesHasPromotion;
    }


    public function getRutaEditarByModel($model)
    {
        $rutaUrl = 'javascript:void(0)';
        if ($model instanceof Internet) {
            $rutaUrl = '/internet/editar/' . $model->id;
        }

        if ($model instanceof Bundle) {
            $rutaUrl = '/paquetes/editar/' . $model->id;
        }

        if ($model instanceof Voise) {
            $rutaUrl = '/voz/editar/' . $model->id;
        }

        if ($model instanceof Custom) {
            $rutaUrl = '/custom/editar/' . $model->id;
        }
        return $rutaUrl;
    }


    public function getDiscount($model)
    {
        $price = $model->price;
        $discount = 0;
        // Comprobar si hay un descuento fijo
        if (!empty($model['discount_value_fixed']) && $model['discount_value_fixed'] > 0) {
            $discount = $model['discount_value_fixed'];
        }
        // Calcular el descuento porcentual si no hay un descuento fijo
        if (!empty($model['discount_value']) || $model['discount_value'] == 0) {
            $discount += $price * ($model['discount_value'] / 100);
        }
        return $discount;
    }

}
