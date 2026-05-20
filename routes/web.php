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

            // Module Administracion
            Route::group(['prefix' => 'administracion', 'namespace' => 'Administration'], function () {
                Route::get('/', 'AdministracionController@index');
                Route::get('/clean-all-client-service', 'AdministracionController@clearAllClientServices');
                Route::get('/add-clients-imported-to-mikrotik', 'AdministracionController@addClientsImportedToMikrotik');
                Route::get('/suspend_clients', 'AdministracionController@suspendProcess');
                Route::get('/billing_services', 'AdministracionController@billigProcess');
                Route::post('/active-schedule-process', 'AdministracionController@activeCommands');
                Route::get('/check-schedule-process', 'AdministracionController@checkProcess');
                Route::get('/show_scripts', 'AdministracionController@showScripts');
                Route::get('/rectify_address_list', 'AdministracionController@rectifyAddressList');
                Route::get('/billing_services_to_client_active_promise_payment', 'AdministracionController@billingServiceToClientActivePromise');

                // Rutas user/addresses/rol/permisos migradas a app/Modules/Core/Usuarios/routes.php

                // Rutas socios migradas a app/Modules/Core/Configuracion/routes.php

                // Rutas ubicacion/sucursal/estado migradas a app/Modules/Core/Localizacion/routes.php

                // Rutas activity_log migradas a app/Modules/Core/Auditoria/routes.php

                // Rutas document_template y document_type_template migradas a app/Modules/Core/Documentos/routes.php

                // Rutas ift migradas a app/Modules/Core/Configuracion/routes.php

                // Rutas municipio/colonia migradas a app/Modules/Core/Localizacion/routes.php

                // Rutas metotdo-de-pago migradas a app/Modules/Core/Configuracion/routes.php

                // Rutas administracion/documentation/* migradas a app/Modules/Core/Documentacion/routes.php

            });

            Route::group(['prefix' => 'inventory', 'namespace' => 'Inventory'], function () {
                Route::group(['prefix' => 'inventory_item', 'namespace' => 'InventoryItem'], function () {
                    Route::get('/', 'InventoryItemController@index');
                    Route::post('/add', 'InventoryItemController@store');
                    Route::post('/add-custom', 'InventoryItemController@storeCustom');
                    Route::get('/editar/{id}', 'InventoryItemController@edit');
                    Route::post('/update/{id}', 'InventoryItemController@update');
                    Route::post('/destroy/{id}', 'InventoryItemController@destroy');
                    Route::post('/assign_to_user/{id}', 'InventoryItemController@assignToUser');
                    Route::post('/change_store/{id}', 'InventoryItemController@changeStore');
                    Route::post('/add_movement', 'InventoryItemController@addMovement');

                    Route::post('/table', 'InventoryItemController@table');
                });
                Route::group(['prefix' => 'inventory_item_type', 'namespace' => 'InventoryItemType'], function () {
                    Route::get('/', 'InventoryItemTypeController@index');
                    Route::post('/add', 'InventoryItemTypeController@store');
                    Route::get('/editar/{id}', 'InventoryItemTypeController@edit');
                    Route::post('/update/{id}', 'InventoryItemTypeController@update');
                    Route::post('/destroy/{id}', 'InventoryItemTypeController@destroy');
                    Route::post('/table', 'InventoryItemTypeController@table');
                });
                Route::group(['prefix' => 'inventory_item_stock', 'namespace' => 'InventoryItemStock'], function () {
                    Route::get('/', 'InventoryItemStockController@index');
                    Route::post('/add', 'InventoryItemStockController@store');
                    Route::post('/change_stock', 'InventoryItemStockController@changeStock');
                    Route::get('/editar/{id}', 'InventoryItemStockController@edit');
                    Route::post('/update/{id}', 'InventoryItemStockController@update');
                    Route::post('/destroy/{id}', 'InventoryItemStockController@destroy');
                    Route::post('/table', 'InventoryItemStockController@table');
                    Route::post('/get_items_by_user/{id}', 'InventoryItemStockController@getItemsByUser');
                    Route::post('/accept_item_by_movement/{id}', 'InventoryItemStockController@acceptItemByMovement');
                    Route::post('/reject_item_by_movement/{id}', 'InventoryItemStockController@rejectItemByMovement');
                    Route::post('/get_items_by_store/{id}', 'InventoryItemStockController@getItemsByStore');
                    Route::post('/get_items_by_client/{id}', 'InventoryItemStockController@getItemsByClient');
                    Route::get('/get_media_by_item/{id}', 'InventoryItemStockController@getMedia');
                    Route::post('/upload_media', 'InventoryItemStockController@uploadMedia');
                    Route::delete('/delete_media/{id}', 'InventoryItemStockController@deleteMedia');
                });
                Route::group(['prefix' => 'inventory_movement', 'namespace' => 'InventoryMovement'], function () {
                    Route::get('/', 'InventoryMovementController@index');
                    Route::post('/add', 'InventoryMovementController@store');
                    Route::get('/editar/{id}', 'InventoryMovementController@edit');
                    Route::post('/update/{id}', 'InventoryMovementController@update');
                    Route::post('/destroy/{id}', 'InventoryMovementController@destroy');
                    Route::post('/table', 'InventoryMovementController@table');
                });
                Route::group(['prefix' => 'inventory_store', 'namespace' => 'InventoryStore'], function () {
                    Route::get('/', 'InventoryStoreController@index');
                    Route::post('/add', 'InventoryStoreController@store');
                    Route::get('/editar/{id}', 'InventoryStoreController@edit');
                    Route::post('/update/{id}', 'InventoryStoreController@update');
                    Route::post('/destroy/{id}', 'InventoryStoreController@destroy');
                    Route::post('/table', 'InventoryStoreController@table');
                    Route::get('/my-store/{id}', 'InventoryStoreController@myStore')->name('inventory.inventory_store.my-store');
                    Route::get('/get-all', 'InventoryStoreController@getAll');
                    Route::get('/get-by-id/{id}', 'InventoryStoreController@getById');
                });
                Route::group(['prefix' => 'store_zone', 'namespace' => 'StoreZone'], function () {
                    Route::get('/', 'StoreZoneController@index');
                    Route::post('/add', 'StoreZoneController@store');
                    Route::get('/editar/{id}', 'StoreZoneController@edit');
                    Route::post('/update/{id}', 'StoreZoneController@update');
                    Route::post('/destroy/{id}', 'StoreZoneController@destroy');
                    Route::post('/table', 'StoreZoneController@table');
                    Route::get('/get-store-zones-by-store/{id}', 'StoreZoneController@getStoreZonesByStore');
                    Route::get('/show-zones-by-store/{id}', 'StoreZoneController@showZonesByStore');
                    Route::get('/search', 'StoreZoneController@search');
                    Route::get('/get-by-id/{id}', 'StoreZoneController@getById');
                    Route::post('/update-zone', 'StoreZoneController@updateZone');
                });

                Route::group(['prefix' => 'inventory_item_custom_model', 'namespace' => 'InventoryItemCustom'], function () {
                    Route::get('/', 'InventoryItemCustomModelController@index');
                    Route::post('/add', 'InventoryItemCustomModelController@store');
                    Route::post('/table', 'InventoryItemCustomModelController@table');
                });

                Route::group(['prefix' => 'inventory_item_custom', 'namespace' => 'InventoryItemCustom'], function () {
                    Route::get('/items/{id}', 'InventoryItemCustomController@getItemsByCustomModelId');
                    Route::post('/table', 'InventoryItemCustomController@table');
                });
            });
            // Rutas `sellers/*` migradas a app/Modules/Addons/Vendedores/routes.php

            // Rutas `message/*` migradas a app/Modules/Addons/Mensajes/routes.php

            // Module Plan
            Route::group(['namespace' => 'Plan'], function () {
                // Internet
                Route::group(['prefix' => 'internet'], function () {
                    Route::get('/', 'InternetController@index')->name('internet');
                    Route::get('/success/{id}', 'InternetController@success');
                    Route::get('/crear', 'InternetController@create');
                    Route::post('/add', 'InternetController@store');
                    Route::get('/editar/{id}', 'InternetController@edit');
                    Route::post('/update/{id}', 'InternetController@update');
                    Route::post('/destroy/{id}', 'InternetController@destroy');
                    Route::post('/table', 'InternetController@table');
                });
                // Bundles
                Route::group(['prefix' => 'paquetes'], function () {
                    Route::get('/', 'BundleController@index')->name('paquetes');
                    Route::get('/success/{id}', 'BundleController@success');
                    Route::get('/crear', 'BundleController@create');
                    Route::post('/add', 'BundleController@store');
                    Route::get('/editar/{id}', 'BundleController@edit');
                    Route::post('/update/{id}', 'BundleController@update');
                    Route::post('/destroy/{id}', 'BundleController@destroy');
                    Route::post('/table', 'BundleController@table');
                });
                // Voz
                Route::group(['prefix' => 'voz'], function () {
                    Route::get('/', 'VozController@index')->name('voz');
                    Route::get('/success/{id}', 'VozController@success');
                    Route::get('/crear', 'VozController@create');
                    Route::post('/add', 'VozController@store');
                    Route::get('/editar/{id}', 'VozController@edit');
                    Route::post('/update/{id}', 'VozController@update');
                    Route::post('/destroy/{id}', 'VozController@destroy');
                    Route::post('/table', 'VozController@table');
                });
                // Custom
                Route::group(['prefix' => 'custom'], function () {
                    Route::get('/', 'CustomController@index')->name('recurrente');
                    Route::get('/success/{id}', 'CustomController@success');
                    Route::get('/crear', 'CustomController@create');
                    Route::post('/add', 'CustomController@store');
                    Route::get('/editar/{id}', 'CustomController@edit');
                    Route::post('/update/{id}', 'CustomController@update');
                    Route::post('/destroy/{id}', 'CustomController@destroy');
                    Route::post('/table', 'CustomController@table');
                });
            });

            // Module Tickets
            Route::group(['prefix' => 'tickets', 'namespace' => 'Ticket'], function () {
                Route::get('/', 'DashboardController@index');

                Route::get('/abiertos', 'TicketController@opened');
                Route::get('/abiertos/{client_id}', 'TicketController@opened');
                Route::get('/cerrados', 'TicketController@closed');
                Route::get('/cerrados/{client_id}', 'TicketController@closed');
                Route::get('/reciclados', 'TicketController@trash');
                Route::get('/crear', 'TicketController@create');
                Route::get('/crear/{clientId}', 'TicketController@create');
                Route::post('/add', 'TicketController@store');
                Route::get('/success/{id}', 'TicketController@success');
                Route::get('/editar/{id}', 'TicketController@edit');
                Route::get('/ver/{id}', 'TicketController@ver');
                Route::post('/update/{id}', 'TicketController@update');
                Route::post('/mensaje/update/{id}', 'TicketThreadController@update');
                Route::post('/mensaje/add/{id}', 'TicketThreadController@store');
                Route::get('/destroy/{id}', 'TicketController@destroy');
                Route::post('/table', 'TicketController@table');
                Route::get('/notifica/{id}', 'TicketController@notificationsReadMarked');

                Route::post('/get-ticket-by-id/{id}', 'TicketController@getTicketById');
                Route::post('/get-time-lapsed/{id}', 'TicketController@getTimeLapsed');
                Route::post('/get-user-data-by-ticket-id/{id}', 'TicketController@getUserDataTicketById');
                Route::post('/set-status-ticket-by-id/{id}', 'TicketController@setStatusTicketById');
                Route::post('/get-ticket-thread-by-id/{id}', 'TicketThreadController@getTicketThreadById');
                Route::post('/get-data-client/{id}', 'TicketController@getDataClient');
                Route::post('/get-parent-ticket-by-id/{id}', 'TicketThreadController@getParentTicketById');
                Route::post('/get-child-ticket-by-id/{id}', 'TicketThreadController@getChildTicketById');

                Route::post('/request-statistics-for-tarjets-by-status', 'DashboardController@getStatisticsForTarjetsByStatus');
                Route::post('/request-ticket-assigned-to-me', 'DashboardController@getTicketAssignedToMe');
                Route::post('/request-ticket-assigned-to', 'DashboardController@getTicketAssignedTo');
                Route::get('/get-tickets-new-by-date/{startDate}/{endDate}/{status}', 'DashboardController@getTicketsByDateAndStatus');
            });

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
