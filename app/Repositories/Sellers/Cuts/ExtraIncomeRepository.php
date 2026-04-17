<?php

namespace App\Repositories\Sellers\Cuts;

use App\Models\CutExtraIncome;
use App\Repositories\BaseRepository;

class ExtraIncomeRepository extends BaseRepository
{
	public function getModel(): CutExtraIncome
	{
		return new CutExtraIncome();
	}
}
