<?php

namespace App\Http\Traits;

trait DatatableCoreTrait
{

    protected function getGeneralQuery($columns, $mapping)
    {
        $joinConfig = $this->getJoinConfiguration();
        $selectedColumns = [];
        $requiredTables = [];
        $mainTableName = (new $this->model)->getTable();
        foreach ($columns as $alias) {
            if (isset($mapping[$alias])) {
                $columnData = $mapping[$alias];
                $selectedColumns[] = $columnData['column'];
                if ($columnData['table'] !== $mainTableName) {
                    $requiredTables[] = $columnData['table'];
                }
            }
        }
        $requiredTables = array_unique($requiredTables);
        if (empty($selectedColumns)) {
            return $this->model::select("{$mainTableName}.*")->limit(0);
        }
        $query = $this->model::select($selectedColumns);

        foreach ($joinConfig as $tableName => $config) {
            if (in_array($tableName, $requiredTables)) {
                call_user_func_array(
                    [$query, $config['type']],
                    [$tableName, $config['on'][0], $config['on'][1], $config['on'][2]]
                );
            }
        }
        return $query;
    }

    protected function applySearch($query, $search, $columns, $mapping)
    {
        if (!empty($search)) {
            $searchableColumns = array_filter(
                $mapping,
                function ($data, $alias) use ($columns) {
                    return ($data['searchable'] === true) && in_array($alias, $columns);
                },
                ARRAY_FILTER_USE_BOTH
            );
            if (!empty($searchableColumns)) {
                $query->where(function ($q) use ($search, $searchableColumns) {
                    $searchTerm = "%{$search}%";
                    foreach ($searchableColumns as $columnData) {
                        $columnName = $columnData['column'];
                        $realColumn = explode(' as ', $columnName)[0];
                        $q->orWhere($realColumn, 'like', $searchTerm);
                    }
                });
            }
        }
        return $query;
    }

    protected function applyFilters($query, $filters, $mapping)
    {
        if (!empty($filters) && is_array($filters)) {
            foreach ($filters as $f) {
                $value = $f['value'];
                if (is_null($value)) {
                    continue;
                }
                $alias = $f['column'];
                if (isset($mapping[$alias])) {
                    $columnName = $mapping[$alias]['column'];
                    $realColumn = explode(' as ', $columnName)[0];
                    $type = $f['type'] ?? 'default';
                    switch ($type) {
                        case 'date':
                            if (is_array($value)) {
                                $query->whereDate($realColumn, '>=', $value[0])->whereDate($realColumn, '<=', $value[1]);
                            } else {
                                $operator = $f['operator'] ?? '=';
                                $query->whereDate($realColumn, $operator, $value);
                            }
                            break;
                        case 'select':
                            $query->whereIn($realColumn, $value[0]);
                            break;
                        case 'range':
                            $query->whereBetween($realColumn, $value[0]);
                            break;
                        default:
                            $operator = $f['operator'] ?? '=';
                            $query->where($realColumn, $operator, $value);
                            break;
                    }
                }
            }
        }
        return $query;
    }

    protected function applySorting($query, $sortBy = null, $sortDirection, $mapping)
    {
        if ($sortBy && isset($mapping[$sortBy])) {
            $columnName = $mapping[$sortBy]['column'];
            $realColumn = explode(' as ', $columnName)[0];
            $query->orderBy($realColumn, $sortDirection);
        } else {
            $query->latest($this->model . '.id');
        }
        return $query;
    }

    protected function getColumnMapping()
    {
        $baseMapping = $this->getBaseColumnsByTable();
        $finalMapping = [];
        foreach ($baseMapping as $tableName => $columns) {
            foreach ($columns as $alias => $config) {
                $columnName = '';
                $isSearchable = false;
                if (is_numeric($alias)) {
                    $alias = $config;
                    $columnName = $config;
                } elseif (is_array($config) && isset($config['column'])) {
                    $columnName = $config['column'];
                    $isSearchable = $config['searchable'] ?? false;
                } elseif (is_array($config)) {
                    $columnName = $alias;
                    $isSearchable = $config['searchable'] ?? false;
                } else {
                    $columnName = $alias;
                }
                $finalMapping[$alias] = [
                    'column' => "{$tableName}.{$columnName} as {$alias}",
                    'table'  => $tableName,
                    'searchable' => $isSearchable,
                ];
            }
        }
        return $finalMapping;
    }

    protected function isQueryEmpty($query)
    {
        return $query->limit(1)->count() === 0;
    }

    protected function getBaseColumnsByTable()
    {
        return [];
    }

    protected function getJoinConfiguration()
    {
        return [];
    }
}
