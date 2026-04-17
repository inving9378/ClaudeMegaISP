<?php

namespace App\Repositories;

use App\Models\ServiceInAddressList;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ServiceInAddressListRepository extends BaseRepository
	{
		public function getModel(): ServiceInAddressList
		{
			return new ServiceInAddressList();
		}
	}
?>
