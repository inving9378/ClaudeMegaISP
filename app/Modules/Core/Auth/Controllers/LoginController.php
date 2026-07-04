<?php

namespace App\Modules\Core\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PostLoginRedirectService;
use App\Services\Security\PasswordService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Roles de STAFF autorizados a entrar al panel admin (guard web).
     * Solo estos roles pueden autenticar en el login admin: clientes y cuentas
     * sin rol de staff quedan bloqueados (p.ej. las cuentas espejo con rol 'client',
     * cuyo password es la Contraseña WEB del cliente, no deben acceder al admin).
     */
    private const STAFF_ROLES = [
        'super-administrator',
        'DESARROLLADOR',
        'Super Administrador',
        'Administrador',
        'Vendedor',
        'Mostrador',
        'TECNICO',
        'Almacen',
    ];

    /**
     * Resuelve la URL de destino post-login mediante PostLoginRedirectService:
     * 1. Última ruta visitada (si el usuario aún tiene permiso)
     * 2. Primera ruta de la lista de fallback que el usuario puede ver
     * 3. /sin-modulos si no tiene ningún módulo accesible
     */
    protected function redirectTo(): string
    {
        return app(PostLoginRedirectService::class)->resolve(Auth::user());
    }

    protected $fieldForLogin = 'email';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->middleware('guest')->except('logout');
        $credentials = $request->only('email', 'password');

        if (isset($credentials['email']) && !filter_var($credentials['email'], FILTER_VALIDATE_EMAIL)) {
            $this->fieldForLogin = 'login_user';
        }
    }

    public function showLoginForm()
    {
        return view('core-auth::login');
    }

    public function username()
    {
        return $this->fieldForLogin;
    }

    protected function validateLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);
    }

    protected function credentials(Request $request)
    {
        $crendentials = $request->only('email', 'password');
        if (!isset($crendentials[$this->fieldForLogin])) {
            $crendentials[$this->fieldForLogin] = $crendentials['email'];
            unset($crendentials['email']);
        }
        return $crendentials;
    }

    protected function attemptLogin(Request $request)
    {
        $user = User::where('email', $request->email)
            ->orWhere('login_user', $request->email)
            ->first();
        // Verificación híbrida: acepta bcrypt y el legacy base64.
        if ($user && PasswordService::check($request->password, $user->password)) {
            if (($user->estado ?? 'activo') !== 'activo') {
                return false;
            }
            // Gate de rol: solo staff entra al panel admin. Un usuario sin rol de
            // staff (p.ej. cuenta espejo con rol 'client') recibe fallo genérico de
            // credenciales (no-disclosure: no revela que el usuario/estado existe).
            if (! $user->hasAnyRole(self::STAFF_ROLES)) {
                return false;
            }
            // Upgrade-on-login: si seguía en base64 legacy, re-hashea a bcrypt.
            if (PasswordService::needsRehash($user->password)) {
                $user->password = PasswordService::make($request->password);
                $user->saveQuietly();
            }
            if ($user->isCounter()) {
                $user->createBox();
            }
            Auth::login($user);
            return true;
        }

        return false;
    }
}
