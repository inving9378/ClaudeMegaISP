<?php

namespace App\Modules\Core\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
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
}
