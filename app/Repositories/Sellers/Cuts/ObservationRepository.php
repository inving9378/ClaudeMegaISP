<?php

namespace App\Repositories\Sellers\Cuts;

use App\Models\CutObservation;
use App\Repositories\BaseRepository;

class ObservationRepository extends BaseRepository
{
	public function getModel(): CutObservation
	{
		return new CutObservation();
	}
}
