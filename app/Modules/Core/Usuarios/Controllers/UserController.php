<?php

namespace App\Modules\Core\Usuarios\Controllers;

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
use App\Models\Promotion;
use App\Services\Security\PasswordService;
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

        // Enrich: mark which users have a Talento collaborator profile (read-only join)
        $colaboradorMap = \Illuminate\Support\Facades\DB::table('talento_colaboradores')
            ->whereNull('deleted_at')
            ->pluck('id', 'user_id');  // [user_id => colaborador_id]

        foreach ($users as $user) {
            $user->role_names = $user->getRoleNames();
            // Aditivo: colaborador_id si existe perfil en Talento (null si no)
            $user->talento_colaborador_id = $colaboradorMap->get($user->id);
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
        // Sólo se puede "mostrar" la contraseña mientras siga en legacy base64.
        // Una vez migrada a bcrypt es irreversible → se devuelve vacío y el
        // admin sólo puede resetearla escribiendo una nueva.
        $password = PasswordService::legacyPlain($user->password) ?? '';
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
            $user->password = PasswordService::make($request->password);
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
            // GUARD Fase 1 — una cuenta-cliente (identidad en client_main_information)
            // no puede recibir roles de staff. Se pasa null como usuario: en store el
            // registro es nuevo (sin roles aún), así que sólo pesa la identidad de cliente.
            $guard = $this->enforceClientStaffGuard(null, $request->login_user, $roles, $request);
            if ($guard !== true) {
                DB::rollBack();
                return $guard;
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
        // Vacío si ya es bcrypt (irreversible); sólo legacy base64 es visible.
        $password = PasswordService::legacyPlain($userModel->password) ?? '';
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
            'password' => 'nullable|min:8'
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
            // Sólo cambia la contraseña si el form trae una nueva y distinta.
            if ($request->filled('password') && ! PasswordService::check($request->password, $user->password)) {
                $user->password = PasswordService::make($request->password);
            }
            $user->color = $request->color;
            $user->sucursal_id = $request->sucursal_id;
            $user->save();

            $roles = [];
            if ($request->role) {
                $roles[] = \Spatie\Permission\Models\Role::findById($request->role)->name;
            }

            // GUARD Fase 1 — una cuenta-cliente no puede recibir roles de staff.
            // Roles que el form intenta asignar (incluye 'Vendedor' si is_seller),
            // evaluados contra los roles ACTUALES del usuario (antes de mutarlos).
            $intendedRoles = $roles;
            if (($request->is_seller == "1" || in_array('Vendedor', $intendedRoles)) && !in_array('Vendedor', $intendedRoles)) {
                $intendedRoles[] = 'Vendedor';
            }
            $guardResult = $this->enforceClientStaffGuard($user, $user->login_user, $intendedRoles, $request);
            if ($guardResult !== true) {
                DB::rollBack();
                return $guardResult;
            }

            $is_seller = $request->is_seller == "1" || in_array('Vendedor', $roles);

            if ($is_seller && !in_array('Vendedor', $roles)) {
                $roles[] = 'Vendedor';
            }

            // Registro de Vendedor (Seller): alta/actualización o baja según is_seller.
            // El ROL 'Vendedor' se agrega/quita en el diff dirigido de más abajo.
            if ($is_seller) {
                $seller = Seller::firstOrNew(['user_id' => $id]);
                $seller->status_id = 1;
                $seller->type_id = $request->type_id;
                $seller->save();
            } else {
                $seller = Seller::where('user_id', $id)->first();
                if ($seller) {
                    $seller->delete();
                }
            }

            // PASO 2 — reemplazo de syncRoles (destructivo) por un diff DIRIGIDO.
            // Nunca se quitan estos roles aunque no vengan en el payload:
            //   super-administrator / DESARROLLADOR / ADMINISTRADOR_COMPLETO (sistema)
            //   client (rompería MegaFamilia y el portal de un staff-cliente).
            $neverRemove = ['super-administrator', 'DESARROLLADOR', 'ADMINISTRADOR_COMPLETO', 'client'];
            $selected = array_values(array_unique($roles));
            $current  = $user->roles()->pluck('name')->all();

            // Quitar: roles de staff actuales NO seleccionados y NO protegidos.
            $toRemove = array_values(array_filter($current, function ($r) use ($selected, $neverRemove) {
                return !in_array($r, $selected, true) && !in_array($r, $neverRemove, true);
            }));
            // Agregar: seleccionados que aún no tiene.
            $toAdd = array_values(array_diff($selected, $current));

            foreach ($toRemove as $roleName) {
                $user->removeRole($roleName);
            }
            foreach ($toAdd as $roleName) {
                $user->assignRole($roleName);
            }

            if (!empty($toAdd) || !empty($toRemove)) {
                \Log::info('Roles actualizados desde el form de Usuarios (diff dirigido)', [
                    'actor'     => Auth::user()?->login_user,
                    'target'    => $user->login_user,
                    'agregados' => $toAdd,
                    'quitados'  => $toRemove,
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }

            // Solo actualizar regla de comisión si llega un ID numérico válido y el usuario tiene vendedor.
            // isset($request->rule_id) era insuficiente: FormData convierte null → string "null", que
            // pasaba el isset y causaba FK violation → rollback silencioso de TODO (incluido el password).
            if (is_numeric($request->input('rule_id')) && $user->seller) {
                $user->seller->updateRule($request->input('rule_id'));
            }

            DB::commit();
            return response()->json(['status' => 200, 'message' => 'Administrador actualizado correctamente']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 500, 'message' => $e->getMessage()]);
        }
    }

    /**
     * ¿La cuenta debe tratarse como "solo-cliente" (espejo) para el guard de roles?
     * Diseño confirmado (Fase 1):
     *   es cuenta-cliente ⇔ login_user ∈ client_main_information.user
     *                        Y (si ya existe) tiene rol 'client' Y NO porta rol de staff.
     * En un espejo real esto coincide con tener el rol 'client' (1124/1125 en dev).
     * Un usuario nuevo (store) aún no tiene roles → sólo pesa la identidad de cliente.
     * Todos los roles se resuelven por NOMBRE.
     */
    private function isClientOnlyAccount(?User $user, string $loginUser): bool
    {
        // Identidad de cliente: el login existe en la ficha (client_main_information.user).
        $boundToClient = DB::table('client_main_information')->where('user', $loginUser)->exists();
        if (! $boundToClient) {
            return false;
        }

        // Usuario existente (update): combinación estricta — rol 'client' y sin rol de staff.
        // Un staff-cliente legítimo (técnico suscriptor con rol de staff) NO se trata como
        // solo-cliente → el guard no lo bloquea.
        if ($user && $user->exists) {
            $hasClientRole = $user->roles()->where('name', 'client')->exists();
            $hasStaffRole  = $user->roles()->where('name', '!=', 'client')->exists();
            return $hasClientRole && ! $hasStaffRole;
        }

        // Usuario nuevo (store): sin roles todavía → sólo la identidad de cliente.
        return true;
    }

    /**
     * Guard Fase 1: una cuenta-cliente no puede recibir roles de staff.
     * Devuelve true si la asignación es válida; si debe bloquearse devuelve el
     * JsonResponse listo para retornar (el caller hace rollback si aplica).
     *
     * OVERRIDE (opción B, verificado SIEMPRE en backend): un super-administrator puede
     * forzar la promoción si envía el flag explícito promote_client_to_staff=1. Nunca se
     * confía sólo en el frontend. Cada override se audita (Log::warning con contexto).
     *
     * @param  User|null  $user       usuario existente (update) o null si es nuevo (store)
     * @param  string     $loginUser  login_user del target (identidad de cliente vía CMI)
     * @param  array      $roles      roles que se intentan asignar (por nombre)
     */
    private function enforceClientStaffGuard(?User $user, string $loginUser, array $roles, Request $request)
    {
        $staffRolesRequested = array_values(array_filter($roles, fn ($r) => $r !== 'client'));

        if (empty($staffRolesRequested) || ! $this->isClientOnlyAccount($user, $loginUser)) {
            return true; // el guard no aplica
        }

        $actor    = Auth::user();
        $override = $actor
            && $actor->hasRole('super-administrator')
            && $request->boolean('promote_client_to_staff');

        if (! $override) {
            // Mismo patrón de respuesta que el resto del controller (HTTP 200 + status en el body).
            return response()->json([
                'status'  => 422,
                'message' => 'Una cuenta de cliente no puede recibir roles de staff.',
            ]);
        }

        \Log::warning('Promoción forzada de cuenta-cliente a staff (override super-administrator)', [
            'actor'           => $actor->login_user,
            'target'          => $loginUser,
            'roles_asignados' => $staffRolesRequested,
            'timestamp'       => now()->toDateTimeString(),
        ]);

        return true;
    }

    public function showPasswordForm()
    {
        return view('profile.password');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        // Verificación con PasswordService::check — acepta bcrypt y legacy base64
        // (mismo patrón que LoginController y UserController::update).
        if (! PasswordService::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Contraseña actual incorrecta.'], 422);
        }

        // PasswordService::make siempre produce bcrypt → además avanza la migración LFPDPPP.
        $user->password = PasswordService::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
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

    public function bloquear($id)
    {
        $user = User::findOrFail($id);
        // Alterna activo ↔ bloqueado (inactivo → activo si viene desde inactivo)
        $nuevoEstado = ($user->estado === 'bloqueado') ? 'activo' : 'bloqueado';
        $user->estado = $nuevoEstado;
        $user->active = ($nuevoEstado === 'activo') ? 1 : 0;
        $user->save(); // dispara UserVoipObserver
        return response()->json(['message' => 'Estado actualizado', 'estado' => $nuevoEstado], 200);
    }

    public function inactiveOrActive($id)
    {
        $user = User::find($id);
        // Toggle activo ↔ inactivo (bloqueado → activo al activar)
        $nuevoEstado = ($user->estado === 'activo') ? 'inactivo' : 'activo';
        $user->estado = $nuevoEstado;
        $user->active = ($nuevoEstado === 'activo') ? 1 : 0;
        $user->save();
        return response()->json(['message' => 'Usuario actualizado correctamente', 'estado' => $nuevoEstado], 200);
    }

    public function avaiablesPromotions($code)
    {
        $user = auth()->user();
        $promotions = [];
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            $promotions = Promotion::with(['promotionable'])->where('code', $code)->get();
        } else {
            $promotions = $user->avaiablesPromotions()->with(['promotionable'])->where('code', $code)->get();
        }
        return response()->json([
            'promotions' => $promotions
        ]);
    }
}
