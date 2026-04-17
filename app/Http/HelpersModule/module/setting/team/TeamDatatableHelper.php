<?php


namespace App\Http\HelpersModule\module\setting\team;


use App\Http\HelpersModule\module\base\HelperModuleDatatable;
use App\Models\Nomenclature;
use App\Models\Team;
use Illuminate\Support\Arr;

class TeamDatatableHelper extends HelperModuleDatatable
{
    public function __construct()
    {
        parent::__construct(Team::class, 'Team');
    }

    public function ordering_query($start, $limit, $order, $dir, $filters = null)
    {
        if ($filters) {
            return $this->model::filters($this->columns, null, $filters)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        }
        return $this->model::select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function searching_query($start, $limit, $order, $dir, $search, $filters = null)
    {
        return $this->model::filters($this->columns, $search, $filters)
            ->select('*')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();
    }

    public function filtering_query($search)
    {
        return $this->model::filters($this->columns, $search)
            ->count();
    }

    public function transform($request)
    {
        $data = array();

        $type_modal_edit = $this->includeButtonEditTypeModalIfIsRequested($request)
            ? '_type_modal' : '';

        if (!empty($request['array'])) {
            foreach ($request['array'] as $key => $value) {
                $id = $value->id;
                foreach ($this->columns as $val) {
                    if ($val == 'users') {
                        $value->users = $this->getUsersByTeam($value->id);
                    }
                    $nestedData[$val] = $value->$val;
                }

                $info = [
                    'id' => $id,
                    'module' => $this->moduleName,
                    'group' => strtolower($this->moduleName),
                    'submodule' => strtolower($this->moduleName)
                ];

                if ($type_modal_edit) $info = Arr::add($info, 'modal', $request['request']->modal['modal']);
                $nestedData['action'] = view('meganet.shared.table.actions' . $type_modal_edit, $info)->toHtml();
                $data[] = $nestedData;
            }
        }

        return [
            "draw" => intval($request['request']->input('draw')),
            "recordsTotal" => intval($request['totalData']),
            "recordsFiltered" => intval($request['totalFiltered']),
            "data" => $data
        ];
    }

    public function getUsersByTeam($team_id)
    {
        // Buscar el equipo por ID
        $team = Team::find($team_id);

        if (!$team) {
            return ''; // Si no existe el equipo, retornar una cadena vacía
        }

        // Obtener los nombres de los usuarios asociados al equipo
        $users = $team->users->pluck('name')->toArray();

        // Tomar solo los primeros 5 usuarios
        $displayUsers = array_slice($users, 0, 5);

        // Convertir los nombres en una cadena separada por comas
        $stringUsers = implode(', ', $displayUsers);

        // Agregar "..." si hay más de 5 usuarios
        if (count($users) > 5) {
            $stringUsers .= '...';
        }

        return $stringUsers;
    }


    public function includeButtonEditTypeModalIfIsRequested($request)
    {
        return isset($request['request']->modal);
    }
}
