<?php

use App\Modules\Core\Auth\Controllers\ForgotPasswordController;
use App\Modules\Core\Auth\Controllers\LoginController;
use App\Modules\Core\Auth\Controllers\RegisterController;
use App\Modules\Core\Auth\Controllers\ResetPasswordController;
use Illuminate\Support\Facades\Route;

/*
 * Reemplazo explícito de Auth::routes() de Laravel UI.
 *
 * Se replican exactamente los 9 endpoints y nombres de ruta que el helper emitía
 * (login/logout/register + flujo password reset), preservando route('login'),
 * route('password.request'), etc. usados por redirecciones existentes en
 * controladores y middleware.
 *
 * El middleware 'guest'/'auth' se aplica desde el constructor de cada
 * controlador (igual que antes con Auth::routes()).
 *
 * Email verification y password confirmation no estaban habilitados — Auth::routes()
 * se llamaba sin opciones — y se omiten aquí para no ampliar la superficie pública.
 *
 * Se envuelve en Route::middleware(['web']) porque BaseModuleServiceProvider
 * carga este archivo con loadRoutesFrom(), que NO aplica el grupo `web`
 * automáticamente (a diferencia de routes/web.php cargado por
 * RouteServiceProvider). Sin `web` no se ejecutan StartSession,
 * ShareErrorsFromSession ni VerifyCsrfToken, por lo que $errors no estaría
 * disponible en las vistas y el login form fallaría por CSRF.
 */

Route::middleware(['web'])->group(function () {
    Route::get('login',  [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('register',  [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [RegisterController::class, 'register']);

    Route::get('password/reset',          [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('password/email',         [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('password/reset/{token}',  [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('password/reset',         [ResetPasswordController::class, 'reset'])->name('password.update');
});
