<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\BundleRepository;
use App\Http\Repository\ClientBundleServiceRepository;
use App\Http\Repository\ClientCustomServiceRepository;
use App\Http\Repository\ClientInternetServiceRepository;
use App\Http\Repository\ClientMainInformationRepository;
use App\Http\Repository\ClientRepository;
use App\Http\Repository\ClientVozServiceRepository;
use App\Http\Repository\CustomRepository;
use App\Http\Repository\InternetRepository;
use App\Http\Repository\VoiseRepository;
use App\Models\ClientBundleService;
use App\Support\Security\QueryGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchModelController extends Controller
{
    public function search(Request $request)
    {
        if (count($request->all())) {
            $model = $request->model;
            if ($model == 'App\Models\SpecialModelClientServices') {
                return $this->getClientServicesByClient($request);
            }
            // Hardening: el modelo, columnas y scope llegan del cliente → validar.
            $model = QueryGuard::model($model);
            $text = QueryGuard::identifier($request->text, true);
            $id = QueryGuard::identifier($request->id, true);
            if (isset($request->filter)) {
                foreach ($request->filter as $key => $filter) {
                    if ($key == 0) {
                        $model = $this->getModelFilterByRelationOrByField($model, $filter, true);
                        continue;
                    }
                    if (isset($filter['or_where'])) {
                        $column = QueryGuard::identifier($filter['or_where']);
                        $model = $model->orWhere($column, $filter['value'] ?? null);
                        continue;
                    }
                    if (isset($filter['or_where_field_relation'])) {
                        $relation = QueryGuard::relation($filter['or_where_field_relation']);
                        $field = QueryGuard::identifier($filter['field'] ?? null);
                        $model = $model->orWhereHas($relation, function ($query) use ($field, $filter) {
                            $query->where($field, $filter['value'] ?? null);
                        });
                    }

                    $model = $this->getModelFilterByRelationOrByField($model, $filter);
                }
                return $model->get()->pluck($text, $id);
            }
            if (isset($request->scope)) {
                $scope = QueryGuard::scope($model, $request->scope, false);
                return $model::$scope()->get()->pluck($text, $id);
            }
            if (isset($request->append)) {
                $append = QueryGuard::identifier($request->append);
                return $model::get()->pluck($append, $id);
            }
            return $model::get()->pluck($text, $id);
        }
    }

    //TODO optimizar la relacion en el filtro del componente
    public function searchWithoutId(Request $request, $id)
    {
        if (count($request->all())) {
            $model = QueryGuard::model($request->model);
            $text = QueryGuard::identifier($request->text, true);
            $keyId = QueryGuard::identifier($request->id, true);
            if (isset($request->filter)) {
                foreach ($request->filter as $key => $filter) {
                    if ($key == 0) {
                        if (isset($filter['field_relation'])) {
                            $relation = QueryGuard::relation($filter['field_relation']);
                            $field = QueryGuard::identifier($filter['field'] ?? null);
                            $model = $model::whereHas($relation, function ($query) use ($field, $filter) {
                                $query->where($field, $filter['value'] ?? null);
                            });
                        } else {
                            $field = QueryGuard::identifier($filter['field'] ?? null);
                            $model = $model::where($field, $filter['value'] ?? null);
                        }
                        continue;
                    }
                    $field = QueryGuard::identifier($filter['field'] ?? null);
                    $model = $model->where($field, $filter['value'] ?? null);
                }
                return $model->get()->pluck($text, $keyId);
            }
            return $model::where('id', '!=', $id)
                ->get()
                ->pluck($text, $keyId);
        }
    }

    protected function getModelFilterByRelationOrByField($model, $filter, $firstLoad = false)
    {
        if (isset($filter['field_relation'])) {
            $relation = QueryGuard::relation($filter['field_relation']);
            $field = QueryGuard::identifier($filter['field'] ?? null);
            $callback = function ($query) use ($field, $filter) {
                $query->where($field, $filter['value'] ?? null);
            };
            $model = $firstLoad
                ? $model::whereHas($relation, $callback)
                : $model->whereHas($relation, $callback);
        } else {
            $field = QueryGuard::identifier($filter['field'] ?? null);
            $model = $firstLoad
                ? $model::where($field, $filter['value'] ?? null)
                : $model->where($field, $filter['value'] ?? null);
        }
        return $model;
    }


    public function getClientServicesByClient($request)
    {
        $clientMainInformationRepository = new ClientMainInformationRepository();
        $clientRepository = new ClientRepository();
        $clientId = $clientMainInformationRepository->getClientIdByClientMainInformationId($request->id);

        if ($clientId) {
            $clientWithServices = $clientRepository->getServicesForClient($clientId);
            $services = ComunConstantsController::ALL_CLIENT_SERVICE;
            $arrayServices = [];

            foreach ($services as $service) {
                foreach ($clientWithServices->$service as $clientService) {
                    if (!$clientService->client_bundle_service_id) {
                        if ($service == 'bundle_service') {
                            $clientServiceRepository = new BundleRepository();
                            $arrayServices[$clientService->id] = $clientServiceRepository->getTitleById($clientService->bundle_id);
                        }
                        if ($service == 'internet_service') {
                            $clientServiceRepository = new InternetRepository();
                            $arrayServices[$clientService->id] = $clientServiceRepository->getTitleById($clientService->internet_id);
                        }
                        if ($service == 'voz_service') {
                            $clientServiceRepository = new VoiseRepository();
                            $arrayServices[$clientService->id] = $clientServiceRepository->getTitleById($clientService->voz_id);
                        }
                        if ($service == 'custom_service') {
                            $clientServiceRepository = new CustomRepository();
                            $arrayServices[$clientService->id] = $clientServiceRepository->getTitleById($clientService->custom_id);
                        }
                    }
                }
            }
            return $arrayServices;
        }
        return [];
    }

    public function longOptions(Request $request)
    {
        $model = QueryGuard::model($request->model);
        $text = QueryGuard::identifier($request->text, true);
        $id = QueryGuard::identifier($request->id, true);
        $filter = $request->filter;
        $filters = $request->filters;
        $page = $request->page;
        $pageSize = $request->pageSize;
        $offset = ($page - 1) * $pageSize;
        $scope = QueryGuard::scope($model, $request->scope);

        if ($filters && method_exists($model, 'scopeFilters')) {
            $query = $model::filters(['id', $text], $filter, $filters)
                ->select($id . ' as value', $text . ' as label');
            $totalCount = (clone $query)->count();
            $options = $query->offset($offset)->limit($pageSize)->get();
            return [
                'options' => $options,
                'totalCount' => $totalCount,
                'currentSelected' => isset($request->currentSelected)
                    ? $model::select($id . ' as value', $text . ' as label')->where($id, $request->currentSelected)->first()
                    : null,
            ];
        }

        if (isset($scope)) {
            $query = $model::$scope();
        } else {
            $query = $model::query();
        }
        if (isset($filter)) {
            $query = $query->where($text, 'like', '%' . $filter . '%');
        }
        $totalCount = (clone $query)->distinct()->count($id);
        $options = $query->toBase()->select($id . ' as value', $text . ' as label')->distinct()->offset($offset)->limit($pageSize)->get();
        return [
            'options' => $options,
            'totalCount' => $totalCount,
            'currentSelected' => isset($request->currentSelected) ? $model::select($id . ' as value', $text . ' as label')->where($id, $request->currentSelected)->first() : null
        ];
    }

    public function longOptionsClient(Request $request)
    {
        $validated = $request->validate([
            'model' => 'required|string',
            'text' => 'required|string',
            'id' => 'required|string'
        ]);

        $filter = $request->filter;
        $page = $request->page;
        $pageSize = $request->pageSize;
        $offset = ($page - 1) * $pageSize;
        $scope = $request->scope;

        // Hardening: model/text/id se interpolan en selectRaw/CONCAT → validar identificadores.
        $model = QueryGuard::model($validated['model']);
        $text = QueryGuard::identifier($validated['text']);
        $id = QueryGuard::identifier($validated['id']);
        $scope = QueryGuard::scope($model, $scope);

        if ($model === 'App\Models\InventoryItem') {
            return $this->getFilterInventoryItem($model, $text, $id, $page, $pageSize, $scope, $request);
        }

        // Consulta base con CONCAT para el label
        $query = isset($scope)
            ? $model::$scope()->selectRaw("`{$id}` as value, CONCAT(`{$id}`, ' - ', `{$text}`) as label")
            : $model::selectRaw("`{$id}` as value, CONCAT(`{$id}`, ' - ', `{$text}`) as label");

        // Filtro modificado para buscar tanto en id, text como en la combinación
        if (!empty($filter)) {
            $query->where(function ($q) use ($id, $text, $filter) {
                $q->where($id, 'like', '%' . $filter . '%')
                    ->orWhere($text, 'like', '%' . $filter . '%')
                    ->orWhereRaw("CONCAT(`{$id}`, ' - ', `{$text}`) LIKE ?", ['%' . $filter . '%']);
            });
        }

        $totalCount = $query->toBase()->count();

        $options = $query->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->toArray();

        $current = null;
        if (isset($currentSelected)) {
            $current = $model::selectRaw("`{$id}` as value, CONCAT(`{$id}`, ' - ', `{$text}`) as label")
                ->where($id, $currentSelected)
                ->first()?->toArray();
        }

        return [
            'options' => $options,
            'totalCount' => $totalCount,
            'currentSelected' => $current
        ];
    }

    private function getFilterInventoryItem($model, $text, $id, $page, $pageSize, $scope, $request)
    {
        $filter = $request->input('filter');
        $filter_where = $request->input('filter_where');
        $currentSelected = $request->input('currentSelected');

        // $model/$text/$id ya validados por el caller (longOptionsClient). $scope se
        // valida aquí porque se invoca como método estático ($model::$scope()).
        $scope = QueryGuard::scope($model, $scope, false);

        // Resolvemos los nombres de las tablas
        $modelInstance = new $model;
        $table = $modelInstance->getTable();
        $stocksTable = $modelInstance->stocks()->getRelated()->getTable();

        // Consulta base con join a stocks
        $query = $model::$scope()
            ->join($stocksTable, "{$table}.id", '=', "{$stocksTable}.inventory_item_id")
            ->selectRaw("`{$stocksTable}`.`{$id}` as value, CONCAT(`{$stocksTable}`.`{$id}`, ' - ', `{$table}`.`{$text}`) as label");

        // Filtro modificado para buscar tanto en id, text como en la combinación
        if (!empty($filter) && !is_array($filter)) {
            $query->where(function ($q) use ($id, $text, $filter, $table, $stocksTable) {
                $q->where("{$stocksTable}.{$id}", 'like', '%' . $filter . '%')
                    ->orWhere("{$table}.{$text}", 'like', '%' . $filter . '%')
                    ->orWhereRaw("CONCAT(`{$stocksTable}`.`{$id}`, ' - ', `{$table}`.`{$text}`) LIKE ?", ['%' . $filter . '%']);
            });
        }
        if (!empty($filter_where)) {
            foreach ($filter_where as $singleFilter) {
                $field = QueryGuard::identifier($singleFilter['field'] ?? null);
                $query->where($field, $singleFilter['value'] ?? null);
            }
        }

        $totalCount = $query->toBase()->count();
        $options = $query->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->toArray();

        $current = null;
        if (isset($currentSelected)) {
            $current = $model::$scope()
                ->join($stocksTable, "{$table}.id", '=', "{$stocksTable}.inventory_item_id")
                ->selectRaw("`{$stocksTable}`.`{$id}` as value, CONCAT(`{$stocksTable}`.`{$id}`, ' - ', `{$table}`.`{$text}`) as label")
                ->where("{$stocksTable}.{$id}", $currentSelected)
                ->first()?->toArray();
        }

        return [
            'options' => $options,
            'totalCount' => $totalCount,
            'currentSelected' => $current
        ];
    }

    public function longOptionsClientTask(Request $request)
    {
        $validated = $request->validate([
            'model' => 'required|string',
            'text' => 'required|string',
            'id' => 'required|string'
        ]);

        $filter = $request->filter;
        $page = $request->page;
        $pageSize = $request->pageSize;
        $scope = $request->scope;
        $append = $request->input('append');

        $model = QueryGuard::model($validated['model']);
        $text = QueryGuard::identifier($validated['text']);
        $id = QueryGuard::identifier($validated['id']);
        $scope = QueryGuard::scope($model, $scope);
        $append = QueryGuard::identifier($append, true);

        $displayId = QueryGuard::identifier($request->input('display_id'), true) ?? $id;

        $query = isset($scope) ? $model::$scope() : $model::query();

        if (!empty($filter)) {
            $query->where(function ($q) use ($text, $displayId, $filter) {
                $q->where($text, 'like', '%' . $filter . '%')
                    ->orWhere('father_last_name', 'like', '%' . $filter . '%')
                    ->orWhere('mother_last_name', 'like', '%' . $filter . '%')
                    ->orWhere($displayId, 'like', '%' . $filter . '%');
            });
        }

        $totalCount = (clone $query)->count();

        $records = $query->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $options = $records->map(function ($record) use ($id, $displayId, $append) {
            $labelText = $append ? $record->$append : $record->name;
            return [
                'value' => $record->$id,
                'label' => $record->$displayId . ' - ' . $labelText,
            ];
        })->toArray();

        $current = null;
        $currentSelected = $request->input('currentSelected');
        if (isset($currentSelected)) {
            $record = $model::find($currentSelected);
            if ($record) {
                $labelText = $append ? $record->$append : $record->name;
                $current = [
                    'value' => $record->$id,
                    'label' => $record->$displayId . ' - ' . $labelText,
                ];
            }
        }

        return [
            'options' => $options,
            'totalCount' => $totalCount,
            'currentSelected' => $current
        ];
    }

    public function longOptionsClientProspect(Request $request)
    {
        $validated = $request->validate([
            'model' => 'required|string',
            'text' => 'required|string',
            'id' => 'required|string'
        ]);

        $filter = $request->filter;
        $page = $request->page;
        $pageSize = $request->pageSize;
        $scope = $request->scope;
        $currentSelected = $request->input('currentSelected');

        $model = QueryGuard::model($validated['model']);
        $text = QueryGuard::identifier($validated['text']);
        $id = QueryGuard::identifier($validated['id']);
        $scope = QueryGuard::scope($model, $scope);

        $query = isset($scope)
            ? $model::$scope()->selectRaw("`{$id}` as value, CONCAT(`{$id}`, ' - ', `{$text}`, ' ', father_last_name, ' ', mother_last_name) as label")
            : $model::selectRaw("`{$id}` as value, CONCAT(`{$id}`, ' - ', `{$text}`, ' ', father_last_name, ' ', mother_last_name) as label");

        if (!empty($filter)) {
            $query->where(function ($q) use ($id, $text, $filter) {
                $q->where($id, 'like', '%' . $filter . '%')
                    ->orWhere($text, 'like', '%' . $filter . '%')
                    ->orWhere('father_last_name', 'like', '%' . $filter . '%')
                    ->orWhere('mother_last_name', 'like', '%' . $filter . '%')
                    ->orWhereRaw("CONCAT(`{$id}`, ' - ', `{$text}`, ' ', father_last_name, ' ', mother_last_name) LIKE ?", ['%' . $filter . '%']);
            });
        }

        $totalCount = $query->toBase()->count();

        $options = $query->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->toArray();

        $current = null;
        if (isset($currentSelected)) {
            $current = $model::selectRaw("`{$id}` as value, CONCAT(`{$id}`, ' - ', `{$text}`, ' ', father_last_name, ' ', mother_last_name) as label")
                ->where($id, $currentSelected)
                ->first()?->toArray();
        }

        return [
            'options' => $options,
            'totalCount' => $totalCount,
            'currentSelected' => $current
        ];
    }
}
