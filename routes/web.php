<?php

use App\Http\Controllers\Module\Administration\User\UserController;
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

                Route::group(['prefix' => 'user', 'namespace' => 'User'], function () {
                    Route::get('/', [UserController::class, 'index'])->name('user.index');
                    Route::get('/getRoles', [UserController::class, 'getRoles'])->name('user.getRoles');
                    Route::get('/get-all-users', [UserController::class, 'getAllUsers'])->name('user.get-all-users');
                    Route::get('/crear', [UserController::class, 'create'])->name('user.create');
                    Route::post('/create', [UserController::class, 'store'])->name('user.store');
                    Route::get('/{id}/editar', [UserController::class, 'edit'])->name('user.edit');
                    Route::post('/get-data-user/{id}', [UserController::class, 'getData'])->name('user.getData');
                    Route::post('/{id}/update', [UserController::class, 'update'])->name('user.update');
                    Route::delete('/{id}/destroy', [UserController::class, 'destroy'])->name('user.destroy');
                    Route::post('/{id}/inactive-or-active', [UserController::class, 'inactiveOrActive'])->name('user.inactive-or-active');
                });

                Route::group(['prefix' => 'addresses', 'namespace' => 'Addresses'], function () {
                    Route::get('/states', [UserController::class, 'getStates'])->name('states');
                    Route::get('/{id}/municipalities', [UserController::class, 'getMunicipalities'])->name('municipalities');
                    Route::get('/{id}/colonies', [UserController::class, 'getColonies'])->name('colonies');
                });

                Route::group(['prefix' => 'rol', 'namespace' => 'Rol'], function () {
                    Route::get('/', 'RolController@index');
                    Route::get('/get-all', 'RolController@get');
                    Route::post('/add', 'RolController@store');
                    Route::get('/editar-role/{id}', 'RolController@edit');
                    Route::post('/update-role/{id}', 'RolController@updateRole');
                    Route::delete('/destroy/{id}', 'RolController@destroy');
                    Route::post('/table', 'RolController@table');
                });

                Route::group(['prefix' => 'permisos', 'namespace' => 'Permission'], function () {
                    Route::get('/get-permission-for-role/{id}', 'PermissionController@get');
                    Route::post('/update-permission-for-role/{id}', 'PermissionController@update');
                    Route::get('/get-permission-for-user/{id}', 'PermissionController@getPermissionUser');
                    Route::post('/update-permission-for-user/{id}', 'PermissionController@updatePermissionUser');
                });

                Route::group(['prefix' => 'socios', 'namespace' => 'Partner'], function () {
                    Route::get('/', 'PartnerController@index');
                    Route::post('/add', 'PartnerController@store');
                    Route::get('/editar/{id}', 'PartnerController@edit');
                    Route::post('/update/{id}', 'PartnerController@update');
                    Route::post('/destroy/{id}', 'PartnerController@destroy');
                    Route::post('/table', 'PartnerController@table');
                });

                Route::group(['prefix' => 'ubicacion', 'namespace' => 'Location'], function () {
                    Route::get('/', 'LocationController@index');
                    Route::post('/add', 'LocationController@store');
                    Route::get('/editar/{id}', 'LocationController@edit');
                    Route::post('/update/{id}', 'LocationController@update');
                    Route::post('/destroy/{id}', 'LocationController@destroy');
                    Route::post('/table', 'LocationController@table');
                });

                Route::group(['prefix' => 'sucursal', 'namespace' => 'Sucursal'], function () {
                    Route::get('/', 'SucursalController@index');
                    Route::get('/all', 'SucursalController@all');
                    Route::post('/add', 'SucursalController@store');
                    Route::get('/editar/{id}', 'SucursalController@edit');
                    Route::post('/update/{id}', 'SucursalController@update');
                    Route::post('/destroy/{id}', 'SucursalController@destroy');
                    Route::post('/table', 'SucursalController@table');
                });


                Route::group(['prefix' => 'estado', 'namespace' => 'State'], function () {
                    Route::get('/', 'StateController@index');
                    Route::post('/add', 'StateController@store');
                    Route::get('/editar/{id}', 'StateController@edit');
                    Route::post('/update/{id}', 'StateController@update');
                    Route::post('/destroy/{id}', 'StateController@destroy');
                    Route::post('/table', 'StateController@table');
                });

                Route::group(['prefix' => 'activity_log', 'namespace' => 'ActivityLog'], function () {
                    Route::get('/', 'ActivityLogController@index');
                    Route::post('/table', 'ActivityLogController@table');
                });

                Route::group(['prefix' => 'document_template', 'namespace' => 'DocumentTemplate'], function () {
                    Route::get('/', 'DocumentTemplateController@index');
                    Route::post('/table', 'DocumentTemplateController@table');
                    Route::get('/load_content_template', 'DocumentTemplateController@loadContentTemplate');
                    Route::post('/show_content_template', 'DocumentTemplateController@showContentTemplate');
                    Route::post('/show_content_template/{id}', 'DocumentTemplateController@showContentTemplateById');
                    Route::get('/get_variables', 'DocumentTemplateController@getVariables');
                    Route::post('/add', 'DocumentTemplateController@store');
                    Route::post('/update/{id}', 'DocumentTemplateController@update');
                    Route::post('/destroy/{id}', 'DocumentTemplateController@destroy');

                    Route::post('/get_data_template/{id}', 'DocumentTemplateController@getDataTemplate');
                });

                Route::group(['prefix' => 'document_type_template', 'namespace' => 'DocumentTypeTemplate'], function () {
                    Route::get('/', 'DocumentTypeTemplateController@index');
                    Route::post('/add', 'DocumentTypeTemplateController@store');
                    Route::get('/editar/{id}', 'DocumentTypeTemplateController@edit');
                    Route::post('/update/{id}', 'DocumentTypeTemplateController@update');
                    Route::post('/destroy/{id}', 'DocumentTypeTemplateController@destroy');
                    Route::post('/table', 'DocumentTypeTemplateController@table');
                });

                Route::group(['prefix' => 'ift', 'namespace' => 'Ift'], function () {
                    Route::get('/', 'IftController@index');
                    Route::post('/add', 'IftController@store');
                    Route::get('/editar/{id}', 'IftController@edit');
                    Route::post('/update/{id}', 'IftController@update');
                    Route::post('/destroy/{id}', 'IftController@destroy');
                    Route::post('/table', 'IftController@table');
                });

                Route::group(['prefix' => 'municipio', 'namespace' => 'Municipality'], function () {
                    Route::get('/', 'MunicipalityController@index');
                    Route::post('/add', 'MunicipalityController@store');
                    Route::get('/editar/{id}', 'MunicipalityController@edit');
                    Route::post('/update/{id}', 'MunicipalityController@update');
                    Route::post('/destroy/{id}', 'MunicipalityController@destroy');
                    Route::post('/table', 'MunicipalityController@table');
                });

                Route::group(['prefix' => 'colonia', 'namespace' => 'Colony'], function () {
                    Route::get('/', 'ColonyController@index');
                    Route::post('/add', 'ColonyController@store');
                    Route::get('/editar/{id}', 'ColonyController@edit');
                    Route::post('/update/{id}', 'ColonyController@update');
                    Route::post('/destroy/{id}', 'ColonyController@destroy');
                    Route::post('/table', 'ColonyController@table');
                });

                Route::group(['prefix' => 'metotdo-de-pago', 'namespace' => 'MethodOfPayment'], function () {
                    Route::get('/', 'MethodOfPaymentController@index');
                    Route::post('/add', 'MethodOfPaymentController@store');
                    Route::get('/editar/{id}', 'MethodOfPaymentController@edit');
                    Route::post('/update/{id}', 'MethodOfPaymentController@update');
                    Route::post('/destroy/{id}', 'MethodOfPaymentController@destroy');
                    Route::post('/table', 'MethodOfPaymentController@table');
                });

                // Rutas nuevo módulo Documentation
                Route::group(['prefix' => 'documentation', 'namespace' => 'Documentation'], function () {
                    Route::group(['prefix' => 'documentation_menu', 'namespace' => 'DocumentationMenu'], function () {
                        Route::get('/', 'DocumentationMenuController@index');
                        Route::post('/add', 'DocumentationMenuController@store');
                        Route::post('/update/{id}', 'DocumentationMenuController@update');
                        Route::post('/destroy/{id}', 'DocumentationMenuController@destroy');
                        Route::post('/table', 'DocumentationMenuController@table');
                        Route::get('/getById/{id}', 'DocumentationMenuController@getById');
                        Route::get('/get-title/{id}', 'DocumentationMenuController@getTitle');
                        // Ruta para el árbol de documentación (dropdown)
                        Route::get('/tree', 'DocumentationMenuController@getTree');
                    });
                    Route::group(['prefix' => 'documentation_submenu', 'namespace' => 'DocumentationSubmenu'], function () {
                        Route::get('/', 'DocumentationSubmenuController@index');
                        Route::post('/add', 'DocumentationSubmenuController@store');
                        Route::post('/update/{id}', 'DocumentationSubmenuController@update');
                        Route::post('/destroy/{id}', 'DocumentationSubmenuController@destroy');
                        Route::post('/table', 'DocumentationSubmenuController@table');
                        // NUEVA RUTA:
                        Route::get('/{id}', 'DocumentationSubmenuController@show');
                    });
                    Route::group(['prefix' => 'documentation_content', 'namespace' => 'DocumentationContent'], function () {
                        // Route::get('/{submenu_id}/contents', 'DocumentationContentController@index');
                        Route::post('/add', 'DocumentationContentController@store');
                        Route::post('/update/{id}', 'DocumentationContentController@update');
                        Route::delete('/delete/{id}', 'DocumentationContentController@destroy');
                        // Route::get('/{submenuId}/contents', 'DocumentationContentController@getContents');
                        Route::get('/{submenuId}/contents', 'DocumentationContentController@getContentsBySubmenuId');
                    });
                });

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

            Route::group(['prefix' => 'message', 'namespace' => 'Message'], function () {
                Route::group(['prefix' => 'inbox', 'namespace' => 'Inbox'], function () {
                    Route::get('/', 'InboxController@index');
                    Route::post('/get-data-tabs', 'InboxController@getDataTabs');
                });

                Route::group(['prefix' => 'reminder', 'namespace' => 'Reminder'], function () {
                    Route::post('/table', 'ReminderController@table');
                    Route::post('/send_message', 'ReminderController@sendMessage');
                });
                // Route::group(['prefix' => 'payment_email', 'namespace' => 'PaymentEmail'], function () {
                //     Route::post('/table', 'PaymentEmailController@table');
                //     Route::post('/send_message', 'PaymentEmailController@sendMessage');
                // });
                Route::group(['prefix' => 'invoice_email', 'namespace' => 'InvoiceEmail'], function () {
                    Route::post('/table', 'InvoiceEmailController@table');
                    // Route::post('/send_message', 'PaymentEmailController@sendMessage');
                });
                Route::group(['prefix' => 'proforma_invoice_email', 'namespace' => 'ProformaInvoiceEmail'], function () {
                    Route::post('/table', 'ProformaInvoiceEmailController@table');
                    Route::post('/send_message', 'ProformaInvoiceEmailController@sendMessage');
                });
            });

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
            Route::group(['prefix' => 'git', 'namespace' => 'Git'], function () {
                Route::get('/get-tags', 'GitController@getTags');
            });

            Route::group(['prefix' => 'scheduling', 'namespace' => 'Scheduling'], function () {

                Route::group(['prefix' => 'project', 'namespace' => 'Project'], function () {
                    Route::get('/', 'ProjectController@index');
                    Route::post('/table', 'ProjectController@table');
                    Route::post('/update/{id}', 'ProjectController@update');
                    Route::post('/add', 'ProjectController@store');
                    Route::post('/destroy/{id}', 'ProjectController@destroy');
                });

                Route::group(['prefix' => 'task', 'namespace' => 'Task'], function () {
                    Route::get('/', 'TaskController@index');
                    Route::post('/table', 'TaskController@table');
                    Route::post('/update/{id}', 'TaskController@update');
                    Route::post('/add', 'TaskController@store');
                    Route::get('/crear', 'TaskController@create');
                    Route::post('/destroy/{id}', 'TaskController@destroy');
                    Route::get('/editar/{id}', 'TaskController@edit');
                    Route::get('/calendar', 'TaskController@showCalendar');
                    Route::post('/get-list-template-verification-by-task/{id}', 'TaskController@getListTemplateVerification');
                    Route::post('/update-task-to-calendar', 'TaskController@updatetaskToCalenddar');
                    Route::post('/archive/{id}', 'TaskController@archive');
                    Route::post('/unarchive/{id}', 'TaskController@unArchive');
                    Route::post('/add_note/{id}', 'TaskController@addNote');
                    Route::post('/get-notes-by-task/{id}', 'TaskController@getNotesByTask');
                    Route::post('/get-data-task/{id}', 'TaskController@getData');
                    Route::get('/show-archived', 'TaskController@showArchived');
                    Route::get('/read-notification/{id}', 'TaskController@readNotification');
                    Route::post('/unread-notification/{id}', 'TaskController@unreadNotification');
                    Route::post('/download-file/{id}', 'TaskController@download');
                    Route::post('/upload-file/{task}', 'TaskController@uploadFile');
                    Route::post('/remove-file/{task}', 'TaskController@removeFile');
                });
            });

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
    Route::get('/permissions-auth', 'Module\Administration\Permission\PermissionController@userPermissions');

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

    Route::post('/has-permission-to-view/{view}', 'Module\Administration\Permission\PermissionController@hasPermissionToView');
    Route::post('/all-view-has-permission', 'Module\Administration\Permission\PermissionController@allViewHasPermission');

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
