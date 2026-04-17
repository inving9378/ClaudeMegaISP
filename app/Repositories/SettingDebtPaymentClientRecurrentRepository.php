<?php

namespace App\Repositories;

use App\Models\SettingDebtPaymentClientRecurrent;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class SettingDebtPaymentClientRecurrentRepository extends BaseRepository
	{
		public function getModel(): SettingDebtPaymentClientRecurrent
		{
			return new SettingDebtPaymentClientRecurrent();
		}
	}
?>
