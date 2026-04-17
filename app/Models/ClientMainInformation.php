<?php

namespace App\Models;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\ClientRepository;
use App\Http\Traits\Models\Client\ClientMainInformation\Scope\ScopeClientMainInformation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientMainInformation extends BaseModel
{
    use HasFactory, ScopeClientMainInformation, SoftDeletes;

    protected $guarded = [];
    protected $appends = ['client_name_with_fathers_names', 'partner_name', 'contract_months', 'contract_months_number', 'service', 'css_state'];
    protected $casts = [
        'distribute_commission' => 'json',
        'coords' => 'json'
    ];

    const STATE_ACTIVE = 'Activo';
    const STATE_BLOCKED = 'Bloqueado';

    const STATE_INACTIVE = 'Inactivo';

    const COLUMNS_TYPE_IN_THIS_TABLE = [
        'int' => [
            'user_id',
            'state_id',
            'municipality_id',
            'colony_id',
            'client_id',
        ],
        'enum' => [
            'estado' => [
                'Nuevo',
                'Activo',
                'Inactivo',
                'Bloqueado',
            ],
            'type_of_billing_id' => [
                '1' => '1 => Pagos Recurrentes',
                '2' => '2 => Prepagos (Diarios)',
                '3' => '3 => Prepagos (Personalizados)'
            ],
            'ift' => [
                'search' => [
                    'model' => 'App\Models\Ift',
                    'id' => 'id',
                    'text' => 'name',
                ]
            ],
            'partner_id' => [
                'search' => [
                    'model' => 'App\Models\Partner',
                    'id' => 'id',
                    'text' => 'name',
                ]
            ]
        ],
        'boolean' => [],
        'required' => []
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user_seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function colony()
    {
        return $this->belongsTo(Colony::class);
    }

    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    public function type_billing()
    {
        return $this->belongsTo(TypeBilling::class, 'type_of_billing_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function medium()
    {
        return $this->belongsTo(MediumOfSale::class, 'medium_sale_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function commissions_details()
    {
        return $this->hasMany(CommissionDetail::class);
    }

    public function payments_details()
    {
        return $this->hasMany(PaymentDetail::class);
    }

    public function duration_contract()
    {
        return $this->belongsTo(DurationContract::class, 'duration_contract_id');
    }

    public function distribution_commission()
    {
        return $this->hasOne(DistributionCommission::class, 'sale_id', 'id');
    }

    public function layer()
    {
        return $this->morphOne(MapLayer::class, 'layerable');
    }

    public function setTypeOfBillingAttribute($value)
    {
        $this->attributes['type_of_billing'] = intval($value);
    }

    public function getClientNameWithFathersNamesAttribute()
    {
        return $this->name .
            ' ' .
            $this->father_last_name .
            ' ' .
            $this->mother_last_name;
    }

    public function getPartnerNameAttribute()
    {
        return $this->partner()->first()->name ?? '';
    }

    public function getSellerNameAttribute()
    {
        return $this->user_seller()->first()->name ?? '';
    }
    public function getContractMonthsAttribute()
    {
        return $this->duration_contract()->first()->name ?? '';
    }
    public function getContractMonthsNumberAttribute()
    {
        return $this->duration_contract()->first()->duration ?? 0;
    }


    public function setDischargeDateAttribute($value)
    {
        $this->attributes['discharge_date'] = Carbon::now()->toDateTimeString();
    }

    public function getDischargeDateAttribute($value)
    {
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d\TH:i');
        } catch (\Exception $e) {
            return Carbon::createFromFormat('d/m/Y', $value)->format(
                'Y-m-d\TH:i'
            );
        }
    }

    public function getServiceAttribute()
    {
        $repository = new ClientRepository();
        $service = $repository->getCostAllService($this->client_id);
        return $service;
    }

    public function getCssStateAttribute()
    {
        $state = $this->estado;
        if (!isset($state)) {
            return null;
        }
        if ($state === "Activo") return "client-active";
        else if ($state === "Bloqueado") return "client-block";
        else if ($state === "Cancelado") return "client-cancel";
        return "client-inactive";
    }

    public function getStateName()
    {
        $stateName = $this->state()->first();
        if ($stateName) {
            return $this->state()->first()->name;
        }
        return '';
    }

    public function getColonyName()
    {
        $colonyName = $this->colony()->first();
        if ($colonyName) {
            return $this->colony()->first()->name;
        }
        return '';
    }

    public function getMunicipalityName()
    {
        $municipalityName = $this->municipality()->first();
        if ($municipalityName) {
            return $this->municipality()->first()->name;
        }
        return '';
    }

    public function isNotActive()
    {
        return $this->estado != ComunConstantsController::STATE_ACTIVE;
    }

    public function setDistributionCommission($config)
    {
        $commission = $this->distribution_commission;
        if (!$commission) {
            DistributionCommission::create(
                $config
            );
        }
    }

    public function getPaymentsInThreeFirstFiveMonths()
    {
        $service = $this->service;
        if ($service > 0) {
            $payments = [];
            $firstPayment = Payment::selectRaw("STR_TO_DATE(SUBSTRING_INDEX(date, ' ', 1), '%Y-%m-%d') as date")->where('paymentable_id', $this->client_id)->where('is_first_payment', true)->first();
            $months_payments = 0;
            $state = 'ok';
            $total_amount = 0;
            if ($firstPayment) {
                $currentDate = Carbon::now();
                $startDate = Carbon::createFromFormat('Y-m-d', $firstPayment->date)->firstOfMonth();
                $endDate = $startDate->copy()->addMonthsNoOverflow(4)->lastOfMonth();
                $payments = Payment::where('paymentable_id', $this->client_id)->whereDate('date', '>=', $startDate)->whereDate('date', '<=', $endDate)->orderBy('id')->get();
                $total_amount = $payments->sum('amount');
                $months_payments = $service > 0 ? floor($total_amount / $service) : 0;
                if ($endDate->gt($currentDate)) {
                    $state = 'in_term';
                } else if ($endDate->lt($currentDate) && $months_payments < 5) {
                    $state = 'delayed';
                } else {
                    $state = 'ok';
                }
            } else {
                $state = 'delayed';
            }
            $payments_descriptions = [];
            foreach ($payments as $p) {
                $payments_descriptions[] = [
                    'id' => $p->id,
                    'date' => Carbon::createFromFormat('Y-m-d', substr($p->date, 0, 10))->format('d/m/Y'),
                    'amount' => number_format($p->amount, 2, '.'),
                    'method' => $p->payment_method->type,
                    'period' => $p->payment_period,
                    'receipt' => $p->receipt,
                    'created_by' => $p->user->name
                ];
            }
            return [
                'service' => $service,
                'state' => $state,
                'completed' => $months_payments > 5 ? 5 : $months_payments,
                'pending' => (5 - $months_payments) > 0 ? (5 - $months_payments) : 0,
                'total_amount' => $total_amount,
                'payments' => $payments_descriptions
            ];
        }
        return null;
    }

    public function getDiscountByAdditionalSalePayment($seller)
    {
        $payment = PaymentByRuleDetails::with(['payment' => function ($query) use ($seller) {
            $query->where('seller_id', $seller->id);
        }])->where('type', 'additional_sales_commissions')->whereJsonContains('sales', [
            'id' => $this->id
        ])->first();
        $discount = 0;
        if ($payment) {
            $discount = collect($payment->sales)->where('id', $this->id)->first()['amount'];
        }
        return $discount;
    }

    public function getPaymentData($seller)
    {
        $payments = $this->getPaymentsInThreeFirstFiveMonths();
        if (isset($payments)) {
            $service = $payments['service'];
            $amount_by_client = $payments['total_amount'];
            $history = HistoryGeneralConfigurationRule::whereDate('created_at', '<=', Carbon::createFromFormat('Y-m-d', substr($this->activation_date, 0, 10)))->latest('created_at')->first();
            $installation_cost = $history->data['installation_cost'];
            $amount_by_seller = DiscountSale::where('sale_id', $this->id)->sum('discount');
            $discount_by_additional_sale = $this->getDiscountByAdditionalSalePayment($seller);
            if (floor($amount_by_client / $service) >= 3 || (($amount_by_client + $amount_by_seller) >= ($installation_cost + $discount_by_additional_sale)) || ($payments['state'] != 'delayed')) {
                return [
                    'full' => true,
                ];
            }
            $discount_by_client = $installation_cost - $amount_by_client;
            $current_debt = $discount_by_additional_sale + $installation_cost - $amount_by_seller - $amount_by_client;
            return [
                'full' => false,
                'service' => $service,
                'installation_cost' => $installation_cost,
                'amount_by_client' => $amount_by_client,
                'discount_by_client' => $discount_by_client,
                'discount_by_additional_sale' => $discount_by_additional_sale,
                'amount_by_seller' => $amount_by_seller,
                'total_discount' => $discount_by_client + $discount_by_additional_sale,
                'current_debt' => $current_debt,
                'to_pay' => $current_debt,
                'rule_id' => $history->id,
                'payments' => $payments['payments']
            ];
        }
        return null;
    }

    public function hasBeenDiscount()
    {
        $payment = DiscountSale::firstWhere('sale_id', $this->id);
        return $payment != null;
    }

    public function endPaymentByDistributtorsCommission()
    {
        $total = PaymentByRuleDetails::where('type', 'distributors_commission')->whereJsonContains('sales', [
            'client' => $this->client_id
        ])->count();
        if ($this->contract_months_number == $total) {
            return true;
        }
        $initial = $this->hasInitialPaymentByDistributtorsCommission();
        if ($this->contract_months_number - $total == 1 && !$initial) {
            return true;
        }
        return false;
    }

    public function hasInitialPaymentByDistributtorsCommission()
    {
        return PaymentByRuleDetails::where('type', 'distributors_commission')->whereJsonContains('sales', [
            'client' => $this->client_id,
            'initial' => true
        ])->first() != null;
    }
}
