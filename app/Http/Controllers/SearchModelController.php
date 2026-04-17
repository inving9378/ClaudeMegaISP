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
            $text = $request->text;
            $id = $request->id;
            if (isset($request->filter)) {
                foreach ($request->filter as $key => $filter) {
                    if ($key == 0) {
                        $model = $this->getModelFilterByRelationOrByField($model, $filter, true);
                        continue;
                    }
                    if (isset($filter['or_where'])) {
                        $model = $model->orWhere($filter['or_where'], $filter['value']);
                        continue;
                    }
                    if (isset($filter['or_where_field_relation'])) {
                        $model = $model->orWhereHas($filter['or_where_field_relation'], function ($query) use ($filter) {
                            $query->where($filter['field'], $filter['value']);
                        });
                    }

                    $model = $this->getModelFilterByRelationOrByField($model, $filter);
                }
                return $model->get()->pluck($text, $id);
            }
            if (isset($request->scope)) {
                $scope = $request->scope;
                return $model::$scope()->get()->pluck($text, $id);
            }
            if (isset($request->append)) {
                $append = $request->append;
                return $model::get()->pluck($append, $id);
            }
            return $model::get()->pluck($text, $id);
        }
    }

    //TODO optimizar la relacion en el filtro del componente
    public function searchWithoutId(Request $request, $id)
    {
        if (count($request->all())) {
            $model = $request->model;
            $text = $request->text;
            $keyId = $request->id;
            if (isset($request->filter)) {
                foreach ($request->filter as $key => $filter) {
                    if ($key == 0) {
                        if (isset($filter['field_relation'])) {
                            $model = $model::whereHas($filter['field_relation'], function ($query) use ($filter) {
                                $query->where($filter['field'], $filter['value']);
                            });
                        } else {
                            $model = $model::where($filter['field'], $filter['value']);
                        }
                        continue;
                    }
                    $model = $model->where($filter['field'], $filter['value']);
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
        if ($firstLoad) {
            if (isset($filter['field_relation'])) {
                $model = $model::whereHas($filter['field_relation'], function ($query) use ($filter) {
                    $query->where($filter['field'], $filter['value']);
                });
            } else {
                $model = $model::where($filter['field'], $filter['value']);
            }
        } else {
            if (isset($filter['field_relation'])) {
                $model = $model->whereHas($filter['field_relation'], function ($query) use ($filter) {
                    $query->where($filter['field'], $filter['value']);
                });
            } else {
                $model = $model->where($filter['field'], $filter['value']);
            }
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
        $model = $request->model;
        $text = $request->text;
        $id = $request->id;
        $filter = $request->filter;
        $page = $request->page;
        $pageSize = $request->pageSize;
        $offset = ($page - 1) * $pageSize;
        $scope = $request->scope;
        if (isset($scope)) {
            $options = $model::$scope()->toBase()->select($id . ' as value', $text . ' as label')->distinct();
        } else {
            $options = $model::toBase()->select($id . ' as value', $text . ' as label')->distinct();
        }
        if (isset($filter)) {
            $options = $options->where($text, 'like', '%' . $filter . '%');
        }

        $totalCount = $model::distinct()->count($id);

        $options = $options->offset($offset)->limit($pageSize)->get();

        if (isset($request->currentSelected)) {
        }

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

        ['model' => $model, 'text' => $text, 'id' => $id] = $validated;

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
            foreach ($filter_where as $filter) {
                $query->where($filter['field'], $filter['value']);
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
}
