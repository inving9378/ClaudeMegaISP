<?php

namespace App\Models;

use App\Http\Traits\Models\Client\ClientBundleService\Scope\ScopeClientBundleService;
use App\Models\Interface\ServiceInterface;
use App\Services\PromotionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ClientBundleService extends BaseModel implements ServiceInterface
{
    use HasFactory, ScopeClientBundleService;

    protected $guarded = [];
    protected $with = ['service_internet', 'service_custom'];
    protected $append = ['service_name', 'instalation_cost', 'has_active_instalation_cost', 'price_service'];

    public function modelName()
    {
        return 'ClientBundleService';
    }


    // relations
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function voz()
    {
        return $this->belongsTo('App\Models\Voise');
    }

    public function internet()
    {
        return $this->belongsTo('App\Models\Internet');
    }

    public function bundle()
    {
        return $this->belongsTo('App\Models\Bundle');
    }

    public function service_internet()
    {
        return $this->hasMany(ClientInternetService::class);
    }

    public function service_voz()
    {
        return $this->hasMany(ClientVozService::class);
    }

    public function service_custom()
    {
        return $this->hasMany(ClientCustomService::class);
    }

    public function client_payment_service()
    {
        return $this->morphMany(ClientPaymentService::class, 'service_paymentable');
    }

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    public function client_serviceables()
    {
        return $this->morphToMany(ClientInvoice::class, 'client_serviceable')->withTimestamps();
    }

    public function client_grace_period()
    {
        return $this->morphOne(ClientGracePeriod::class, 'serviceable');
    }

    public function serviceHasIva()
    {
        return $this->bundle->tax_include;
    }

    public function getTax()
    {
        return $this->bundle->tax;
    }

    public function service_in_address_list()
    {
        return $this->morphMany(ServiceInAddressList::class, 'serviceable');
    }

    // functions
    public function getNewPriceByIva()
    {
        $tax = $this->bundle->tax;
        if ($tax) {
            $price = $this->bundle->price;
            return $price + ($price * $tax / 100);
        }
        return $this->bundle->price;
    }

    public function isDeployed()
    {
        return $this->deployed;
    }

    /**
     * @return mixed
     */
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
        return 'bundle';
    }

    public function getService()
    {
        return \App\Services\ClientService\ClientBundleService::class;
    }

    public function getRepository()
    {
        return \App\Http\Repository\ClientBundleServiceRepository::class;
    }

    public function getServiceNameAttribute()
    {
        return $this->bundle->title;
    }

    public function getInstalationCostAttribute()
    {
        return $this->bundle->cost_instalation ?? 0;
    }

    public function getHasActiveInstalationCostAttribute()
    {
        return $this->bundle->cost_instalation_enable;
    }

    public function getRequestAndStoreMethod()
    {
        $request = new Request();
        $storeMethod = 'App\Http\Controllers\Module\Client\ClientBundleServiceController@imporDataToTable';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod,
            'parameter_id' => 'client_id',
        ];
    }

    public function getPriceServiceAttribute()
    {
        $price = $this->bundle->price ?? 0;
        $promotionService = new PromotionService();
        $promotion = $promotionService->getIfServiceHasPromotion($this);
        if ($promotion) {
            $price = $promotionService->getDiscountValuePromotion($this, $price);
        }
        return $price;
    }
}
