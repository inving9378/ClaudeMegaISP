<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralAccountingCategory extends Model
{
    use HasFactory;

    protected $table = 'general_accounting_categories';

    protected $guarded = [];

    public function general_accounting_type()
    {
        return $this->belongsTo(GeneralAccountingType::class, 'type_id', 'id');
    }

    public function scopeNotDefault($query)
    {
        return $query->where('is_default', false);
    }

    public function scopeIncome($query)
    {
        return $query->whereHas('general_accounting_type', function ($q) {
            $q->where('type', GeneralAccountingType::TYPE_INCOME);
        });
    }

    public function scopeExpense($query)
    {
        return $query->whereHas('general_accounting_type', function ($q) {
            $q->where('type', GeneralAccountingType::TYPE_EXPENSE);
        });
    }
}
