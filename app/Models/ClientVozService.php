<?php

namespace App\Models;

use App\Http\Requests\module\client\ClientVozServiceCreateRequest;
use App\Http\Traits\Models\Client\ClientVozService\Scope\ScopeClientVozService;
use App\Models\Interface\ServiceInterface;
use App\Services\PromotionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientVozService extends BaseModel implements ServiceInterface
{
    use HasFactory, ScopeClientVozService;
    protected $guarded = [];
    protected $append = ['instalation_cost', 'has_active_instalation_cost', 'price_service', 'service_name'];

    public function modelName()
    {
        return 'ClientVozService';
    }

    public function voise()
    {
        return $this->belongsTo('App\Models\Voise', 'voz_id');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function bundle_service()
    {
        return $this->belongsTo('App\Models\ClientBundleService', 'client_bundle_service_id');
    }

    public function client_payment_service()
    {
        return $this->morphMany(ClientPaymentService::class, 'service_paymentable');
    }

    public function client_invoice()
    {
        return $this->morphMany(ClientInvoice::class, 'serviceable');
    }

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    public function client_serviceables()
    {
        return $this->morphToMany(ClientInvoice::class, 'client_serviceable');
    }

    public function client_grace_period()
    {
        return $this->morphOne(ClientGracePeriod::class, 'serviceable');
    }

    public function serviceHasIva()
    {
        return $this->voise->tax_include;
    }

    public function getTax()
    {
        return $this->voise->tax;
    }

    public function getNewPriceByIva()
    {
        $tax = $this->voise->tax;
        if ($tax) {
            $price = $this->voise->price;
            return $price + ($price * $tax / 100);
        }
        return $this->voise->price;
    }

    public function isDeployed()
    {
        return $this->deployed;
    }


    public function getClientWithBalanceAndBillingConfiguration()
    {
        return $this->client()->with('balance', 'billing_configuration')->first();
    }

    public function haveTransaction(): bool
    {
        return $this->transactions()->count();
    }

    public function getPlanRelation()
    {
        return 'voise';
    }

    public function getService()
    {
        return \App\Services\ClientService\ClientInternetService::class;
    }

    public function getRepository()
    {
        return \App\Http\Repository\ClientVozServiceRepository::class;
    }

    public function getServiceNameAttribute()
    {
        return $this->voise->title;
    }

    public function getInstalationCostAttribute()
    {
        return $this->voise->cost_instalation ?? 0;
    }

    public function getHasActiveInstalationCostAttribute()
    {
        return $this->voise->cost_instalation_enable;
    }

    public function getRequestAndStoreMethod()
    {
        $request = new ClientVozServiceCreateRequest();
        $storeMethod = 'App\Http\Controllers\Module\Client\ClientVozServiceController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod,
            'parameter_id' => 'client_id',
        ];
    }

    public function getPriceServiceAttribute()
    {
        $price = $this->voise->price ?? 0;
        $promotionService = new PromotionService();
        $promotion = $promotionService->getIfServiceHasPromotion($this);
        if ($promotion) {
            $price = $promotionService->getDiscountValuePromotion($this, $price);
        }
        return $price;
    }
}
