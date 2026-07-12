<?php

namespace App\Modules\Core\Usuarios\Controllers;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\administration\rol\RolDatatableHelper;
use App\Http\HelpersModule\module\administration\rol\RolModelHelper;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;


class RolController extends Controller
{
    private $helper, $model_helper;
    public function __construct(RolModelHelper $model_helper, RolDatatableHelper $helper)
    {
        $this->data['url'] = 'meganet.module.administration.rol';
        $this->model_helper = $model_helper;
        $this->data['fields'] = $this->model_helper::FIELDS;
        $this->data['columns'] = ['data' => $this->model_helper::DATATABLE_FIELDS];
        $this->data['module'] = 'Role';
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
        $this->includeLibraryDinamic('Role');
        return view($this->data['url'] . '.listar',$this->data);
    }

    public function get()
    {
        $roles = Role::all();
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|unique:roles,name',
        ]);

        Role::create(['name' => $request->input('name')]);

        return response()->json(['status' => 200, 'message' => 'Rol creado correctamente']);
    }


    public function table(Request $request)
    {
        return $this->helper->fetch_datatable_data($request);
    }

    public function edit($id)
    {
        $role = Role::find($id);
        return response()->json($role);
    }

    public function updateRole(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $role = Role::find($id);
        $role->name = $request->input('name');
        $role->save();

        return response()->json(['status' => 200, 'message' => 'Rol actualizado correctamente']);
    }


    public function update(Request $request, $rol){
        $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        $role = Role::find($rol);
        foreach ($request->all() as $key => $val){
            $val == true ? $role->givePermissionTo($key) : $role->revokePermissionTo($key);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        return true;
    }

    /* public function store(Request $request){
        $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        return Role::create($request->all());
    } */

    /**
     * Roles estructurales que jamás se pueden eliminar (lockout / rotura de módulos que
     * dependen de ellos, ej. portal cliente sobre el rol "client"). Ver Hoja de Ruta #220.
     */
    private const PROTECTED_ROLES = ['super-administrator', 'DESARROLLADOR', 'ADMINISTRADOR_COMPLETO', 'client'];

    public function destroy($id)
    {
        $role = Role::find($id);

        if (!$role) {
            abort(404, 'Rol no encontrado');
        }

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            abort(422, 'Este rol es protegido y no se puede eliminar');
        }

        if (\App\Models\User::role($role->name)->exists()) {
            abort(422, 'El rol tiene usuarios asignados y no se puede eliminar');
        }

        $permissions = $role->permissions;

        $role->revokePermissionTo($permissions);

        $role->delete();

        return response()->json(['status' => 200, 'message' => 'Rol eliminado correctamente']);
    }
}
