<?php

namespace App\Modules\Core\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Las vistas de auth viven bajo el namespace del módulo (core-auth::), no en
     * resources/views/auth/passwords/ (que no existe en este proyecto).
     */
    public function showLinkRequestForm()
    {
        return view('core-auth::passwords.email');
    }

    /**
     * Item roadmap #9990512 (q2): el identificador es login_user, no email
     * (el login del sistema es por login_user). El broker resuelve igual el
     * email real del usuario vía User::getEmailForPasswordReset() para el envío.
     */
    protected function validateEmail(Request $request)
    {
        $request->validate(['login_user' => 'required|string']);
    }

    protected function credentials(Request $request)
    {
        return $request->only('login_user');
    }

    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        if ($request->wantsJson()) {
            throw ValidationException::withMessages([
                'login_user' => [trans($response)],
            ]);
        }

        return back()
            ->withInput($request->only('login_user'))
            ->withErrors(['login_user' => trans($response)]);
    }
}
