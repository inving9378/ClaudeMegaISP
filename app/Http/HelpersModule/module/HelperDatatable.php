<?php

namespace App\Http\HelpersModule\module;

class HelperDatatable
{

    public function fetch_datatable_data($request)
    {
        if (empty($request->data['columns'])) {
            return [
                "draw" => 0,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => []
            ];
        }

        // if (isset($request->export)) {
        //     return $this->exportData($request);
        // }
        $columns = $request->data['columns'];
        $columns = $this->getColumns($columns);
        $filters = $this->getFiltersFromRequest($request);
        $idModule = $this->getIdModuleFromRequest($request);
        $totalData = $this->countTotalData($idModule, $filters, $request, $columns);
        $totalFiltered = $totalData;

        if ($request->limit == 0) {
            set_time_limit(0);
            ini_set('memory_limit', '8912M');
        }
        // Definir límites y paginación
        $limit = $request->limits == 0 ? $totalFiltered : $request->limits;
        $start = $request->start ?? 0;
        $order = isset($request->order) ? $request->order : 'id';
        $dir = $this->resolveDir($request->dir);

        // Obtener los datos según el estado de búsqueda
        $array = $this->hasSearchTerm($request)
            ? $this->searching_query($start, $limit, $order, $dir, $request->data['search'], $idModule ?? $filters, $columns)
            : $this->ordering_query($start, $limit, $order, $dir, $idModule ?? $filters, $columns);

        // Transformar y devolver los datos
        $param_resource = collect([
            'array' => $array,
            'totalData' => $totalData,
            'totalFiltered' => $totalFiltered,
            'request' => $request
        ]);

        return $this->transform($param_resource);
    }

    public function getColumns($columns)
    {
        $cols = [];
        foreach ($columns as $col) {
            $cols[] = $col['data'];
        }

        return $cols;
    }

    public function getFiltersFromRequest($request)
    {
        $filters = [];

        if ($this->hasFilters($request)) {
            if (is_array($request->data['filters'])) {
                $filters = $request->data['filters'];
            } else {
                $filters = $this->transformFilter($request->data['filters']);
            }
        }

        if ($this->hasAdditionalFilters($request)) {
            $filters[] = $request->data['additionalFilter'];
        }

        if (isset($request->persistentFilter) && !empty($request->persistentFilter)) {
            $formattedItems = $this->formatPersistentFilter($request->persistentFilter);
            foreach ($formattedItems as $item) {
                $filters[$item['key']] = $item['value'];
            }
        }
        return $filters;
    }

    private function formatPersistentFilter($persistentFilter)
    {
        $formatted = [];
        foreach ($persistentFilter as $key => $value) {
            $formattedValue = is_array($value) ? $value : [$value];
            $formatted[] = [
                'key'   => $key,
                'value' => $formattedValue
            ];
        }
        return $formatted; // Devuelve un array de arrays
    }

    public function countTotalData($idModule, $filters, $request, $columns = null)
    {
        if ($idModule) {
            return $this->count($idModule);
        }

        if (!empty($filters) && empty($request->data['search'])) {
            return $this->count($filters);
        }

        if (!empty($filters) && !empty($request->data['search'])) {
            return $this->filtering_query($request->data['search'], $columns, $filters);
        }

        if ($this->hasSearchTerm($request)) {
            return $this->filtering_query($request->data['search'], $columns);
        }

        return $this->count();
    }

    public function getIdModuleFromRequest($request)
    {
        return $this->hasIdModule($request) ? $request->data['idModule'] : null;
    }

    private function resolveDir($dir): string
    {
        if (is_bool($dir)) {
            return $dir ? 'ASC' : 'DESC';
        }
        return strtolower((string) $dir) === 'desc' ? 'DESC' : 'ASC';
    }

    private function hasIdModule($request)
    {
        return isset($request->data['idModule']);
    }

    private function hasFilters($request)
    {
        return !empty($request->data['filters']);
    }

    private function hasAdditionalFilters($request)
    {
        return !empty($request->data['additionalFilter']);
    }

    private function hasSearchTerm($request)
    {
        return !empty($request->data['search']);
    }


    private function exportData($request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '8912M');
        // Obtenemos y procesamos columnas seleccionadas
        $columns = $this->getColumns($request->data['columns']);

        $filters = $this->getFiltersFromRequest($request);
        $idModule = $this->getIdModuleFromRequest($request);

        $totalData = $this->countTotalData($idModule, $filters, $request, $columns);
        $limit = $request->limits == 0 ? $totalData : $request->limits;
        $start = $request->start ?? 0;

        $order = $request->order ?? $request->data['columns'][0]['data'];
        $dir = $this->resolveDir($request->dir);

        $queryResult = $this->ordering_query($start, $limit, $order, $dir, $idModule ?? $filters);

        // Transformamos los datos en el formato requerido y evitamos bucles anidados
        $data = $queryResult->map(function ($item) use ($columns) {
            return collect($columns)->mapWithKeys(function ($col) use ($item) {
                return [$col => $item->$col ?? null];
            })->all();
        });

        return ['data' => $data];
    }
}
