<?php

// Administration\User\UserController migrado a App\Modules\Core\Usuarios (rutas en módulo)
// Mapas controllers (infraestructura física) migrados a App\Modules\Addons\Mapas\Controllers\Mapas (rutas en módulo)
// Maps controllers (sub-namespace Geo) migrados a App\Modules\Addons\Mapas\Controllers\Geo
// Vendors controllers migrados a App\Modules\Addons\Vendedores\Controllers\Vendors (rutas en módulo)
// OLTs controllers migrados a App\Modules\Addons\GestionRed\Controllers\OLTs (rutas en módulo)
// Sellers controllers migrados a App\Modules\Addons\Vendedores\Controllers\Sellers (rutas en módulo)
// Imports de Module/Setting movidos a app/Modules/Core/Configuracion/routes.php
use App\Http\Controllers\RegisterVendorController;
use App\Models\TaskNotification;
use App\Notifications\StandardNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InternetConsumptionRadiusController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

include('script_db.php');

/*
 * Rutas modulares
 * ---------------
 * Cada módulo bajo app/Modules/{Core,Addons}/<Slug>/routes.php se carga
 * automáticamente desde su ModuleServiceProvider (registrado por AppServiceProvider).
 * No es necesario incluirlas aquí. Las rutas globales legacy permanecen abajo
 * hasta que cada módulo sea migrado en PRs posteriores.
 */

// Auth::routes() reemplazado por app/Modules/Core/Auth/routes.php
// (registra los mismos 9 endpoints + nombres login/logout/register/password.*)
//Language Translation
Route::group(['middleware' => ['auth']], function () {
    Route::get('script', 'TestScriptController@script');
    Route::get('log-client', 'TestScriptController@logClient');
    Route::get('information-dev', 'TestScriptController@getInformationDevelopment');
    Route::get('data-client-mikrotik/{id}', 'TestScriptController@getDataToMikrotikByClientId');
    Route::get('data-client-mikrotik-by-ip', 'TestScriptController@getDataToMikrotikByIp');
    Route::get('libera-ip/{id}', 'TestScriptController@liberaIp');
    Route::prefix('admin/consumo')->group(function () {
        Route::get('/', [InternetConsumptionRadiusController::class, 'index'])
            ->name('consumption.index');
        Route::get('/{username}', [InternetConsumptionRadiusController::class, 'show'])
            ->name('consumption.show');
    });
    Route::group(['middleware' => ['check_route_permission']], function () {
        Route::group(['namespace' => 'Module'], function () {
            // Module Configuracion migrado a app/Modules/Core/Configuracion/routes.php

            // Module Administracion — TODAS las rutas migradas a módulos:
            //   - dashboard + user/rol/permisos/addresses      -> Core/Usuarios/routes.php
            //   - socios + ift + metotdo-de-pago               -> Core/Configuracion/routes.php
            //   - ubicacion + sucursal + estado + municipio    -> Core/Localizacion/routes.php
            //   - colonia                                       -> Core/Localizacion/routes.php
            //   - activity_log                                  -> Core/Auditoria/routes.php
            //   - document_template + document_type_template    -> Core/Documentos/routes.php
            //   - documentation/* (menu/submenu/content)        -> Core/Documentacion/routes.php

            // Rutas `inventory/*` migradas a app/Modules/Addons/Inventario/routes.php
            // Rutas `sellers/*` migradas a app/Modules/Addons/Vendedores/routes.php

            // Rutas `message/*` migradas a app/Modules/Addons/Mensajes/routes.php

            // Rutas internet/paquetes/voz/custom (Module Plan) migradas a app/Modules/Addons/Planes/routes.php

            // Rutas `tickets/*` migradas a app/Modules/Addons/Tickets/routes.php

            // Module CRM migrado a app/Modules/Core/CRM/routes.php

            Route::post('/helper/get-value-colony-state-municipality', 'Shared\ComponentSelectStateMunicipalityAndColonyController@getValueDB');
            Route::post('/helper/get-services-by-client-main-information', 'Shared\ComponentSearchServiceController@getServiceByClientMainInformationId');

            // Módulo de vendedores
            // Rutas `vendedores/*` migradas a app/Modules/Addons/Vendedores/routes.php


            // Rutas `maps/*` (Connections/Devices/KMZ/Layers/Proyects/ServiceBox) migradas
            // a app/Modules/Addons/Mapas/routes.php (sub-namespace Geo)


            // Rutas `olts/*` migradas a app/Modules/Addons/GestionRed/routes.php

            // Rutas `mapas/*` (infraestructura física FTTH) migradas a app/Modules/Addons/Mapas/routes.php

            Route::group(['prefix' => 'red'], function () {

                // Rutas `red/ipv4` y `red/router` migradas a app/Modules/Addons/GestionRed/routes.php
            });

            Route::group(['prefix' => 'finanzas', 'namespace' => 'Finance'], function () {

                Route::group(['prefix' => 'transacciones', 'namespace' => 'Transaction'], function () {
                    Route::get('/', 'FinanceTransactionController@index');
                    Route::get('/', 'FinanceTransactionController@index');
                    Route::post('/table', 'FinanceTransactionController@table');
                });

                Route::group(['prefix' => 'facturas', 'namespace' => 'Invoice'], function () {
                    Route::get('/', 'FinanceInvoiceController@index');
                    Route::post('/table', 'FinanceInvoiceController@table');
                });

                Route::group(['prefix' => 'pagos', 'namespace' => 'Payment'], function () {
                    Route::get('/', 'FinancePaymentController@index');
                    Route::post('/table', 'FinancePaymentController@table');
                });

                Route::group(['prefix' => 'invoices', 'namespace' => 'Invoice'], function () {
                    Route::get('/', 'InvoiceController@index');
                    Route::post('/table', 'InvoiceController@table');
                    Route::post('/create-for-client/{id}', 'InvoiceController@createForClient');

                    Route::post('/send/{id}', 'InvoiceController@sendInvoice');
                    Route::get('/print/{id}', 'InvoiceController@printInvoice');
                    Route::post('/mark-as-paid/{id}', 'InvoiceController@markAsPaid');
                    Route::post('/edit-period/{id}', 'InvoiceController@editPeriod');

                    Route::post('/get-pending-by-client/{id}', 'InvoiceController@getPendingByClient');
                    Route::post('/destroy/{id}', 'InvoiceController@destroy');
                    Route::get('/get-available-periods-by-client/{id}', 'InvoiceController@getAvailablePeriodsByClient');
                });

                Route::group(['prefix' => 'general-accounting', 'namespace' => 'GeneralAccounting'], function () {
                    Route::get('/', 'GeneralAccountingController@index');
                    Route::get('/get-data', 'GeneralAccountingController@getData');
                    Route::get('/get-bar-data', 'GeneralAccountingController@getBarData');
                    Route::get('/get-donut-data', 'GeneralAccountingController@getDonutData');
                    Route::group(['prefix' => 'income', 'namespace' => 'Income'], function () {
                        Route::post('/table', 'GeneralAccountingIncomeController@table');
                        Route::post('/add', 'GeneralAccountingIncomeController@store');
                    });

                    Route::group(['prefix' => 'expense', 'namespace' => 'Expense'], function () {
                        Route::post('/table', 'GeneralAccountingExpenseController@table');
                        Route::post('/add', 'GeneralAccountingExpenseController@store');
                    });

                    Route::group(['prefix' => 'operation', 'namespace' => 'Operation'], function () {
                        Route::post('/add', 'GeneralAccountingOperationController@store');
                        Route::post('/update/{id}', 'GeneralAccountingOperationController@update');
                    });

                    Route::group(['prefix' => 'category', 'namespace' => 'Category'], function () {
                        Route::post('/add', 'GeneralAccountingCategoryController@store');
                    });
                });
            });

            // Rutas `releases/*` migradas a app/Modules/Core/Release/routes.php
            // Rutas `git/*` migradas a app/Modules/Addons/DevTools/routes.php (sub-namespace Git)

            // Rutas `scheduling/*` migradas a app/Modules/Addons/Scheduling/routes.php

            // Rutas Mikrotik globales migradas a app/Modules/Addons/GestionRed/routes.php
        });
    });

    Route::post('/cliente/get-receipt-for-client', 'Utils\ReceiptController@getReceiptForClient');
    Route::post('/get-payment-period', 'Utils\UtilController@getPaymentPeriod');

    // SettingTableController migrado a App\Modules\Core\Configuracion\Controllers\Table
    Route::get('/setting-table/get/{table_id}', [\App\Modules\Core\Configuracion\Controllers\Table\SettingTableController::class, 'get']);
    Route::post('/setting-table/post/{table_id}', [\App\Modules\Core\Configuracion\Controllers\Table\SettingTableController::class, 'store']);

    // Rutas / , /index y /get-*-card-in-dashboard-c migradas a
    // app/Modules/Core/Dashboard/routes.php
    // Ruta /permissions-auth migrada a app/Modules/Core/Usuarios/routes.php

    Route::post('/user/get-next-user', 'HelperController@getNextUserId');

    Route::post('/get-data/{module}', 'HelperController@getData');
    Route::post('/fields-by-module', 'HelperController@getFieldsByModule');

    Route::post('/fields-by-module-and-relation', 'HelperController@getFieldsByModuleRelation');
    Route::post('/fields-by-module-with-module-requested', 'HelperController@getFieldsByModuleWithModuleRequested');

    Route::post('/fields-by-module/{id}', 'HelperController@getFieldsEditedById');
    Route::post('/fields-by-module/general/edited', 'HelperController@requestGeneralEditedFields');
    Route::post('/columns-by-module', 'HelperController@getColumnsByModule');
    Route::post('/column-expand-by-module', 'HelperController@getColumnDtExpandByModule');
    Route::post('/set-column-expand-by-module', 'HelperController@setColumnDtExpandByModule');
    Route::post('/columns-by-module-except-columns', 'HelperController@getColumnsByModuleExceptColumns');


    Route::post('/all-columns-by-module', 'HelperController@getAllColumnsByModule');
    Route::post('/update-column-by-user', 'HelperController@updateColumnsByUser');


    Route::post('/request-random-password', 'HelperController@getRandomPassword');
    Route::post('/save-random-password', 'HelperController@saveRandomPassword');
    Route::post('/request-generate-user/{username}', 'HelperController@getGenerateUser');
    Route::post('/request-generate-user-exist', 'HelperController@getGenerateUserExist');

    Route::post('/get-options-team', 'HelperController@getOptionsTeam');

    Route::post('/get-options-select', 'SearchModelController@search');
    Route::post('/get-options-select/{id}', 'SearchModelController@searchWithoutId');
    Route::post('/get-long-options-select', 'SearchModelController@longOptions');
    Route::post('/get-options-client', 'SearchModelController@longOptionsClient');

    // Rutas /has-permission-to-view y /all-view-has-permission migradas a app/Modules/Core/Usuarios/routes.php

    Route::group(['prefix' => 'perfil'], function () {
        Route::get('/{id}', 'UserController@show');
        Route::get('/editar/{id}', 'UserController@edit');
        Route::post('/update/{id}', 'UserController@update');
        Route::post('/update-image/{id}', 'UserController@updateImage');
        Route::post('/get-perfil-by-id/{id}', 'UserController@getPerfilById');
    });

    Route::post('/get-user-authenticated', 'HelperController@getUserAuthenticated');

    Route::post('/get-default-value/{date}', 'Utils\DefaultValueController@getDefaultValue');
    Route::post('/get-default-billing-date-for-client', 'Utils\DefaultValueController@getDefaultBillingDateForClient');

    Route::post('/cliente/get-user-for-client', 'Utils\DefaultValueController@getDefaultValueForUserClient');

    Route::post('/crm/send-notification/{id}', 'Utils\NotificationController@sendNotificationCrm');
    Route::post('/get-log-activities/{id}', 'Utils\LogActivityController@getLogActivities');
    Route::get('/get-all-activities', 'Utils\LogActivityController@getAllActivities');
    Route::get('/get-activities-by-user/{id}', 'Utils\LogActivityController@getActivitiesByUser');
    Route::post('/fullcalendar/get-billing-configuration', 'Utils\FullcalendarController@getBillingConfiguration');
    Route::post('/fullcalendar/get-task-events', 'Utils\FullcalendarController@getTaskEvents');

    Route::post('/get-crm-client-if-exist', 'HelperController@getCrmClientIfExist');
    Route::post('/save-or-delete-default-value', 'SharedDefaultValues@saveOrDeleteDefaultValue');
    Route::post('/get-default-fields-value', 'SharedDefaultValues@getDefaultFieldsValue');

    // ConfigAppLayoutController routes movidas a app/Modules/Core/Layout/routes.php
    Route::post('/getDataTable', 'Controller@getDataToTable');
    Route::get('/read-all-notifications', 'Utils\NotificationController@readAll');
    Route::get('/read-notification/{id}', 'Utils\NotificationController@readNotification');

    // Bloque /statics/* migrado a app/Modules/Core/Dashboard/routes.php
});

// Ruta /home (named 'home') migrada a app/Modules/Core/Dashboard/routes.php

Route::group(['prefix' => '/register-vendor'], function () {
    Route::get('', [RegisterVendorController::class, 'index'])->name('register-vendor.index');
    Route::post('/register', [RegisterVendorController::class, 'register'])->name('register-vendor.register');
});

Route::get('/notification-email', function () {
    $n = TaskNotification::find(1);
    return (new StandardNotification($n, ['email'], ['title' => 'usted tiene asignada una nueva tarea', 'url' => '/scheduling/task/editar/' . $n->task_id]))
        ->toMail($n->user);

    Route::group(['prefix' => 'ia', 'namespace' => 'IA'], function () {
        Route::post('/chat',   'IAChatController@chat');
        Route::get('/history', 'IAChatController@history');
    });
});
