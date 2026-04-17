<?php

namespace App\Models;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Requests\module\client\ClientCustomServiceCreateRequest;
use App\Http\Traits\Models\Client\ClientCustomService\Scope\ScopeClientCustomService;
use App\Models\Interface\ServiceInterface;
use App\Services\PromotionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientCustomService extends BaseModel implements ServiceInterface
{
    use HasFactory, ScopeClientCustomService;

    protected $guarded = [];
    protected $with = ['router'];
    protected $append = ['instalation_cost', 'has_active_instalation_cost', 'service_name', 'price_service'];

    public function modelName()
    {
        return 'ClientCustomService';
    }

    //relations

    public function custom()
    {
        return $this->belongsTo('App\Models\Custom');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function internet()
    {
        return $this->belongsTo('App\Models\Internet');
    }

    public function bundle_service()
    {
        return $this->belongsTo('App\Models\ClientBundleService', 'client_bundle_service_id');
    }

    public function router()
    {
        return $this->belongsTo('App\Models\Router');
    }

    public function mikrotik_tariff_target_tail()
    {
        return $this->hasOne(MikrotikTariffTargetTail::class);
    }

    public function serviceHasIva()
    {
        return $this->custom->tax_include;
    }

    public function getTax()
    {
        return $this->custom->tax;
    }

    public function client_payment_service()
    {
        return $this->morphMany(ClientPaymentService::class, 'service_paymentable');
    }

    public function client_invoice()
    {
        return $this->morphMany(ClientInvoice::class, 'serviceable');
    }


    public function service_in_address_list()
    {
        return $this->morphMany(ServiceInAddressList::class, 'serviceable');
    }

    public function network_ip()
    {
        return $this->belongsTo('App\Models\NetworkIp', 'ipv4');
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

    public function getInstalationCostAttribute()
    {
        return $this->custom->cost_instalation ?? 0;
    }

    public function getHasActiveInstalationCostAttribute()
    {
        return $this->custom->cost_instalation_enable;
    }


    //functions

    public function getNewPriceByIva()
    {
        $tax = $this->custom->tax;
        if ($tax) {
            $price = $this->custom->price;
            return $price + ($price * $tax / 100);
        }
        return $this->custom->price;
    }


    public function isDeployed()
    {
        return $this->deployed;
    }

    public function isIpv4AssigmentStatic()
    {
        return $this->ipv4_assignment == ComunConstantsController::IPV4_ASSIGNMENT_STATIC;
    }

    public function isIpv4AssigmentPoolIp()
    {
        return $this->ipv4_assignment == ComunConstantsController::IPV4_ASSIGNMENT_POOL_IP;
    }

    public function getIp()
    {
        return $this->ipv4;
    }

    public function getPoolIp()
    {
        return $this->ipv4_pool;
    }

    public function getIpv4Complete()
    {
        if ($this->network_ip) {
            return $this->network_ip->ip;
        }
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
        return 'custom';
    }

    public function getService()
    {
        return \App\Services\ClientService\ClientInternetService::class;
    }

    public function getRepository()
    {
        return \App\Http\Repository\ClientCustomServiceRepository::class;
    }

    public function getNameClient()
    {
        return $this->user;
    }

    public function getServiceNameAttribute()
    {
        return $this->custom->title;
    }


    public function getRequestAndStoreMethod()
    {
        $request = new ClientCustomServiceCreateRequest();
        $storeMethod = 'App\Http\Controllers\Module\Client\ClientCustomServiceController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod,
            'parameter_id' => 'client_id',
        ];
    }

    public function getPriceServiceAttribute()
    {
        $price = $this->custom->price ?? 0;
        $promotionService = new PromotionService();
        $promotion = $promotionService->getIfServiceHasPromotion($this);
        if ($promotion) {
            $price = $promotionService->getDiscountValuePromotion($this, $price);
        }
        return $price;
    }
}
