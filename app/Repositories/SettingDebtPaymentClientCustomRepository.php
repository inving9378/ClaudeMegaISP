<?php

namespace App\Repositories;

use App\Models\SettingDebtPaymentClientCustom;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class SettingDebtPaymentClientCustomRepository extends BaseRepository
	{
		public function getModel(): SettingDebtPaymentClientCustom
		{
			return new SettingDebtPaymentClientCustom();
		}
	}
?>
