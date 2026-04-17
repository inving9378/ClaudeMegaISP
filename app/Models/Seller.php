<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seller extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'status_id',
        'type_id',
        'balance'
    ];

    public static function boot()
    {
        parent::boot();
        static::created(function ($obj) {
            $rule_id = request('rule_id');
            if (isset($rule_id)) {
                $rule = CommissionRule::find($rule_id);
                HistorySellerRule::create([
                    'seller_id' => $obj->id,
                    'rule_id' => $rule_id,
                    'data' => $rule
                ]);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function client_main_information()
    {
        return $this->hasMany(ClientMainInformation::class, 'seller_id');
    }

    public function status()
    {
        return $this->belongsTo(SellerStatus::class, 'status_id');
    }

    public function type()
    {
        return $this->belongsTo(SellerType::class, 'type_id');
    }

    public function commissionRules()
    {
        return $this->belongsToMany(CommissionRule::class, 'commissions_rules_sellers');
    }

    public function rules()
    {
        return $this->belongsToMany(HistorySellerRule::class, 'history_sellers_rules');
    }

    public function paymentSeller()
    {
        return $this->hasMany(PaymentSeller::class, 'seller_id');
    }

    public function discountBySales()
    {
        return $this->hasMany(Discount::class, 'seller_id');
    }

    public function transaction()
    {
        return $this->hasMany(TransactionSeller::class, 'seller_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class);
    }

    public function scopeDoesntHaveRules($query, $type)
    {
        return $query->where('type_id', $type)
            ->doesntHave('commissionRules')
            ->join('users', 'sellers.user_id', '=', 'users.id')
            ->select([
                'sellers.*',
                DB::raw('CONCAT(users.name, " ", users.father_last_name, " ", users.mother_last_name) as full_name')
            ]);
    }

    public function scopeHasRules($query, $type, $id)
    {
        return $query->where('type_id', $type)
            ->whereHas('commissionRules', function (Builder $query) use ($id) {
                $query->where('commission_rule_id', $id);
            })
            ->join('users', 'sellers.user_id', '=', 'users.id')
            ->select([
                'sellers.*',
                DB::raw('CONCAT(users.name, " ", users.father_last_name, " ", users.mother_last_name) as full_name')
            ]);
    }

    public function updateRule($rule_id)
    {
        $rule = HistorySellerRule::where('seller_id', $this->id)->latest()->first();
        if (!isset($rule) || (isset($rule) && $rule->rule_id != $rule_id)) {
            $rule = CommissionRule::find($rule_id);
            HistorySellerRule::create([
                'seller_id' => $this->id,
                'rule_id' => $rule_id,
                'data' => $rule
            ]);
        }
    }

    public function hasPaymentByRuleInPeriod($data, $type)
    {
        $id = $this->id;
        $payment = PaymentByRuleDetails::whereHas('payment', function ($query) use ($id) {
            $query->where('seller_id', $id);
        })->with(['payment' => function ($query) use ($id) {
            $query->where('seller_id', $id);
        }])->where('type', $type)->whereDate('start_date', $data['from'])->whereDate('end_date', $data['to'])->first();
        return $payment != null;
    }

    public function hasNotBeenPaid($sale)
    {
        $id = $this->id;
        $payment = PaymentByRuleDetails::whereHas('payment', function ($query) use ($id) {
            $query->where('seller_id', $id);
        })->with(['payment' => function ($query) use ($id) {
            $query->where('seller_id', $id);
        }])->where('type', 'additional_sales_commissions')->whereJsonContains('sales', [
            'id' => $sale->client_id
        ])->first();
        return $payment == null;
    }

    public function getSales()
    {
        return ClientMainInformation::whereHas('client')->where('seller_id', $this->user->id)->whereDate('activation_date', '>=', '2024-06-01')->get();
    }

    public function getTotalDebtBySales()
    {
        $discount = 0;
        foreach ($this->getSales() as $s) {
            $paymentData = $s->getPaymentData($this);
            if ($paymentData && !$paymentData['full']) {
                $discount += $paymentData['current_debt'];
            }
        }
        return $discount;
    }

    public function getTotalDiscountBySales()
    {
        $amount = $this->discountBySales()->sum('discount');
        return $amount;
    }

    public function getDebtBySales()
    {
        $sales = [];
        foreach ($this->getSales() as $s) {
            $paymentData = $s->getPaymentData($this);
            if ($paymentData && !$paymentData['full']) {
                $sales[] = [
                    'id' => $s->id,
                    'client_id' => $s->client_id,
                    'date' => Carbon::createFromFormat('Y-m-d', substr($s->activation_date, 0, 10))->format('d/m/Y'),
                    'client' => sprintf('%s %s %s', $s->name, $s->father_last_name, $s->mother_last_name),
                    ...$paymentData
                ];
            }
        }
        return $sales;
    }

    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {

                    if ($value == 'type') {
                        $query->orWhereHas('type', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'name') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'father_last_name') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('father_last_name', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'mother_last_name') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('mother_last_name', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'phone') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('phone', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'address') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('address', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'municipality') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('city_municipality', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'state') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('state_country', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'rfc') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('rfc', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'status') {
                        $query->orWhereHas('status', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                    } else {
                        if ($value !== 'action') {
                            $query->orWhere($value, 'like', '%' . $search . '%');
                        }
                    }
                }
            });
        } elseif (!empty($filter)) {
            $query =  $query->where(function ($query) use ($filter, $search, $columns) {
                // Aplica los filtros
                foreach ($filter as $field => $values) {
                }
            })->where(function ($query) use ($search, $columns) {
                // Aplica la búsqueda solo después de haber aplicado los filtros
                foreach (
                    collect($columns)->filter(function ($value) {
                        return $value != 'action';
                    })->toArray() as $value
                ) {
                    if ($value == 'type') {
                        $query->orWhereHas('type', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'name') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'father_last_name') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('father_last_name', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'mother_last_name') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('mother_last_name', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'phone') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('phone', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'address') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('address', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'municipality') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('city_municipality', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'state') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('state_country', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'rfc') {
                        $query->orWhereHas('user', function ($query) use ($search) {
                            $query->where('rfc', 'like', '%' . $search . '%');
                        });
                    } elseif ($value == 'status') {
                        $query->orWhereHas('status', function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                    } else {
                        if ($value !== 'action') {
                            $query->orWhere($value, 'like', '%' . $search . '%');
                        }
                    }
                }
            });
        }
        return $query;
    }
}
