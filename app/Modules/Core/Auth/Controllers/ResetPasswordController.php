<?php

namespace App\Modules\Core\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;

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
            ['token' => $token, 'email' => $request->email]
        );
    }
}
