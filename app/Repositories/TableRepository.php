<?php

namespace App\Repositories;

use App\Models\Table;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Francisco López Zetina
 */
	class TableRepository extends BaseRepository
	{
		public function getModel(): Table
		{
			return new Table();
		}

        public function findByType(string $type): ?Table
        {
            return $this->getModel()->where('type', $type)->first();
        }

        public function findByLabel(string $label): ?Table
        {
            return $this->getModel()->where('label', $label)->first();
        }

        public function findByModelClass(string $modelClass): Table
        {
            return $this->getModel()->where('model_class', $modelClass)->first();
        }

        public function getHasPosition(bool $hasPosition): Collection
        {
            return $this->getModel()
                        ->where('has_position', $hasPosition)
                        ->orderBy('label')
                        ->get();
        }

        public function getHasConnection(bool $hasConnection): Collection
        {
            return $this->getModel()
                        ->where('has_connection', $hasConnection)
                        ->orderBy('label')
                        ->get();
        }

        public function findByString(string $string): ?Table
        {
            $table = $this->findByType($string);

            if(!empty($table))
                return $table;

            $table = $this->findByLabel($string);

            if(!empty($table))
                return $table;

            return $this->findByModelClass($string);
        }
	}
?>
