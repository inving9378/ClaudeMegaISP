<?php

namespace App\Modules\Core\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
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
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

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
            if (!$user->active) {
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
