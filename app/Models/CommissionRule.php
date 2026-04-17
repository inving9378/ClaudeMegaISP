<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CommissionRule extends BaseModel
{
    use HasFactory;
    protected $table = 'commissions_rules';

    protected $fillable = [
        'name',
        'type_of_seller',
        'fixed_salary',
        'sales_commission',
        'sales_commission_type',
        'additional_sales_commissions',
        'amount',
        'fixed_sales_commission',
        'number_of_prospects',
        'period',
        'zone',
        'iva',
        'minimum_sales',
        'total_bonus',
        'number_sales_required',
        'conditions',
        'commission_percentage_additional',
        'fixed_sales_commission_additional',
        'installation_cost',
        'commission_percentage',
        'is_fixed_salary',
        'total_comission',
        'number_sales_bonus_commission_required',
        'penalty',
        'fixed_sales_commission_distribuitors',
        'fixed_sales_commission_distribuitors_percent',
        'conditions_comission',
        'selected_fields',
        'monthly_bonus',
        'distributors_commission'
    ];

    protected $casts = [
        'selected_fields' => 'json',
        'monthly_bonus' => 'json',
        'distributors_commission' => 'json',
        'additional_sales_commissions' => 'json',
        'is_fixed_salary' => 'boolean'
    ];

    protected $appends = ['sellers_id', 'sales_commission_format', 'additional_sales_commissions_format', 'fixed_salary_format', 'distributors_commission_format', 'monthly_bonus_format'];

    const MULTIPLE_RELATIONS = [
        'sellers' => 'sellers'
    ];

    public function comments(): HasMany
    {
        return $this->hasMany(Seller::class);
    }

    public function sellers()
    {
        return $this->belongsToMany(Seller::class, 'commissions_rules_sellers');
    }

    public function updateRulesHistory($oldSellers = [])
    {
        $sellers = [];
        if ($this->wasChanged() || $this->wasRecentlyCreated) {
            $sellers = $this->sellers()->get();
        } else {
            $sellers = $this->sellers()->get()->diff($oldSellers);
        }
        foreach ($sellers as $s) {
            HistorySellerRule::create([
                'rule_id' => $this->id,
                'seller_id' => $s->id,
                'data' => $this,
            ]);
        }
        return true;
    }

    public function scopeFilters($query, $columns, $search = null, $filter = null)
    {
        if (isset($search) && empty($filter)) {
            $query->where(function ($query) use ($search, $columns) {
                foreach ($columns as $value) {

                    if ($value == 'sellers_count') {
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
                    if ($value == 'sellers_count') {
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

    public function getSellersIdAttribute()
    {
        return $this->sellers()->get()->pluck('id');
    }


    public function getSalesCommissionFormatAttribute()
    {
        if ($this->sales_commission > 0) {
            return $this->sales_commission_type == '$' ? ('$' . $this->sales_commission) : ($this->sales_commission . ' %');
        }
        return '';
    }

    public function getSellersCountAttribute()
    {
        return $this->sellers()->join('users', 'sellers.user_id', '=', 'users.id')
            ->select([
                DB::raw('CONCAT(users.name, " ", users.father_last_name, " ", users.mother_last_name) as full_name')
            ])->get();
    }

    public function getAdditionalSalesCommissionsFormatAttribute()
    {
        $commission = $this->additional_sales_commissions;
        $result = '';
        if (isset($commission)) {
            $result = sprintf('Aplicar descuento IVA: %s, Comisión adicional: ', ($commission['iva'] ? 'Si' : 'No'));
            $bonus = $commission['bonus'];
            if ($commission['type'] == '$') {
                $result = "$result $$bonus";
            } else {
                $result = "$result $bonus%";
            }
            $sales = $this->minimum_sales;
            $result = "$result por venta, Ventas requeridas: $sales";
        }
        return $result;
    }

    public function getFixedSalaryFormatAttribute()
    {
        $result = '';
        if ($this->fixed_salary > 0) {
            if ($this->is_fixed_salary) {
                $result = sprintf('Salario fijo de $%d', $this->fixed_salary);
            } else {
                $result = sprintf('Salario fijo de $%d', $this->fixed_salary);
                if ($this->minimum_sales > 0 && $this->number_of_prospects > 0) {
                    $result = sprintf('%s si %d venta(s) y %d prospecto(s)', $result, $this->minimum_sales, $this->number_of_prospects);
                } else if ($this->minimum_sales > 0) {
                    $result = sprintf('%s si %d venta(s)', $result, $this->minimum_sales);
                } else if ($this->number_of_prospects > 0) {
                    $result = sprintf('%s si %d prospecto(s)', $result, $this->number_of_prospects);
                }
            }
        }
        return $result;
    }

    public function getDistributorsCommissionFormatAttribute()
    {
        $result = '';
        $commission = $this->distributors_commission;
        if (isset($commission)) {
            $result = sprintf('Comisión inicial por venta: $%s , Mínimo de ventas: %d, Aplicar descuento IVA: %s, Bonos: ', $commission['initial'], $commission['sales'], $commission['iva'] ? 'Si' : 'No');
            $bonus = $commission['bonus'];
            $contract = null;
            foreach ($bonus as $b) {
                if (!$contract || $contract->id != $b['contract']) {
                    $contract = DurationContract::find($b['contract']);
                }
                $c = $b['commission'];
                $result = "$result $c% si contrato de $contract->name,";
            }
            $result = substr($result, 0, strrpos($result, ','));
        }
        return $result;
    }

    public function getMonthlyBonusFormatAttribute()
    {
        $result = '';
        $commission = $this->monthly_bonus;
        if (isset($commission)) {
            $result = sprintf('Aplicar descuento IVA: %s, Bonos: ', $commission['iva'] ? 'Si' : 'No');
            foreach ($commission['bonus'] as $b) {
                $result = sprintf($result . '$%d si %d ventas, ', $b['bonus'], $b['sales']);
            }
            $result = substr($result, 0, strrpos($result, ','));
        }
        return $result;
    }
}
