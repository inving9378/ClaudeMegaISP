<?php

namespace App\Modules\Core\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

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
}
