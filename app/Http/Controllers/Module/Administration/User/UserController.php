<?php

namespace App\Http\Controllers\Module\Administration\User;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\State;
use App\Models\Municipality;
use App\Models\Colony;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;


class UserController extends Controller
{

    public function index()
    {
        return view('meganet.module.administration.user.listar');
    }

    public function getAllUsers(Request $request)
    {
        $search = $request->input('search', '');

        $users = User::where(function ($query) use ($search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('father_last_name', 'like', "%{$search}%")
                ->orWhere('mother_last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhere('city_municipality', 'like', "%{$search}%")
                ->orWhere('state_country', 'like', "%{$search}%")
                ->orWhere('code_postal', 'like', "%{$search}%")
                ->orWhere('rfc', 'like', "%{$search}%")
                ->orWhere('colony', 'like', "%{$search}%");
        })
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'client');
            })
            ->has('roles')
            ->orderBy('id')
            ->paginate(49);

        foreach ($users as $user) {
            $user->role_names = $user->getRoleNames();
        }

        return response()->json($users);
    }


    public function getRoles()
    {
        $roles = Role::all();
        return response()->json($roles);
    }

    public function create()
    {
        $sucursals = Sucursal::all();
        return view('meganet.module.administration.user.add', [
            'sucursals' => $sucursals
        ]);
    }

    public function getData($id)
    {
        $user = User::find($id);
        $password = base64_decode($user->password);
        $seller = $user->seller;
        return response()->json(['user' => $user, 'password' => $password, 'seller' => $seller]);
    }


    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'father_last_name' => 'required',
            'mother_last_name' => 'required',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required',
            'address' => 'required',
            'city_municipality' => 'required',
            'state_country' => 'required',
            'code_postal' => 'required',
            'rfc' => 'required',
            'login_user' => 'required|unique:users,login_user',
            'password' => 'required|min:8'
        ]);

        if ($request->hasFile('photography')) {
            $photography = $request->file('photography');

            $nameImage = Str::uuid() . "." . $photography->extension();

            $imageServer = Image::make($photography);
            $imageServer->fit(130, 130);

            $imagePath = public_path('perfiles') . '/' . $nameImage;
            $imageServer->save($imagePath);
        }
        try {
            DB::beginTransaction();
            $user = new User();
            $user->name = $request->name;
            $user->father_last_name = $request->father_last_name;
            $user->mother_last_name = $request->mother_last_name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->address = $request->address;
            $user->city_municipality = $request->city_municipality;
            $user->colony = $request->colony;
            $user->state_country = $request->state_country;
            $user->code_postal = $request->code_postal;
            $user->rfc = $request->rfc;
            $user->photography = $nameImage ?? null;
            $user->login_user = $request->login_user;
            $user->is_seller = $request->is_seller;
            $user->password = base64_encode($request->password);
            $user->sucursal_id = $request->sucursal;
            $user->color = $request->color;

            $user->save();

            $roles = [];
            if ($request->role) {
                $roles[] = \Spatie\Permission\Models\Role::findById($request->role)->name;
            }

            if ($request->is_seller == "1" || in_array('Vendedor', $roles)) {
                $roles[] = 'Vendedor';

                $seller = new Seller();
                $seller->status_id = $request->status_id;
                $seller->type_id = $request->type_id;
                $seller->user_id = $user->id;
                $seller->save();
            }
            $user->assignRole($roles);

            $roles = Role::whereIn('name', $roles)->get();
            foreach ($roles as $role) {
                $user->givePermissionTo($role->permissions()->pluck('name')->toArray());
            }

            DB::commit();
            return response()->json(['status' => 200, 'message' => 'Administrador creado con éxito!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 500, 'message' => $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $userModel = User::find($id);
        $user = $id;
        $password = base64_decode($userModel->password);
        $seller = $userModel->seller;
        $sucursals = Sucursal::all();
        return view('meganet.module.administration.user.edit', compact('user', 'password', 'seller', 'sucursals'));
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['status' => 500, 'message' => 'El usuario no existe!']);
        }
        $this->validate($request, [
            'name' => 'required',
            'father_last_name' => 'required',
            'mother_last_name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'address' => 'required',
            'city_municipality' => 'required',
            'state_country' => 'required',
            'code_postal' => 'required',
            'rfc' => 'required',
            'login_user'       => 'required|unique:users,login_user,' . $user->id,
            'password' => 'required|min:8'
        ]);


        if ($request->file('photography')) {
            $file = $request->file('photography');
            $file_name = Str::uuid() . "." . $file->extension();

            $file_server = Image::make($file);
            $file_server->fit(130, 130);
            $file_path = public_path('perfiles') . '/' . $file_name;
            $file_server->save($file_path);

            $user->photography = $file_name;
        }
        try {
            DB::beginTransaction();
            $user->name = $request->name;
            $user->father_last_name = $request->father_last_name;
            $user->mother_last_name = $request->mother_last_name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->address = $request->address;
            $user->city_municipality = $request->city_municipality;
            $user->colony = $request->colony;
            $user->state_country = $request->state_country;
            $user->code_postal = $request->code_postal;
            $user->rfc = $request->rfc;
            $user->login_user = $request->login_user;
            $user->is_seller = $request->is_seller;
            $user->password = base64_encode($request->password);
            $user->color = $request->color;
            $user->sucursal_id = $request->sucursal_id;
            $user->save();

            $roles = [];
            if ($request->role) {
                $roles[] = \Spatie\Permission\Models\Role::findById($request->role)->name;
            }

            $is_seller = $request->is_seller == "1" || in_array('Vendedor', $roles);

            if ($is_seller) {
                if (!in_array('Vendedor', $roles)) {
                    $roles[] = 'Vendedor';
                }

                $seller = Seller::firstOrNew(['user_id' => $id]);
                if ($seller->exists) {
                    // Actualizar registro existente
                    $seller->status_id = 1;
                    $seller->type_id = $request->type_id;
                } else {
                    // Manejar nuevo registro
                    $seller->status_id = 1;
                    $seller->type_id = $request->type_id;
                }
                $seller->save();
            } else {
                // Si is_seller es false, eliminar el rol de vendedor y el registro de vendedor si existe
                if ($user->hasRole('Vendedor')) {
                    $user->removeRole('Vendedor');
                }

                $seller = Seller::where('user_id', $id)->first();
                if ($seller) {
                    $seller->delete();
                }
            }

            $user->syncRoles($roles);

            if (isset($request->rule_id)) {
                $user->seller->updateRule($request->rule_id);
            }

            DB::commit();
            return response()->json(['status' => 200, 'message' => 'Administrador actualizado correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 500, 'message' => $e->getMessage()]);
        }
    }

    public function changePassword()
    {
        $user = Auth::user();
        return view('meganet.layout.change-password');
    }

    public function destroy($id)
    {
        $user = User::find($id);

        $roleName = 'super-administrator';

        if ($user->hasRole($roleName)) {
            if (User::role($roleName)->count() > 1) {
                $user->delete();
                return response()->json(['message' => 'Usuario eliminado correctamente'], 200);
            } else {
                return response()->json(['error' => 'No se puede eliminar el único super-administrador'], 400);
            }
        } else {
            $user->delete();
            return response()->json(['message' => 'Usuario eliminado correctamente'], 200);
        }
    }

    public function getStates()
    {
        $states = State::all();
        return response()->json($states);
    }

    public function getMunicipalities($id)
    {
        $municipalities = Municipality::where('state_id', $id)->get();
        return response()->json($municipalities);
    }

    public function getColonies($id)
    {
        $colonies = Colony::where('municipality_id', $id)->get();
        return response()->json($colonies);
    }

    public function inactiveOrActive($id)
    {
        $user = User::find($id);
        if ($user->active == 1) {
            $user->active = 0;
        } else {
            $user->active = 1;
        }
        $user->save();
        return response()->json(['message' => 'Usuario actualizado correctamente'], 200);
    }
}
