<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralAccountingOperation extends Model
{
    use HasFactory;

    protected $table = 'general_accounting_operations';

    protected $guarded = [];

    public function general_accounting_category()
    {
        return $this->belongsTo(GeneralAccountingCategory::class);
    }

    public function general_accounting_expense()
    {
        return $this->hasOne(GeneralAccountingExpense::class, 'operation_id', 'id');
    }

    public function general_accounting_income()
    {
        return $this->hasOne(GeneralAccountingIncome::class, 'operation_id', 'id');
    }
}
