<?php

use App\Modules\Addons\Domiciliacion\Controllers\DomiciliacionController;
use App\Modules\Addons\Domiciliacion\Controllers\EnrollmentLinkController;
use Illuminate\Support\Facades\Route;

// ── Portal Cliente — Domiciliación ────────────────────────────────────────────
Route::prefix('portal/domiciliacion')->name('portal.domiciliacion.')
    ->middleware(['web', 'auth.portal'])
    ->group(function () {
        Route::get('/',          [DomiciliacionController::class, 'portalShow'])->name('show');
        Route::post('/',         [DomiciliacionController::class, 'portalStore'])->name('store');
        Route::delete('/{card}', [DomiciliacionController::class, 'portalDestroy'])->name('destroy');
    });

// ── Admin — Domiciliación ─────────────────────────────────────────────────────
Route::prefix('domiciliacion')->name('admin.domiciliacion.')
    ->middleware(['web', 'auth', 'check_route_permission'])
    ->group(function () {
        Route::get('/clientes/{clientId}',             [DomiciliacionController::class, 'adminShow'])->name('show');
        Route::post('/clientes/{clientId}',            [DomiciliacionController::class, 'adminStore'])->name('store');
        Route::delete('/clientes/{clientId}/{cardId}', [DomiciliacionController::class, 'adminDestroy'])->name('destroy');

        Route::post('/clientes/{clientId}/liga',       [EnrollmentLinkController::class, 'generate'])->name('liga.generate');
    });

// ── Pública — Liga de enrolamiento (sin auth) ─────────────────────────────────
Route::prefix('d')->name('domiciliacion.public.')
    ->middleware(['web'])
    ->group(function () {
        Route::get('/{token}',  [EnrollmentLinkController::class, 'show'])->name('show');
        Route::post('/{token}', [EnrollmentLinkController::class, 'store'])->name('store');
    });
