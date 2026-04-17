<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralAccountingType extends Model
{
    use HasFactory;

    const TYPE_INCOME = 'income';
    const TYPE_EXPENSE = 'expense';

    protected $table = 'general_accounting_types';

    protected $guarded = [];
}
