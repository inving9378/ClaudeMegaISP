<?php

namespace App\Repositories\Sellers\Cuts;

use App\Models\CutSupplierExpense;
use App\Repositories\BaseRepository;

class SuppliersExpenseRepository extends BaseRepository
{
	public function getModel(): CutSupplierExpense
	{
		return new CutSupplierExpense();
	}
}
