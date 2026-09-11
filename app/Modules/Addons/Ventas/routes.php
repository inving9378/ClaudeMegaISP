<?php

use App\Modules\Addons\Ventas\Controllers\CustodiaController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del addon Ventas — motor de custodia de prospectos (item #9990780).
 *
 * UI mínima de solo lectura ("ver el estado de custodia: fecha límite, renovaciones usadas").
 * Gateada por ROL, mismo patrón que las pantallas de prueba de Payments
 * (app/Modules/Addons/Payments/routes.php, comentario "gateada por ROL... vía el middleware
 * Spatie 'role'"): no se crea un permiso Spatie nuevo en esta vuelta porque el alcance es
 * mínimo (solo lectura); si el módulo crece (alta de prospectos, renovación desde UI), ahí sí
 * toca su propio permiso.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente (ver
 * app/Modules/Addons/Inventario/routes.php, mismo comentario).
 */
// Nombre de ruta 'ventas-custodia.' (no 'ventas.') a propósito: ese prefijo ya lo usa el
// addon legacy Vendedores para sus rutas de reportes de venta (vendedores/routes.php,
// ventas.index/ventas.rankingSales/...) — mismo nombre de recurso, motor nuevo distinto.
Route::middleware(['web', 'auth', 'role:super-administrator|DESARROLLADOR'])
    ->prefix('ventas')
    ->name('ventas-custodia.')
    ->group(function () {
        Route::get('/custodias', [CustodiaController::class, 'index'])->name('custodias');
    });
