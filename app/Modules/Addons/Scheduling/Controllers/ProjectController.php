<?php

namespace App\Modules\Addons\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\scheduling\project\ProjectDatatableHelper;
use App\Http\Requests\module\scheduling\project\ProjectCreateRequest;
use App\Models\LogActivity;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    private $helper;

    public function __construct(ProjectDatatableHelper $helper)
    {
        $model = 'Project';
        $this->data['url'] = 'meganet.module.scheduling.project';
        $this->data['module'] = 'Project';
        $this->data['model'] = 'App\Models\\' . $model;
        $this->helper = $helper;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.index', $this->data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        return view($this->data['url'] . '.add', $this->data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(ProjectCreateRequest $request)
    {
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        $model = $this->data['model']::create($input);
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');
        return redirect()->back()->with('message', $this->data['module'] . 'Proyecto añadido correctamente');
    }

    public function show(LogActivity $log)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Client $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);

        return view($this->data['url'] . '.edit', $this->data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Client $client
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $model = $this->data['model']::find($id);
        $input = defined($this->data['model'] . '::MULTIPLE_RELATIONS') ?
            $request->except(collect($this->data['model']::MULTIPLE_RELATIONS)->keys()->toArray()) :
            $request->all();
        $this->saveRelationMultipleIfExist($this->data['model'], $model, $request, 'sync');
        return $model->update($input);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Client $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->data['model']::findOrFail($id)->delete();
        return redirect()->back()->with('message', $this->data['module'] . ' Eliminado Correctamente');
    }

    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    /**
     * Devuelve los usuarios del proyecto según sus equipos asignados.
     * Si el proyecto no tiene equipos configurados → fallback todos notClientRole.
     * Usado por CrearTareaModal para filtrar "Asignar a".
     */
    public function usersForProject(Project $project)
    {
        $teamIds = $project->teams()->pluck('teams.id');

        $query = User::query()->notClientRole()->select('id', 'name')->orderBy('name');

        if ($teamIds->isNotEmpty()) {
            $query->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $teamIds));
        }

        return response()->json($query->get());
    }

    /**
     * Sincroniza los equipos asociados a un proyecto.
     * Usado por ProjectCrud para guardar el mapping project↔teams.
     */
    public function syncTeams(Request $request, Project $project)
    {
        $teamIds = collect($request->team_ids ?? [])
            ->filter(fn ($id) => Team::where('id', $id)->exists())
            ->values();

        $project->teams()->sync($teamIds);

        return response()->json(['team_ids' => $project->teams()->pluck('teams.id')]);
    }

    /**
     * Devuelve los equipos actuales de un proyecto (para cargar en ProjectCrud).
     */
    public function getTeams(Project $project)
    {
        return response()->json($project->teams()->select('teams.id', 'teams.name')->get());
    }
}
