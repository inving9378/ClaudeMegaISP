<?php

namespace App\Modules\Core\Configuracion\Controllers\DataPlanPromotions;

use App\Http\Controllers\Controller;
use App\Http\Traits\DatatableCoreTrait;
use App\Models\DataPlanPromotion;
use App\Models\OltSpeedProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DataPlanPromotionsController extends Controller
{

    use DatatableCoreTrait;

    protected $model = null;

    public function __construct()
    {
        $model = 'DataPlanPromotion';
        $this->data['url'] = 'meganet.module.setting.data_plan_promotion';
        $this->data['title'] = 'Configuración de las Notificaciones de Finanzas';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->data['module'] = 'DataPlanPromotion';

        $this->model = DataPlanPromotion::class;
    }

    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->data['profiles'] = OltSpeedProfile::all();
        $this->includeLibraryDinamic('ConfigFinanceNotification');
        return view($this->data['url'] . '.index', $this->data);
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|unique:data_plan_promotion',
            'upload' => 'required',
            'download' => 'required'
        ];
        $model = new $this->data['model'];
        $data = $request->only($model->getFillable());
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            $validator->validate();
        }
        $model = $this->data['model']::create($data);
        return $model;
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'name' => ['required', Rule::unique('data_plan_promotion')->ignore($id)],
            'upload' => 'required',
            'download' => 'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        $data = $request->only((new $this->data['model'])->getFillable());
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            $validator->validate();
        }
        $model = $this->data['model']::findOrFail($id);
        $model->update($data);
        return response()->json([
            'success' => true,
            'message' => 'Los datos se han guardado correctamente.',
            'data' => $model->fresh()
        ]);
    }

    public function destroy($id)
    {
        $model = $this->data['model']::findOrFail($id);
        $model->delete();
        return response()->json([
            'success' => true,
        ]);
    }

    public function data(Request $request)
    {
        $columns = array_keys($this->getBaseColumnsByTable()['data_plan_promotion']);
        $order = $request->sortBy ?? $columns[0];
        $dir = $request->descending ? 'DESC' : 'ASC';
        $mapping = $this->getColumnMapping();
        $query  = $this->getGeneralQuery($columns, $mapping);
        $query = $this->applySearch($query, $request->search ?? null, $columns, $mapping);
        $query = $this->applySorting($query, $order, $dir, $mapping);
        if ($request->export) {
            $data = $query->get();
            return response()->json(
                [
                    'objects' => $data,
                    'total' => count($data)
                ]
            );
        }
        $data = $query->paginate(isset($request->rowsPerPage) ? $request->rowsPerPage : 20, ['*'], 'page', isset($request->page) ? $request->page : null);
        return response()->json(
            [
                'objects' => $data->items(),
                'total' => $data->total()
            ]
        );
    }

    protected function getBaseColumnsByTable()
    {
        return [
            'data_plan_promotion' => [
                'id' => ['searchable' => false],
                'name' => ['searchable' => true],
                'upload' => ['searchable' => true],
                'download' => ['searchable' => true],
                'duration' => ['searchable' => true],
                'type_duration' => ['searchable' => true],
                'defined_by_user' => ['searchable' => true],
            ]
        ];
    }
}
