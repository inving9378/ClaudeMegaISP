<?php

namespace App\Repositories\Sellers\Cuts;

use App\Models\CutInstallation;
use App\Repositories\BaseRepository;

class InstallationRepository extends BaseRepository
{
	public function getModel(): CutInstallation
	{
		return new CutInstallation();
	}
}
