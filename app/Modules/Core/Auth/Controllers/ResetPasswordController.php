<?php

namespace App\Modules\Core\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Las vistas de auth viven bajo el namespace del módulo (core-auth::), no en
     * resources/views/auth/passwords/ (que no existe en este proyecto).
     */
    public function showResetForm(Request $request)
    {
        $token = $request->route()->parameter('token');

        return view('core-auth::passwords.reset')->with(
            ['token' => $token, 'login_user' => $request->login_user]
        );
    }

    /**
     * Item roadmap #9990513 (Fase 2, hereda q1 de #9990512): el identificador es
     * login_user, no email — el broker resuelve al usuario por login_user y sigue
     * usando su email real internamente (getEmailForPasswordReset) para la fila de
     * password_resets, sin cambio ahí.
     */
    protected function rules()
    {
        return [
            'token' => 'required',
            'login_user' => 'required|string',
            // Regla igual a la ya usada en UserController (alta/edición de usuario),
            // decisión q3: reusar la política existente en vez de Password::defaults().
            'password' => 'required|min:8|confirmed',
        ];
    }

    protected function credentials(Request $request)
    {
        return $request->only(
            'login_user', 'password', 'password_confirmation', 'token'
        );
    }

    protected function sendResetFailedResponse(Request $request, $response)
    {
        if ($request->wantsJson()) {
            throw ValidationException::withMessages([
                'login_user' => [trans($response)],
            ]);
        }

        return redirect()->back()
                    ->withInput($request->only('login_user'))
                    ->withErrors(['login_user' => trans($response)]);
    }

    /**
     * Decisión q1 (#9990513, recomendada por el revisor y aprobada por Irving):
     * NO auto-login tras el reset (más auditable) — se omite el
     * `$this->guard()->login($user)` del trait base a propósito. El usuario
     * reingresa manualmente con su contraseña nueva desde /login.
     *
     * Decisión q4: se rota el remember_token (invalida el "recordarme"
     * persistente, igual que el default de Laravel). No existe en este
     * proyecto una infraestructura de sesiones por-usuario (driver de sesión
     * es 'file', sin tabla `sessions`) para además matar sesiones activas ya
     * abiertas en otros dispositivos — construir eso es un cambio de
     * arquitectura global de auth, fuera del alcance de esta fase de prueba.
     */
    protected function resetPassword($user, $password)
    {
        $this->setUserPassword($user, $password);

        $user->setRememberToken(Str::random(60));

        $user->save();

        event(new PasswordReset($user));
    }

    /**
     * Decisión q1: redirige a /login con mensaje flash en vez de autenticar
     * directo al dashboard.
     */
    protected function sendResetResponse(Request $request, $response)
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => trans($response)]);
        }

        return redirect()->route('login')
                    ->with('status', trans($response));
    }
}
