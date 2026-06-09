<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoRoleDepartment;
use Illuminate\Http\Request;

class TalentoColaboradorController extends Controller
{
    public function index()
    {
        return view('addon-talento::talento.index');
    }

    public function data(Request $request)
    {
        $this->authorize('talento.view');

        $q = TalentoColaborador::with(['user', 'supervisor.user'])
            ->when($request->search, fn($q, $s) =>
                $q->whereHas('user', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))
            )
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type,   fn($q, $t) => $q->where('type', $t))
            ->when($request->department, fn($q, $d) => $q->where('department', $d))
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 25);

        // Enrich each user with their Spatie roles (read-only) for the list
        $q->each(function ($col) {
            if ($col->user) {
                $col->user->role_names = $col->user->getRoleNames();
            }
        });

        return response()->json($q);
    }

    public function store(Request $request)
    {
        $this->authorize('talento.manage');

        $data = $request->validate([
            'user_id'       => 'required|exists:users,id|unique:talento_colaboradores,user_id',
            'type'          => 'required|in:interno,externo',
            'department'    => 'nullable|string|max:100',
            'supervisor_id' => 'nullable|exists:talento_colaboradores,id',
            'hire_date'     => 'nullable|date',
            'status'        => 'required|in:active,inactive,suspended',
            'base_salary'   => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
        ]);

        $colaborador = TalentoColaborador::create($data);

        return response()->json($colaborador->load('user'), 201);
    }

    public function show($id)
    {
        $this->authorize('talento.view');

        $colaborador = TalentoColaborador::with(['user', 'supervisor.user', 'subordinados.user'])
            ->findOrFail($id);

        // Enrich with Spatie roles (read-only display — roles are managed in Administradores)
        if ($colaborador->user) {
            $colaborador->user->role_names = $colaborador->user->getRoleNames();
        }

        return response()->json($colaborador);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('talento.manage');

        $colaborador = TalentoColaborador::findOrFail($id);

        $data = $request->validate([
            'type'          => 'sometimes|in:interno,externo',
            'department'    => 'nullable|string|max:100',
            'supervisor_id' => 'nullable|exists:talento_colaboradores,id',
            'hire_date'     => 'nullable|date',
            'status'        => 'sometimes|in:active,inactive,suspended',
            'base_salary'   => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
        ]);

        // Prevent self-referencing supervisor
        if (isset($data['supervisor_id']) && $data['supervisor_id'] == $colaborador->id) {
            return response()->json(['error' => 'Un colaborador no puede ser su propio supervisor.'], 422);
        }

        $colaborador->update($data);

        return response()->json($colaborador->load('user', 'supervisor.user'));
    }

    public function destroy($id)
    {
        $this->authorize('talento.manage');

        $colaborador = TalentoColaborador::findOrFail($id);
        $colaborador->delete();

        return response()->json(['ok' => true]);
    }

    public function roleDepartments()
    {
        $this->authorize('talento.manage');

        return response()->json(
            TalentoRoleDepartment::pluck('department', 'role_name')
        );
    }

    public function usersDisponibles(Request $request)
    {
        $this->authorize('talento.manage');

        $existingUserIds = TalentoColaborador::pluck('user_id');

        $users = User::whereNotIn('id', $existingUserIds)
            ->when($request->search, fn($q, $s) =>
                $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")
            )
            ->with('roles:name')
            ->select('id', 'name', 'email')
            ->limit(30)
            ->get()
            ->map(fn($u) => array_merge(
                $u->only('id', 'name', 'email'),
                ['role_names' => $u->roles->pluck('name')->values()->all()]
            ));

        return response()->json($users);
    }
}
