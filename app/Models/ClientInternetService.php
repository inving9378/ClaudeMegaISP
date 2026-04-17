<?php

namespace App\Models;

use App\Http\Requests\module\client\ClientInternetServiceCreateRequest;
use App\Http\Traits\Models\Client\ClientInternetService\Scope\ScopeClientInternetService;
use App\Models\Interface\ServiceInterface;
use App\Services\PromotionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientInternetService extends BaseModel implements ServiceInterface
{
    use HasFactory, ScopeClientInternetService;

    const IPV4_ASSIGNMENT_STATIC = 'IP Estatica';
    const IPV4_ASSIGNMENT_POOL_IP = 'Pool IP';

    const STATE_ACTIVE = 'Activado';

    protected $guarded = [];
    protected $append = ['instalation_cost', 'has_active_instalation_cost', 'price_service', 'service_name'];

    public function modelName()
    {
        return 'ClientInternetService';
    }

    // relations

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

    public function network_ip()
    {
        return $this->belongsTo('App\Models\NetworkIp', 'ipv4');
    }

    public function network_ip_used_by()
    {
        return $this->belongsTo('App\Models\NetworkIp', 'id', 'used_by');
    }

    public function mikrotik_tariff_target_tail()
    {
        return $this->hasOne(MikrotikTariffTargetTail::class);
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

    public function service_in_address_list()
    {
        return $this->morphMany(ServiceInAddressList::class, 'serviceable');
    }

    // functions

    public function serviceHasIva()
    {
        return $this->internet->tax_include ?? false;
    }

    public function getTax()
    {
        return $this->internet->tax ?? 0;
    }

    public function getNewPriceByIva()
    {
        $tax = $this->internet->tax;
        if ($tax) {
            $price = $this->internet->price;
            return $price + ($price * $tax / 100);
        }
        return $this->internet->price;
    }

    public function isDeployed()
    {
        return $this->deployed;
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
        if ($this->network_ip_used_by) {
            return $this->network_ip_used_by->ip;
        }
        return null;
    }

    public function isIpv4AssigmentStatic()
    {
        return $this->ipv4_assignment == self::IPV4_ASSIGNMENT_STATIC;
    }

    public function isIpv4AssigmentPoolIp()
    {
        return $this->ipv4_assignment == self::IPV4_ASSIGNMENT_POOL_IP;
    }

    public function getInstalationCostAttribute()
    {
        return $this->internet->cost_instalation ?? 0;
    }

    public function getHasActiveInstalationCostAttribute()
    {
        return $this->internet->cost_instalation_enable;
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
        return 'internet';
    }

    public function getService()
    {
        return \App\Services\ClientService\ClientInternetService::class;
    }

    public function getRepository()
    {
        return \App\Http\Repository\ClientInternetServiceRepository::class;
    }

    public function getNameClient()
    {
        return $this->client_name ?? $this->user->name;
    }

    public function getServiceNameAttribute()
    {
        return $this->internet->title ?? '';
    }
    public function getRequestAndStoreMethod()
    {
        $request = new ClientInternetServiceCreateRequest();
        $storeMethod = 'App\Http\Controllers\Module\Client\ClientInternetServiceController@store';
        return [
            'request' => $request,
            'storeMethod' => $storeMethod,
            'parameter_id' => 'client_id',
        ];
    }

    public function getPriceServiceAttribute()
    {
        $price = $this->internet->price ?? 0;
        $promotionService = new PromotionService();
        $promotion = $promotionService->getIfServiceHasPromotion($this);

        if ($promotion) {
            $price = $promotionService->getDiscountValuePromotion($this, $price);
        }
        return $price;
    }
}
