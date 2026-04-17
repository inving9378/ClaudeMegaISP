<?php

namespace App\Http\Controllers\Module\Administration\Rol;

use App\Http\Controllers\Controller;
use App\Http\HelpersModule\module\administration\rol\RolDatatableHelper;
use App\Http\HelpersModule\module\administration\rol\RolModelHelper;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


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

        $role = Role::create(['name' => $request->input('name')]);

        $permissions = Permission::all();

        foreach ($permissions as $permission) {
            $role->givePermissionTo($permission);
        }

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
        return true;
    }

    /* public function store(Request $request){
        $this->validateFieldByRulesInTableFiledModules($this->data['module'],$request);
        return Role::create($request->all());
    } */

    public function destroy($id)
    {
        $role = Role::findById($id);

        $permissions = $role->permissions;

        $role->revokePermissionTo($permissions);

        $role->delete();

        return response()->json(['status' => 200, 'message' => 'Rol eliminado correctamente']);
    }
}
