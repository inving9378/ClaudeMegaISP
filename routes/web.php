<?php

use App\Http\Controllers\Module\Administration\User\UserController;
use App\Http\Controllers\Module\Mapas\ActiveEquipmentController;
use App\Http\Controllers\Module\Mapas\ActiveEquipmentTypeController;
use App\Http\Controllers\Module\Mapas\BoxController;
use App\Http\Controllers\Module\Mapas\BoxInputController;
use App\Http\Controllers\Module\Mapas\BoxTypeController;
use App\Http\Controllers\Module\Mapas\BrandController;
use App\Http\Controllers\Module\Mapas\BufferController;
use App\Http\Controllers\Module\Mapas\CardController;
use App\Http\Controllers\Module\Mapas\EquipmentLinkController;
use App\Http\Controllers\Module\Mapas\FiberController;
use App\Http\Controllers\Module\Mapas\MapasController;
use App\Http\Controllers\Module\Mapas\MaplinkController;
use App\Http\Controllers\Module\Mapas\MapProyectController;
use App\Http\Controllers\Module\Mapas\MapRouteController;
use App\Http\Controllers\Module\Mapas\PassiveEquipmentController;
use App\Http\Controllers\Module\Mapas\PassiveEquipmentTypeController;
use App\Http\Controllers\Module\Mapas\PointAccessoryController;
use App\Http\Controllers\Module\Mapas\PointController;
use App\Http\Controllers\Module\Mapas\PoleAccessoryController;
use App\Http\Controllers\Module\Mapas\PoleController;
use App\Http\Controllers\Module\Mapas\PortController;
use App\Http\Controllers\Module\Mapas\RackController;
use App\Http\Controllers\Module\Mapas\SiteController;
use App\Http\Controllers\Module\Mapas\SplitterController;
use App\Http\Controllers\Module\Mapas\TrenchController;
use App\Http\Controllers\Module\Mapas\TrenchTypeController;
use App\Http\Controllers\Module\Mapas\TubeController;
use App\Http\Controllers\Module\Mapas\MapCredentialController;
use App\Http\Controllers\Module\Mapas\TableController;
use App\Http\Controllers\Module\Mapas\TubeTypeController;
use App\Http\Controllers\Module\Mapas\TransceiverController;
use App\Http\Controllers\Module\Mapas\TrayController;
use App\Http\Controllers\Module\Maps\ConnectionsController;
use App\Http\Controllers\Module\Maps\DevicesController;
use App\Http\Controllers\Module\Vendors\VendorController;
use App\Http\Controllers\Module\Vendors\SellerController;
use App\Http\Controllers\Module\Vendors\Prospects\ProspectController;
use App\Http\Controllers\Module\Vendors\Sales\SaleController;
use App\Http\Controllers\Module\Vendors\Billing\PaymentClientController;
use App\Http\Controllers\Module\Vendors\Billing\PaymentSellerController;
use App\Http\Controllers\Module\Vendors\Billing\SellerTransactionController;
use App\Http\Controllers\Module\Vendors\Billing\CommissionRuleController;
use App\Http\Controllers\Module\Vendors\Billing\RangeSaleController;
use App\Http\Controllers\Module\Maps\LayersController;
use App\Http\Controllers\Module\Maps\ProyectsController;
use App\Http\Controllers\Module\Maps\KMZController;
use App\Http\Controllers\Module\Maps\ServiceBoxController;
use App\Http\Controllers\Module\OLTs\OLTsBillingController;
use App\Http\Controllers\Module\OLTs\OLTsCardsController;
use App\Http\Controllers\Module\OLTs\OLTsController;
use App\Http\Controllers\Module\OLTs\OLTsODBsController;
use App\Http\Controllers\Module\OLTs\OLTsOnuController;
use App\Http\Controllers\Module\OLTs\OLTsPonPortsController;
use App\Http\Controllers\Module\OLTs\OLTsProfilesController;
use App\Http\Controllers\Module\OLTs\OLTsTypeONUsController;
use App\Http\Controllers\Module\OLTs\OLTsUplinkPortsController;
use App\Http\Controllers\Module\OLTs\OLTsVlansController;
use App\Http\Controllers\Module\OLTs\OLTsZonesController;
use App\Http\Controllers\Module\Sellers\Cuts\ExtraIncomeController;
use App\Http\Controllers\Module\Sellers\Cuts\InstallationController;
use App\Http\Controllers\Module\Sellers\Cuts\ObservationsController;
use App\Http\Controllers\Module\Sellers\Cuts\SuppliersExpensesController;
use App\Http\Controllers\Module\Setting\Tools\ImportController;
use App\Http\Controllers\Module\Setting\Credential\CredentialUpdateController;
use App\Http\Controllers\Module\Setting\MediumSale\MediumSaleController;
use App\Http\Controllers\Module\Setting\Commission\ComissionController;
use App\Http\Controllers\Module\Setting\TypeSeller\TypeSellerController;
use App\Http\Controllers\Module\Setting\StatusSeller\StatusSellerController;
use App\Http\Controllers\Module\Setting\MethodPayment\MethodPaymentController;
use App\Http\Controllers\Module\Setting\ListTemplateVerification\ListTemplateVerificationController;
use App\Http\Controllers\Module\Setting\TemplateTask\TemplateTaskController;
use App\Http\Controllers\RegisterVendorController;
use App\Http\Controllers\StaticsController;
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

Auth::routes();
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
            // Module Configuracion
            Route::group(['prefix' => 'configuracion', 'namespace' => 'Setting'], function () {
                Route::get('/', 'SettingController@index');
                Route::post('/debt-payment-client-recurrent', 'SettingController@debtPaymentClientRecurrent');
                Route::post('/debt-payment-client-custom', 'SettingController@debtPaymentClientCustom');

                Route::group(['prefix' => 'debitcustom'], function () {
                    Route::get('/', 'SettingDebitPaymentCustomController@index');
                    Route::post('/add', 'SettingDebitPaymentCustomController@store');
                    Route::get('/editar/{id}', 'SettingDebitPaymentCustomController@edit');
                    Route::post('/update/{id}', 'SettingDebitPaymentCustomController@update');
                    Route::post('/destroy/{id}', 'SettingDebitPaymentCustomController@destroy');
                    Route::post('/table', 'SettingDebitPaymentCustomController@table');
                });

                Route::group(['prefix' => 'company-information'], function () {
                    Route::get('/', 'CompanyInformation\CompanyInformationController@index');
                    Route::post('/add', 'CompanyInformation\CompanyInformationController@store');
                    Route::get('/editar/{id}', 'CompanyInformation\CompanyInformationController@edit');
                    Route::post('/get-data-company', 'CompanyInformation\CompanyInformationController@getDataCompany');
                    Route::post('/update/{id}', 'CompanyInformation\CompanyInformationController@update');
                    Route::post('/destroy/{id}', 'CompanyInformation\CompanyInformationController@destroy');
                });
                Route::group(['prefix' => 'billing-reminder'], function () {
                    Route::get('/editar/{id}', 'BillingReminder\BillingReminderController@edit');
                    Route::post('/update/{id}', 'BillingReminder\BillingReminderController@update');
                });

                Route::group(['prefix' => 'email-setting'], function () {
                    Route::get('/editar/{id}', 'EmailSetting\EmailSettingController@edit');
                    Route::post('/update/{id}', 'EmailSetting\EmailSettingController@update');
                });

                Route::group(['prefix' => 'command'], function () {
                    Route::get('/', 'CommandConfigController@index');
                    Route::post('/update/{id}', 'CommandConfigController@update');
                });


                //Additional -fields
                Route::group(['prefix' => 'additional-fields'], function () {
                    Route::get('/', 'SettingAdditionalFieldController@index');
                    Route::post('/add', 'SettingAdditionalFieldController@store');
                    Route::get('/editar/{id}', 'SettingAdditionalFieldController@edit');
                    Route::post('/update/{id}', 'SettingAdditionalFieldController@update');
                    Route::post('/destroy/{id}', 'SettingAdditionalFieldController@destroy');
                    Route::post('/table', 'SettingAdditionalFieldController@table');
                    Route::post('/get-required-value/{id}', 'SettingAdditionalFieldController@getRequiredValue');
                    Route::post('/update-position-field', 'SettingAdditionalFieldController@updatePositionField');
                });

                //setting tolls

                Route::group(['prefix' => 'tools-import'], function () {
                    Route::get('/', [ImportController::class, 'index']);
                    Route::get('/crear', [ImportController::class, 'create']);
                    Route::post('/create', [ImportController::class, 'store']);
                    Route::get('/{id}/editar', [ImportController::class, 'edit']);
                    Route::post('/{id}/update', [ImportController::class, 'update']);
                    Route::post('/destroy/{id}', [ImportController::class, 'destroy']);
                    Route::post('/table', [ImportController::class, 'table']);
                    Route::post('/get-example-for-this-module', [ImportController::class, 'getExampleForThisModule']);
                });

                // Config de credencial
                Route::group(['prefix' => 'credencial'], function () {
                    Route::get('/modificar-credencial', [CredentialUpdateController::class, 'changeImageCredential']);
                    Route::get('/image-front', [CredentialUpdateController::class, 'getFrontalImagePath']);
                    Route::get('/image-back', [CredentialUpdateController::class, 'getBackImagePath']);
                    Route::get('/image-logo', [CredentialUpdateController::class, 'getLogoImagePath']);
                    Route::post('/upload', [CredentialUpdateController::class, 'upload']);
                });

                // Medios de venta
                Route::group(['prefix' => 'medios-de-venta'], function () {
                    Route::get('/', [MediumSaleController::class, 'index']);
                    Route::get('/get-mediums-sales', [MediumSaleController::class, 'getAll']);
                    Route::get('/{id}/get-by-id', [MediumSaleController::class, 'getById']);
                    Route::post('/create', [MediumSaleController::class, 'store']);
                    Route::post('/{id}/update', [MediumSaleController::class, 'update']);
                    Route::delete('/{id}/destroy', [MediumSaleController::class, 'destroy']);
                });

                // Comisiones a los vendedores
                Route::group(['prefix' => 'comisiones'], function () {
                    Route::get('/get-types-sellers', [ComissionController::class, 'getTypeSeller']);
                    Route::get('/{id}/get-commissions-pending', [ComissionController::class, 'countPendingCommissions']);
                    Route::get('/{id}/get-by-type-seller', [ComissionController::class, 'getSellerByType']);
                    Route::get('/{id}/get-total-amount-commission', [ComissionController::class, 'getTotalAmountOfTheCommission']);
                    Route::get('/{id}/get-commissions-by-seller', [ComissionController::class, 'getCommissionsNotPaidToTheSeller']);
                    Route::get('/get-list-commissions-by-seller/{seller_id}', [ComissionController::class, 'getListOfCommissionsToBePaid']);
                    Route::get('/{id}/get-details-commission', [ComissionController::class, 'getCommissionWithDetails']);
                    Route::post('/create-comision-internal-distributor', [ComissionController::class, 'createComission']);
                });

                // Reglas de las comisiones
                Route::group(['prefix' => 'reglas-comisiones'], function () {
                    Route::get('/', [CommissionRuleController::class, 'index']);
                    Route::get('/editar/{id_rule}', [CommissionRuleController::class, 'edit']);
                    Route::get('/vendedores/{id_rule}', [CommissionRuleController::class, 'showVendors']);
                    Route::get('/get-all-rules', [CommissionRuleController::class, 'getAllRules']);
                    Route::get('/get-sellers-by-type/{id_type}', [CommissionRuleController::class, 'getSellersByType']);
                    Route::get('/get-sellers/{id_rule}', [CommissionRuleController::class, 'getRuleWithSellers']);
                    Route::get('/crear', [CommissionRuleController::class, 'create']);
                    Route::get('/get-rule-by-id/{id_rule}', [CommissionRuleController::class, 'getRuleById']);
                    Route::get('/get-rule-by-seller/{seller_id}', [CommissionRuleController::class, 'getRuleByIdSeller']);
                    Route::post('/create', [CommissionRuleController::class, 'store']);
                    Route::post('/update/{id_rule}', [CommissionRuleController::class, 'update']);
                    Route::delete('/delete/{id_rule}', [CommissionRuleController::class, 'destroy']);
                });

                // Configurar los tipos de vendedores
                Route::group(['prefix' => 'tipos-vendedores'], function () {
                    Route::get('/', [TypeSellerController::class, 'index']);
                    Route::get('/get-all-types', [TypeSellerController::class, 'getAll']);
                    Route::get('/{id}/edit', [TypeSellerController::class, 'edit']);
                    Route::post('/create', [TypeSellerController::class, 'store']);
                    Route::post('/{id}/update', [TypeSellerController::class, 'update']);
                    Route::delete('/{id}/destroy', [TypeSellerController::class, 'destroy']);
                });

                // Configurar los status de los vendedores
                Route::group(['prefix' => 'estados-vendedores'], function () {
                    Route::get('/', [StatusSellerController::class, 'index']);
                    Route::get('/get-all-status', [StatusSellerController::class, 'getAll']);
                    Route::get('/{id}/edit', [StatusSellerController::class, 'edit']);
                    Route::post('/create', [StatusSellerController::class, 'store']);
                    Route::post('/{id}/update', [StatusSellerController::class, 'update']);
                    Route::delete('/{id}/destroy', [StatusSellerController::class, 'destroy']);
                });

                // Configurar metodos de pago
                Route::group(['prefix' => 'metodos-de-pago'], function () {
                    Route::get('/', [MethodPaymentController::class, 'index']);
                    Route::get('/get-all-methods', [MethodPaymentController::class, 'getAll']);
                    Route::get('/{id}/edit', [MethodPaymentController::class, 'edit']);
                    Route::post('/create', [MethodPaymentController::class, 'store']);
                    Route::post('/{id}/update', [MethodPaymentController::class, 'update']);
                    Route::delete('/{id}/destroy', [MethodPaymentController::class, 'destroy']);
                });

                // Configurar credenciales de google maps
                Route::group(['prefix' => 'credenciales-google-maps'], function () {
                    Route::get('/', [MapCredentialController::class, 'index']);
                    Route::get('/edit', [MapCredentialController::class, 'edit']);
                    Route::post('/create', [MapCredentialController::class, 'store']);
                    Route::post('/{id}/update', [MapCredentialController::class, 'update']);
                    Route::delete('/{id}/destroy', [MapCredentialController::class, 'destroy']);
                });

                // Configurar los rangos de venta
                Route::group(['prefix' => 'rangos-venta'], function () {
                    Route::get('/', [RangeSaleController::class, 'index']);
                    Route::get('/get-all-ranges-sales', [RangeSaleController::class, 'getListRangesSales']);
                    Route::get('/sector-one', [RangeSaleController::class, 'getSectorOne']);
                    Route::get('/sector-two', [RangeSaleController::class, 'getSectorTwo']);
                    Route::get('/sector-three', [RangeSaleController::class, 'getSectorThree']);
                    Route::get('/{id}/edit', [RangeSaleController::class, 'edit']);
                    Route::post('/{id}/update', [RangeSaleController::class, 'update']);
                });

                Route::group(['prefix' => 'work-flow', 'namespace' => 'WorkFlow'], function () {
                    Route::get('/', 'WorkFlowController@index');
                    Route::post('/add', 'WorkFlowController@store');
                    Route::get('/editar/{id}', 'WorkFlowController@edit');
                    Route::post('/update/{id}', 'WorkFlowController@update');
                    Route::post('/destroy/{id}', 'WorkFlowController@destroy');
                    Route::post('/table', 'WorkFlowController@table');
                });

                Route::group(['prefix' => 'list-template-verification'], function () {
                    Route::get('/', [ListTemplateVerificationController::class, 'index']);
                    Route::post('/add', [ListTemplateVerificationController::class, 'store']);
                    Route::get('/editar/{id}', [ListTemplateVerificationController::class, 'edit']);
                    Route::post('/update/{id}', [ListTemplateVerificationController::class, 'update']);
                    Route::post('/destroy/{id}', [ListTemplateVerificationController::class, 'destroy']);
                    Route::post('/table', [ListTemplateVerificationController::class, 'table']);
                    Route::post('/get-check-list-template/{id}', [ListTemplateVerificationController::class, 'getChecksById']);
                });


                Route::group(['prefix' => 'template-task'], function () {
                    Route::get('/', [TemplateTaskController::class, 'index']);
                    Route::post('/add', [TemplateTaskController::class, 'store']);
                    Route::get('/editar/{id}', [TemplateTaskController::class, 'edit']);
                    Route::post('/update/{id}', [TemplateTaskController::class, 'update']);
                    Route::post('/destroy/{id}', [TemplateTaskController::class, 'destroy']);
                    Route::post('/table', [TemplateTaskController::class, 'table']);
                    Route::post('/get-data-template/{id}', [TemplateTaskController::class, 'getDataTemplate']);
                });

                Route::group(['prefix' => 'nomenclature', 'namespace' => 'Nomenclature'], function () {
                    Route::get('/', 'NomenclatureController@index');
                    Route::post('/add', 'NomenclatureController@store');
                    Route::get('/editar/{id}', 'NomenclatureController@edit');
                    Route::post('/update/{id}', 'NomenclatureController@update');
                    Route::post('/destroy/{id}', 'NomenclatureController@destroy');
                    Route::post('/assign_client/{id}', 'NomenclatureController@assignClient');
                    Route::post('/get-nomenclature-by-client/{id}', 'NomenclatureController@getNomenclatureByClient');
                    Route::post('/table', 'NomenclatureController@table');
                    Route::post('/add-multiple-nomenclatures', 'NomenclatureController@generarNomenclatura');
                    Route::post('/change-client', 'NomenclatureController@changeClient');
                });

                Route::group(['prefix' => 'team', 'namespace' => 'Team'], function () {
                    Route::get('/', 'TeamController@index');
                    Route::post('/add', 'TeamController@store');
                    Route::get('/editar/{id}', 'TeamController@edit');
                    Route::post('/update/{id}', 'TeamController@update');
                    Route::post('/destroy/{id}', 'TeamController@destroy');
                    Route::post('/table', 'TeamController@table');
                });

                Route::group(['prefix' => 'service_in_address_list', 'namespace' => 'ServiceInAddressList'], function () {
                    Route::get('/', 'ServiceInAddressListController@index');
                    Route::post('/add', 'ServiceInAddressListController@store');
                    Route::get('/editar/{id}', 'ServiceInAddressListController@edit');
                    Route::post('/update/{id}', 'ServiceInAddressListController@update');
                    Route::post('/destroy/{id}', 'ServiceInAddressListController@destroy');
                    Route::post('/table', 'ServiceInAddressListController@table');
                    Route::post('/mikrotik-remove-address-list/{id}', 'ServiceInAddressListController@removeServiceToAddressList');
                });

                Route::group(['prefix' => 'rules', 'namespace' => 'Rules'], function () {
                    Route::get('/', 'RuleController@index');
                    Route::get('/get-sellers-by-type/{type}', 'RuleController@getSellersByType');
                    Route::get('/create', 'RuleController@create');
                    Route::post('/store', 'RuleController@store');
                    Route::get('/edit/{id}', 'RuleController@edit');
                    Route::post('/update/{id}', 'RuleController@update');
                    Route::post('/destroy/{id}', 'RuleController@destroy');
                    Route::post('/table', 'RuleController@table');
                    Route::post('/general-config', 'RuleController@generalConfig');
                    Route::post('/save-general-config', 'RuleController@saveGeneralConfig');
                    Route::get('/get-all', 'RuleController@getAll');
                });

                Route::group(['prefix' => 'finance-notification'], function () {
                    Route::get('/', 'Finance\ConfigFinanceNotificationController@index');
                    Route::post('/get-data-tabs', 'Finance\ConfigFinanceNotificationController@getDataTabs');
                    Route::post('/update/{id}', 'Finance\ConfigFinanceNotificationController@update');
                });
            });

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
            Route::group(['prefix' => 'sellers', 'namespace' => 'Sellers'], function () {
                Route::group(['prefix' => 'seller', 'namespace' => 'Seller'], function () {
                    Route::get('/', 'SellerController@index');
                    Route::post('/add', 'SellerController@store');
                    Route::get('/editar/{id}', 'SellerController@edit');
                    Route::post('/update/{id}', 'SellerController@update');
                    Route::post('/destroy/{id}', 'SellerController@destroy');
                    Route::post('/table', 'SellerController@table');
                    Route::post('/get-prospects/{id}', 'SellerController@table');
                });
                Route::group(['prefix' => 'cuts', 'namespace' => 'Cuts'], function () {
                    Route::resource('/extras-incomes', ExtraIncomeController::class)->except('index');
                    Route::post('/extras-incomes-list/{id}', 'ExtraIncomeController@index');
                    Route::resource('/installations', InstallationController::class)->except('index');
                    Route::post('/installations-list/{id}', 'InstallationController@index');
                    Route::resource('/suppliers-expenses', SuppliersExpensesController::class)->except('index');
                    Route::post('/suppliers-expenses-list/{id}', 'SuppliersExpensesController@index');
                    Route::resource('/observations', ObservationsController::class)->except('index');
                    Route::post('/observations-list/{id}', 'ObservationsController@index');
                    Route::get('/get-user-current-box/{id}', 'BoxController@getCurrentBox');
                    Route::get('/box/{id}', 'BoxController@findBox');
                    Route::get('/box-pdf/{id}', 'BoxController@pdf');
                    Route::get('/get-received-payments-by-box/{id}', 'BoxController@getReceivedPaymentsByBox');
                    Route::post('/close-user-current-box/{id}', 'BoxController@close');
                    Route::get('/technicals', 'BoxController@technicals');
                    Route::post('/{id}', 'BoxController@cuts');
                });
            });

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

            // Module CRM
            Route::group(['prefix' => 'crm', 'namespace' => 'Crm'], function () {
                Route::get('/', 'DashboardController@index');
                Route::get('/success/{id}', 'CrmController@success');
                Route::get('/listar', 'CrmController@index')->name('crm');
                Route::get('/crear', 'CrmController@create');
                Route::post('/add', 'CrmController@store');
                Route::get('/editar/{id}', 'CrmController@edit');
                Route::post('/update/{id}', 'CrmInformationController@update');
                Route::get('/view-of-convert-crm-to-client/{id}', 'CrmController@viewOfConvertCrmToClient');
                Route::post('/convert-to-client/{id}', 'CrmController@convertToClient');
                Route::post('/update-last-contacted/{id}', 'CrmController@updateLastContacted');
                Route::post('/destroy/{id}', 'CrmController@destroy');
                Route::post('/table', 'CrmController@table');
                Route::post('information/{crmId}/get-crm-main-information-id-and-crm-lead-information-id', 'CrmController@getCrmMainInformationIdAndCrmLeadInformationId');

                Route::group(['prefix' => 'document'], function () {
                    Route::post('/add/{idCrm}', 'DocumentCrmController@store');
                    Route::post('/update/{idCrm}', 'DocumentCrmController@update');
                    Route::post('/upload-file/{id}', 'DocumentCrmController@uploadFile');
                    Route::post('/table', 'DocumentCrmController@table');
                    Route::post('/destroy/{id}', 'DocumentCrmController@destroy');
                    Route::post('/generate_contract/{id}', 'DocumentCrmController@generateContract');
                    Route::get('/load_content_template', 'DocumentCrmController@loadContentTemplate');
                    Route::post('/show_content_template', 'DocumentCrmController@showContentTemplate');
                });
            });

            Route::post('/helper/get-value-colony-state-municipality', 'Shared\ComponentSelectStateMunicipalityAndColonyController@getValueDB');
            Route::post('/helper/get-services-by-client-main-information', 'Shared\ComponentSearchServiceController@getServiceByClientMainInformationId');

            // Module Cliente
            Route::group(['prefix' => 'cliente', 'namespace' => 'Client'], function () {
                Route::get('/', 'DashboardController@index');
                Route::get('/success/{id}', 'ClientController@success');
                Route::post('/debit/{id}', 'ClientController@getClientDebit');
                Route::get('/listar', 'ClientController@index')->name('cliente');
                Route::get('/actives', 'ClientController@getActiveClients');
                Route::get('/get-client-filtered-by-bundle-service/{id}', 'ClientController@getClientFilteredByBundleService');
                Route::get('/get-client-filtered-by-internet-service/{id}', 'ClientController@getClientFilteredByInternetService');
                Route::get('/get-client-filtered-by-custom-service/{id}', 'ClientController@getClientFilteredByCustomService');
                Route::get('/get-client-filtered-by-voz-service/{id}', 'ClientController@getClientFilteredByVozService');
                Route::get('/crear', 'ClientController@create');
                Route::post('/add', 'ClientController@store');
                Route::get('/editar/{id}', 'ClientController@edit');
                Route::post('/update/{id}', 'ClientInformationController@update');
                Route::post('/edit_id', 'ClientInformationController@editId');
                Route::get('/payment_today', 'ClientController@getClientToPaymentToDay');
                Route::get('/suspend_today', 'ClientController@getClientToSuspendToDay');
                Route::post('/edit_court_date', 'ClientController@editCourtDate');
                Route::post('/force_delete', 'ClientController@forceDelete');
                Route::post('/edit_date_payment', 'ClientController@editDatePayment');
                Route::post('/edit_balance', 'ClientController@editBalance');
                Route::post('/payment_instalation_cost/services', 'ClientController@paymentInstalationCostServices');
                Route::post('/payment_activation_cost', 'ClientController@paymentActivationCost');

                Route::post('/{clientId}/get-client-main-information-id-and-client-additional-information-id', 'ClientController@getClientMainInformationIdAndClientAdditionalInformationId');
                Route::post('/get-is-promise-payment/{id}', 'ClientController@getIsPromisePayment');
                Route::post('/get-client-id-by-client-main-information-id/{id}', 'ClientController@geClientIdByClientMainInformationId');
                Route::post('/get-promotions/{id}', 'ClientController@getPromotionsByClient');
                Route::post('/get-period-by-amount/{id}', 'ClientController@getPaymentPeriodByAmount');

                Route::group(['prefix' => 'document'], function () {
                    Route::post('/add/{idClient}', 'DocumentClientController@store');
                    Route::post('/update/{idClient}', 'DocumentClientController@update');
                    Route::post('/upload-file/{id}', 'DocumentClientController@uploadFile');
                    Route::post('/table', 'DocumentClientController@table');
                    Route::post('/destroy/{id}', 'DocumentClientController@destroy');
                    Route::post('/generate_contract/{id}', 'DocumentClientController@generateContract');
                    Route::get('/load_content_template', 'DocumentClientController@loadContentTemplate');
                    Route::post('/show_content_template', 'DocumentClientController@showContentTemplate');
                });
                Route::post('/get-client-with-balance/{id}', 'ClientInformationController@getClientWithBalance');
                Route::post('/get-tickets-open/{id}', 'ClientInformationController@getClientTicketsOpen');
                Route::post('/get-client-status/{id}', 'ClientInformationController@getClientStatus');
                Route::post('/get-data-client-to-select-component/{id}', 'ClientInformationController@getDataClientToSelectComponent');


                Route::post('/destroy/{id}', 'ClientController@destroy');
                Route::post('/table', 'ClientController@table');

                Route::post('/has-service/{id}', 'ClientServiceController@hasService');
                Route::post('/can-add-service/{id}', 'ClientServiceController@canAddService');

                Route::group(['prefix' => 'clientbundleservice'], function () {
                    Route::post('/bundle/{id}', 'ClientBundleServiceController@getPlansById');
                    Route::post('/bundle/edit/{id}', 'ClientBundleServiceController@getEditedServiceBundleById');
                    Route::post('/table', 'ClientBundleServiceController@table');
                    Route::post('/update/{id}', 'ClientBundleServiceController@update');
                    Route::post('/crear/{id}', 'ClientBundleServiceController@store');
                    Route::post('/destroy/{id}', 'ClientBundleServiceController@destroy');
                    Route::post('/bundle/change-bundle/{id}', 'ClientBundleServiceController@changeBundle');
                    Route::post('/bundle/get-equals/{id}', 'ClientBundleServiceController@getEqualsPlansById');
                    Route::post('/bundle/get-plans-to-change/{id}', 'ClientBundleServiceController@getPlansToChangeById');
                });
                Route::group(['prefix' => 'clientinternetservice'], function () {
                    Route::post('/table', 'ClientInternetServiceController@table');
                    Route::post('/update/{id}', 'ClientInternetServiceController@update');
                    Route::post('/crear/{id}', 'ClientInternetServiceController@store');
                    Route::post('/destroy/{id}', 'ClientInternetServiceController@destroy');
                    Route::post('/change-internet/{id}', 'ClientInternetServiceController@changeInternet');
                    Route::post('/refresh-ip/{id}', 'ClientInternetServiceController@refreshIp');
                });
                Route::group(['prefix' => 'clientvozservice'], function () {
                    Route::post('/table', 'ClientVozServiceController@table');
                    Route::post('/update/{id}', 'ClientVozServiceController@update');
                    Route::post('/crear/{id}', 'ClientVozServiceController@store');
                    Route::post('/destroy/{id}', 'ClientVozServiceController@destroy');
                    Route::post('/change-voz/{id}', 'ClientVozServiceController@changeVoz');
                });
                Route::group(['prefix' => 'clientcustomservice'], function () {
                    Route::post('/table', 'ClientCustomServiceController@table');
                    Route::post('/update/{id}', 'ClientCustomServiceController@update');
                    Route::post('/crear/{id}', 'ClientCustomServiceController@store');
                    Route::post('/destroy/{id}', 'ClientCustomServiceController@destroy');
                    Route::post('/change-custom/{id}', 'ClientCustomServiceController@changeCustom');
                });

                Route::post('/clientbundleservice/table', 'ClientBundleServiceController@table');

                Route::group(['prefix' => 'billing'], function () {
                    Route::post('/update-billing-configuration/{id}', 'ClientBillingConfigurationController@update');
                    Route::post('/client-debit-rectification-agreement/{id}', 'ClientBillingConfigurationController@getClientDebitRectificationAgreement');

                    Route::post('/update-billing-address/{id}', 'ClientBillingAddressController@update');
                    Route::post('/update-reminders-configuration/{id}', 'ClientBillingRemindersConfigurationController@update');
                    Route::post('/get-reminder-payment-amount/{id}', 'ClientBillingRemindersConfigurationController@getReminderPaymentAmount');
                    Route::post('/get-billing-information-block/{id}', 'ClientBillingConfigurationController@getBillingInformationBlock');
                    Route::post('/get-payment-method/{id}', 'ClientBillingConfigurationController@getPaymentMethod');
                    Route::post('/get-type-of-billing-by-client-id/{id}', 'ClientBillingConfigurationController@getTypeOfBillingByClientId');


                    Route::group(['prefix' => 'payment'], function () {
                        Route::post('/crear/{id}', 'ClientPaymentController@store');
                        Route::post('/update/{id}', 'ClientPaymentController@update');
                        Route::post('/destroy/{id}', 'ClientPaymentController@destroy');
                        Route::post('/table', 'ClientPaymentController@table');
                        Route::get('/pdf/{id}', 'ClientPaymentController@getPrintPdf');

                        Route::post('/get-totals/{id}', 'ClientPaymentController@getTotals');
                        Route::post('/get-cost-all-service-active/{id}', 'ClientPaymentController@getCostAllServiceActive');
                        Route::post('/get-active-service-expiration/{id}', 'ClientPaymentController@getActiveServiceExpiration');
                        Route::post('/get-cost-all-service/{id}', 'ClientPaymentController@getCostAllService');
                    });

                    Route::group(['prefix' => 'transaction'], function () {
                        Route::post('/crear/{id}', 'ClientTransactionController@store');
                        Route::post('/update/{id}', 'ClientTransactionController@update');
                        Route::post('/get-totals/{id}', 'ClientTransactionController@getTotals');
                        Route::post('/destroy/{id}', 'ClientTransactionController@destroy');
                        Route::post('/table', 'ClientTransactionController@table');
                    });

                    Route::group(['prefix' => 'invoice'], function () {
                        Route::post('/crear/{id}', 'ClientInvoiceController@store');
                        Route::post('/update/{id}', 'ClientInvoiceController@update');
                        Route::post('/get-totals/{id}', 'ClientInvoiceController@getTotals');
                        Route::post('/destroy/{id}', 'ClientInvoiceController@destroy');
                        Route::post('/table', 'ClientInvoiceController@table');
                        Route::get('/pdf/{id}', 'ClientInvoiceController@getPrintPdf');
                        Route::get('/create-new-client-invoice/{id}', 'ClientInvoiceController@createManualClientInvoice');
                    });
                });
                Route::group(['prefix' => 'statistics'], function () {
                    Route::get('/get-active-connections/{id}', 'ClientStatisticsController@getActiveConnections');
                    Route::get('/get-consumption-summary/{id}', 'ClientStatisticsController@getConsumptionSummary');
                    Route::get('/get-daily-usage/{id}', 'ClientStatisticsController@getDailyUsage');
                    Route::get('/get-fup-stats/{id}', 'ClientStatisticsController@getFupStatistics');
                    Route::get('/get-history/{id}', 'ClientStatisticsController@getConnectionHistory');
                    // Ping monitoring (clientes dedicados)
                    Route::get('/get-ping-status/{id}',  'ClientPingController@getPingStatus');
                    Route::get('/get-ping-history/{id}', 'ClientPingController@getPingHistory');
                    Route::get('/get-ping-daily/{id}',   'ClientPingController@getPingDailySummary');
                });
            });

            // Módulo de vendedores
            Route::group(['prefix' => 'vendedores'], function () {
                Route::get('/', [SellerController::class, 'showView'])->name('vendedores.showView');
                Route::get('/seguimiento-me/', [SellerController::class, 'showPanel'])->name('vendedores.showPanel');
                Route::get('/data', [SellerController::class, 'index'])->name('vendedores.index');
                Route::get('/{id}/seguimiento-vendedor/{seller_id}', [SellerController::class, 'edit'])->name('vendedores.seguimiento');
                Route::get('/{id}/getDataById', [SellerController::class, 'getDataById'])->name('vendedores.getDataById');
                Route::get('/get-status-sellers', [SellerController::class, 'getStatusSeller'])->name('vendedores.getStatusSeller');
                Route::get('/get-type-sellers', [SellerController::class, 'getTypesSeller'])->name('vendedores.getTypesSeller');
                Route::post('/{id}/update', [SellerController::class, 'update'])->name('vendedores.update');
                Route::get('/{id}/pdf', [SellerController::class, 'pdf'])->name('vendedores.pdf');

                // Dashboard
                Route::group(['prefix' => 'dashboard'], function () {
                    Route::get('/', [VendorController::class, 'index'])->name('dashboard');
                });

                // Prospectos
                Route::group(['prefix' => 'prospectos'], function () {
                    Route::get('/', [ProspectController::class, 'index'])->name('prospectos.index');
                    Route::get('{id}/getById', [ProspectController::class, 'getById'])->name('prospectos.getById');
                    Route::get('/statusProspects/{startDate}/{endDate}', [ProspectController::class, 'statusProspects'])->name('prospectos.statusProspects');
                });

                // Ventas
                Route::group(['prefix' => 'ventas'], function () {
                    Route::get('/', [SaleController::class, 'index'])->name('ventas.index');
                    Route::get('{id}/salesBySeller', [SaleController::class, 'salesBySeller'])->name('ventas.salesBySeller');
                    Route::post('/sales-by-seller/{id}', [SaleController::class, 'getSalesBySeller']);
                    Route::get('/salesByMedium/{startDate}/{endDate}', [SaleController::class, 'salesByMedium'])->name('ventas.salesByMedium');
                    Route::get('/salesByMonth', [SaleController::class, 'salesByMonth'])->name('ventas.salesByMonth');
                    Route::get('/salesAndProspectsByDateRange/{startDate}/{endDate}', [SaleController::class, 'salesAndProspectsByDateRange'])->name('ventas.salesAndProspectsByDateRange');
                    Route::get('/rankingSales/{startDate}/{endDate}', [SaleController::class, 'rankingSales'])->name('ventas.rankingSales');

                    Route::get('/total-prospects', [SaleController::class, 'getTotalProspects'])->name('ventas.getTotalProspects');
                    Route::get('/total-sales', [SaleController::class, 'getTotalSales'])->name('ventas.getTotalSales');
                    Route::get('/total-lost-sales', [SaleController::class, 'getLostSales'])->name('ventas.getLostSales');
                });

                // Pagos de los clientes
                Route::group(['prefix' => 'payments'], function () {
                    Route::get('/', [PaymentClientController::class, 'index'])->name('index');
                    Route::get('{id}/getListPayments', [PaymentClientController::class, 'getListPaymentsOfCustomersBySeller'])->name('getListPayments');
                    Route::get('{id}/getPayments', [PaymentClientController::class, 'getPaymentsOfCustomersBySeller'])->name('getPayments');
                    Route::get('{id}/getDataSeller', [PaymentClientController::class, 'getDataSeller'])->name('getDataSeller');
                    Route::post('{id}/getRuleDataSeller', [PaymentClientController::class, 'getRuleDataSeller'])->name('getRuleDataSeller');
                    Route::post('get-periods-from-seller/{id}', [PaymentClientController::class, 'getPeriodsFromSeller'])->name('getPeriodsFromSeller');
                    Route::post('{id}/getMontlyCommissionsBySeller', [PaymentClientController::class, 'getMontlyCommissionsBySeller'])->name('getMontlyCommissionsBySeller');
                });

                // Pagos de los vendedores
                Route::group(['prefix' => 'payments-sellers'], function () {
                    Route::get('{id}/get-all-payments-of-seller', [PaymentSellerController::class, 'getPaymentsBySellerId']);
                    Route::get('{id_seller}/{id_payment}/get-ticket-of-seller', [PaymentSellerController::class, 'getTicket']);
                    Route::get('/{id_seller}/{id_payment}/download-receipt-pdf', [PaymentSellerController::class, 'downloadReceipt']);
                    Route::get('/{id}/edit-payment', [PaymentSellerController::class, 'getDataToEditPayment']);
                    Route::get('/{id}/edit', [PaymentSellerController::class, 'edit']);
                    Route::post('/create', [PaymentSellerController::class, 'registerPayment']);
                    Route::post('/{id}/update', [PaymentSellerController::class, 'updatePayment']);
                    Route::delete('/{id}/destroy', [PaymentSellerController::class, 'deletePayment']);
                    Route::post('/store', [PaymentSellerController::class, 'store']);
                    Route::post('/details', [PaymentSellerController::class, 'details']);
                    Route::post('/details-from-payment-type', [PaymentSellerController::class, 'detailsFromPaymentType']);
                    Route::post('/details-from-discount-type', [PaymentSellerController::class, 'detailsFromDiscountType']);
                    Route::get('/payment-receipt/{id}', [PaymentSellerController::class, 'paymentReceiptPDF']);
                    Route::get('/payment-receipt-by-type/{id}', [PaymentSellerController::class, 'paymentReceiptByTypePDF']);
                    Route::get('/discount-receipt/{id}', [PaymentSellerController::class, 'discountReceiptPDF']);
                    Route::post('/statement-account/{id}', [PaymentSellerController::class, 'statementAccount']);
                    Route::post('/incomes-account/{id}', [PaymentSellerController::class, 'incomesAccount']);
                    Route::post('/expenses-account/{id}', [PaymentSellerController::class, 'expensesAccount']);
                    Route::post('/debt-account/{id}', [PaymentSellerController::class, 'debtAccount']);
                    Route::post('/discount-account/{id}', [PaymentSellerController::class, 'discountAccount']);
                    Route::post('/payments-by-seller/{id}', [PaymentSellerController::class, 'paymentsBySeller']);
                    Route::post('/payment-signature/{id}', [PaymentSellerController::class, 'paymentSignature']);
                    Route::post('/pending-payments-by-seller/{id}', [PaymentSellerController::class, 'pendingPaymentsBySeller']);
                    Route::post('/discounts-by-seller/{id}', [PaymentSellerController::class, 'discountsBySeller']);
                    Route::get('/pending-discounts/{id}', [PaymentSellerController::class, 'pendingDiscountsBySeller']);
                    Route::post('/collect-debt', [PaymentSellerController::class, 'collectDebt']);
                });

                // Transacciones
                Route::group(['prefix' => 'transacciones'], function () {
                    Route::get('{id_seller}/{start_date}/{end_date}/{method}/get-transactions-by-seller', [SellerTransactionController::class, 'getTransactionsList']);
                });
            });


            Route::group(['prefix' => 'maps', 'namespace' => 'Maps'], function () {
                Route::get('/zones', [ConnectionsController::class, 'zones']);
                Route::post('/get-clients', [ProyectsController::class, 'clients']);
                Route::post('/clients-without-project', [ProyectsController::class, 'clientsWithoutProject']);
                Route::resource('/layers', LayersController::class);
                Route::resource('/projects', ProyectsController::class);
                Route::post('/projects/move-folder/{node}/{to}', [ProyectsController::class, 'moveFolder']);
                Route::post('/projects/move-marker/{node}/{to}', [LayersController::class, 'moveMarker']);
                Route::post('/projects/move-folder/{node}', [ProyectsController::class, 'moveFolder']);
                Route::post('/projects/move-marker/{node}', [LayersController::class, 'moveMarker']);
                Route::post('/layers/configuration/{id}', [LayersController::class, 'configuration']);
                Route::post('/projects/move-marker/{node}', [LayersController::class, 'moveMarker']);
                Route::post('/layers/convert-from-project/{id}', [LayersController::class, 'convertLayersFromProject']);
                Route::post('/layers/convert-from-layer/{id}', [LayersController::class, 'convertLayerFromLayer']);
                Route::post('/layers/convert-from-tickeds', [LayersController::class, 'convertLayersFromTickeds']);
                Route::post('/layers/destroy-multiple', [LayersController::class, 'destroyMultiple']);
                Route::post('/layers/coords/{id}', [LayersController::class, 'coords']);
                Route::post('/layers/avaiables-routes/{id}', [LayersController::class, 'avaiablesRoutes']);
                Route::post('/layers/avaiables-routes', [LayersController::class, 'avaiablesRoutes']);
                Route::post('/layers/assign-routes/{id}', [LayersController::class, 'assignRoutes']);
                Route::post('/layers/create-input/{id}', [LayersController::class, 'createInput']);
                Route::post('/layers/update-input/{id}', [LayersController::class, 'updateInput']);
                Route::post('/layers/update-markers-distance-from-route/{id}', [LayersController::class, 'updateMarkersDistanceFromRoute']);
                Route::delete('/layers/unassign-route/{id}', [LayersController::class, 'unassignRoute']);
                Route::post('/layers/change-route-position/{id}', [LayersController::class, 'changeRoutePosition']);
                Route::get('/layers/devices/{id}', [LayersController::class, 'devicesFromRack']);
                Route::post('/change-classification', [LayersController::class, 'changeClassification']);
                Route::post('/client-to-service-box/{client}/{box}', [LayersController::class, 'addClientToServiceBox']);
                Route::post('/kmz/{id}', [KMZController::class, 'loadKMZ']);
                Route::post('/kmz', [KMZController::class, 'loadKMZ']);
                Route::post('/service-box/selected-clients/{id}', [ServiceBoxController::class, 'getSelectedClients']);
                Route::post('/service-box/avaiables-clients', [ServiceBoxController::class, 'getAvaiablesClients']);
                Route::post('/service-box/remove-clients', [ServiceBoxController::class, 'removeClients']);
                Route::post('/service-box/remove-client/{id}', [ServiceBoxController::class, 'removeClient']);
                Route::post('/service-box/add-clients/{id}', [ServiceBoxController::class, 'addClients']);
                Route::post('/service-box/save-port/{id}', [ServiceBoxController::class, 'savePort']);
                Route::post('/service-box/remove-client-from-drop/{id}', [ServiceBoxController::class, 'removeClientFromDrop']);

                //Connections
                Route::resource('/connections', ConnectionsController::class)->except('index');
                Route::post('/connections-multiple/{id}', [ConnectionsController::class, 'connectionsMultiple']);
                Route::post('/connections/cut/{id}', [ConnectionsController::class, 'cutConnections']);

                //Devices
                Route::resource('/devices', DevicesController::class)->except('index');
                Route::post('/devices/save-port/{id}', [DevicesController::class, 'savePort']);
                Route::post('/devices/add-ports/{id}', [DevicesController::class, 'addPorts']);
                Route::post('/devices/change-card-olt-direction/{id}', [DevicesController::class, 'changeCardOLTDirection']);
            });


            Route::group(['prefix' => 'olts', 'namespace' => 'OLTs'], function () {
                Route::get('/', [OLTsController::class, 'panel']);
                Route::post('/list', [OLTsController::class, 'oltList']);
                Route::post('/zones', [OLTsController::class, 'zones']);
                Route::post('/type-onus', [OLTsController::class, 'typeONUs']);
                Route::post('/nomenclatures', [OLTsController::class, 'nomenclatures']);
                Route::post('/uptime-env-temp', [OLTsController::class, 'getUptimeEnvTemp']);
                Route::post('/cards/{id}', [OLTsController::class, 'oltCardsDetails']);
                Route::post('/pon-ports/{id}', [OLTsController::class, 'getOltPonPortsDetails']);
                Route::post('/outage-pons/{id}', [OLTsController::class, 'getOutagePons']);
                Route::post('/outage-pons', [OLTsController::class, 'getOutagePons']);
                Route::post('/uplink-ports/{id}', [OLTsController::class, 'getOltUplinkPortsDetails']);
                Route::post('/vlans/{id}', [OLTsController::class, 'getVLANs']);

                Route::post('/dashboard-interruptions', [OLTsController::class, 'getDashboardInterruptions']);
                Route::post('/dashboard-onus-status/{id}', [OLTsController::class, 'getDashboardOnusStatus']);
                Route::post('/dashboard-onus-status', [OLTsController::class, 'getDashboardOnusStatus']);

                Route::get('/dashboard', [OLTsController::class, 'dashboard']);

                Route::group(['prefix' => 'onus'], function () {
                    Route::post('/create', [OLTsOnuController::class, 'store']);
                    Route::post('/sync/{id}', [OLTsOnuController::class, 'sync']);
                    Route::post('/get-by-client/{id}', [OLTsOnuController::class, 'getByClient']);
                    Route::post('/get-mgmt-ip/{id}', [OLTsOnuController::class, 'getMgmTIp']);
                    Route::post('/get-ip-address/{id}', [OLTsOnuController::class, 'getIpAddress']);
                    Route::post('/get-signal-and-status/{id}', [OLTsOnuController::class, 'getSignalAndStatus']);
                    Route::post('/configure-ethernet-port/{id}', [OLTsOnuController::class, 'configureEhernetPort']);
                    Route::post('/configure-wifi-port/{id}', [OLTsOnuController::class, 'configureWifiPort']);
                    Route::post('/update-service-port/{id}', [OLTsOnuController::class, 'updateServicePort']);
                    Route::post('/update-attached-vlans/{id}', [OLTsOnuController::class, 'changeAttachedVlans']);
                    Route::post('/set-onu-voip-port/{id}', [OLTsOnuController::class, 'setOnuVoipPort']);
                    Route::post('/update-channel/{id}', [OLTsOnuController::class, 'updateChannel']);
                    Route::post('/update-mode/{id}', [OLTsOnuController::class, 'updateMode']);
                    Route::delete('/remove/{id}', [OLTsOnuController::class, 'remove']);
                    Route::post('/traffic-graph/{id}', [OLTsOnuController::class, 'getTrafficGraph']);
                    Route::post('/image/{id}', [OLTsOnuController::class, 'getImageONU']);
                    Route::post('/signal-graph/{id}', [OLTsOnuController::class, 'getSignalGrap']);
                    Route::post('/update-mgmt-and-vo-ip/{id}', [OLTsOnuController::class, 'updateMgmtAndVoIp']);
                    Route::post('/full-status/{id}', [OLTsOnuController::class, 'getFullStatus']);
                    Route::post('/running-config/{id}', [OLTsOnuController::class, 'getRunningConfig']);
                    Route::post('/update-external-id/{id}', [OLTsOnuController::class, 'updateExternalId']);
                    Route::post('/change-web-user-pass/{id}', [OLTsOnuController::class, 'changeWebUserPass']);
                    Route::post('/set-catv/{id}', [OLTsOnuController::class, 'setCATV']);
                    Route::post('/change-onu-type/{id}', [OLTsOnuController::class, 'changeOnuType']);
                    Route::post('/unconfigured', [OLTsOnuController::class, 'getUnconfigured']);
                    Route::post('/unconfigured/{id}', [OLTsOnuController::class, 'getUnconfigured']);
                    Route::post('/saved-unconfigured', [OLTsOnuController::class, 'getSavedUnconfigured']);
                    Route::post('/configured', [OLTsOnuController::class, 'index']);
                    Route::post('/configured/{id}', [OLTsOnuController::class, 'index']);

                    Route::post('/signal/{sn}', [OLTsController::class, 'getSignalByOnu']);
                    Route::post('/enable-disable/{id}', [OLTsController::class, 'enableDisableOnu']);
                    Route::post('/resync/{id}', [OLTsController::class, 'resyncOnuConfig']);
                    Route::post('/reboot/{id}', [OLTsController::class, 'rebootOnu']);
                    Route::post('/move/{id}', [OLTsController::class, 'moveOnu']);
                    Route::post('/details/{id}', [OLTsController::class, 'getDetailsByONU']);
                    Route::post('/update-location/{id}', [OLTsController::class, 'updateOnuLocation']);
                    Route::post('/nomenclatures', [OLTsController::class, 'nomenclaturesFromOnus']);
                });

                Route::group(['prefix' => 'settings'], function () {
                    Route::post('/billings', [OLTsBillingController::class, 'index']);

                    Route::group(['prefix' => 'zones'], function () {
                        Route::post('/', [OLTsZonesController::class, 'index']);
                        Route::post('/store', [OLTsZonesController::class, 'store']);
                    });

                    Route::group(['prefix' => 'odbs'], function () {
                        Route::post('/', [OLTsODBsController::class, 'index']);
                        Route::post('/store', [OLTsODBsController::class, 'store']);
                    });

                    Route::group(['prefix' => 'type-onus'], function () {
                        Route::post('/', [OLTsTypeONUsController::class, 'index']);
                        Route::post('/store', [OLTsTypeONUsController::class, 'store']);
                    });

                    Route::group(['prefix' => 'profiles'], function () {
                        Route::post('/', [OLTsProfilesController::class, 'index']);
                        Route::post('/store', [OLTsProfilesController::class, 'store']);
                    });

                    Route::group(['prefix' => 'olts'], function () {
                        Route::post('/', [OLTsController::class, 'index']);
                        Route::post('/{id}/cards', [OLTsCardsController::class, 'index']);
                        Route::post('/{id}/pon-ports', [OLTsPonPortsController::class, 'index']);
                        Route::post('/{id}/uplink-ports', [OLTsUplinkPortsController::class, 'index']);
                        Route::post('/{id}/vlans', [OLTsVlansController::class, 'index']);
                        Route::post('/{id}/vlans/store', [OLTsVlansController::class, 'store']);
                    });
                });
            });

            Route::group(['prefix' => 'mapas', 'namespace' => 'Mapas'], function () {
                Route::get('/', [MapasController::class, 'index']);
                Route::post('/get_form', [MapasController::class, 'getForm'])->name('maps.getForm');
                Route::post('/object/create', [MapasController::class, 'objectCreate'])->name('maps.objectCreate');
                Route::post('/objects/get', [MapasController::class, 'getObject'])->name('maps.getObject');
                Route::post('/info_window', [MapasController::class, 'getInfoInfoWindow'])->name('maps.getInfoInfoWindow');
                Route::post('/data_form', [MapasController::class, 'getDataForm'])->name('maps.getDataForm');
                Route::post('/data_form/get_by_id', [MapasController::class, 'getDataFormById'])->name('maps.getDataFormById');
                Route::post('/catalog_form', [MapasController::class, 'getCatalogView'])->name('maps.get.catalog.view');

                Route::post('/site/poles', [MapasController::class, 'getPolesBySite'])->name('maps.getPolesBySite');

                Route::post('/object/position/update', [MapasController::class, 'updatePosition'])->name('maps.updatePosition');
                Route::post('/assing/list/type', [MapasController::class, 'objectListForSelectByType'])->name('maps.objectListForSelectByType');
                Route::post('/assing/list/pole', [MapasController::class, 'getAssingListPole'])->name('maps.getAssingListPole');

                Route::post('/search/inputs_or_ports', [MapasController::class, 'searchInputsOrPorts'])->name('maps.searchInputsOrPorts');
                /*
                |----------------------------------------------------------------------------
                |  POINT
                |----------------------------------------------------------------------------
                */
                Route::post('point/assign', [MapasController::class, 'assingPoint'])->name('maps.assingPoint');
                Route::post('point/destroy', [PointController::class, 'destroy'])->name('maps.point.destroy');
                /*
                |----------------------------------------------------------------------------
                |  BRANDS
                |----------------------------------------------------------------------------
                */
                Route::post('brand/index', [BrandController::class, 'index'])->name('maps.brand.index');
                Route::post('brand/store', [BrandController::class, 'store'])->name('maps.brand.store');
                Route::post('brand/update', [BrandController::class, 'update'])->name('maps.brand.update');
                Route::post('brand/list', [BrandController::class, 'getListToSelect'])->name('maps.brand.list');
                /*
                |----------------------------------------------------------------------------
                |  BOXES
                |----------------------------------------------------------------------------
                */
                Route::post('box/index', [BoxController::class, 'index'])->name('maps.box.index');
                Route::post('box/store', [BoxController::class, 'store'])->name('maps.box.store');
                Route::post('box/destroy', [BoxController::class, 'destroy'])->name('maps.box.destroy');

                Route::post('box_type/index', [BoxTypeController::class, 'index'])->name('maps.box_type.index');
                Route::post('box_type/store', [BoxTypeController::class, 'store'])->name('maps.box_type.store');
                Route::post('box_type/update', [BoxTypeController::class, 'update'])->name('maps.box_type.update');
                /*
                |----------------------------------------------------------------------------
                |  BOX INPUTS
                |----------------------------------------------------------------------------
                */
                Route::post('box_inputs/index', [BoxInputController::class, 'index'])->name('maps.box_input.index');
                Route::post('box_inputs/list', [BoxInputController::class, 'list'])->name('maps.box_input.list');
                Route::post('box_inputs/store', [BoxInputController::class, 'store'])->name('maps.box_input.store');
                Route::post('box_inputs/update', [BoxInputController::class, 'update'])->name('maps.box_input.update');
                Route::post('box_inputs/search', [BoxInputController::class, 'search'])->name('maps.box_input.search');
                Route::post('box_inputs/list_for_fusion', [BoxInputController::class, 'listForFusion'])->name('maps.box_input.list_for_fusion');
                Route::post('box_inputs/list_for_splitter_in', [BoxInputController::class, 'listForSplitterIn'])->name('maps.box_input.list_for_splitter_in');
                /*
                |----------------------------------------------------------------------------
                |  TRENCHES
                |----------------------------------------------------------------------------
                */
                Route::post('trench/index', [TrenchController::class, 'index'])->name('maps.trench.index');
                Route::post('trench/store', [TrenchController::class, 'store'])->name('maps.trench.store');
                Route::post('trench/update', [TrenchController::class, 'update'])->name('maps.trench.update');
                Route::post('trench/destroy', [TrenchController::class, 'destroy'])->name('maps.trench.destroy');
                /*
                |----------------------------------------------------------------------------
                |  TRENCHES
                |----------------------------------------------------------------------------
                */
                Route::post('trench_type/index', [TrenchTypeController::class, 'index'])->name('maps.trench_type.index');
                Route::post('trench_type/store', [TrenchTypeController::class, 'store'])->name('maps.trench_type.store');
                Route::post('trench_type/update', [TrenchTypeController::class, 'update'])->name('maps.trench_type.update');
                /*
                |----------------------------------------------------------------------------
                |  TUBE_TYPES
                |----------------------------------------------------------------------------
                */
                Route::post('tube_type/index', [TubeTypeController::class, 'index'])->name('maps.tube_type.index');
                Route::post('tube_type/store', [TubeTypeController::class, 'store'])->name('maps.tube_type.store');
                Route::post('tube_type/update', [TubeTypeController::class, 'update'])->name('maps.tube_type.update');
                /*
                |----------------------------------------------------------------------------
                |  TUBES
                |----------------------------------------------------------------------------
                */
                Route::post('tube/index', [TubeController::class, 'index'])->name('maps.tube.index');
                Route::post('tube/store', [TubeController::class, 'store'])->name('maps.tube.store');
                /*
                |----------------------------------------------------------------------------
                |  POINT ACCESSORIES
                |----------------------------------------------------------------------------
                */
                Route::post('point_accessory/index', [PointAccessoryController::class, 'index'])->name('maps.point_accessory.index');
                Route::post('point_accessory/store', [PointAccessoryController::class, 'store'])->name('maps.point_accessory.store');
                Route::post('point_accessory/update', [PointAccessoryController::class, 'update'])->name('maps.point_accessory.update');
                Route::post('point_accessory/destroy', [PointAccessoryController::class, 'destroy'])->name('maps.point_accessory.destroy');
                /*
                |----------------------------------------------------------------------------
                |  MAP_LINKS
                |----------------------------------------------------------------------------
                */
                Route::post('/map_link/index', [MaplinkController::class, 'index'])->name('maps.map_link.index');
                Route::post('/map_link/store', [MaplinkController::class, 'store'])->name('maps.map_link.store');
                Route::post('/map_link/show', [MaplinkController::class, 'show'])->name('maps.map_link.show');


                Route::post('/maplinks/list', [MapasController::class, 'getListMapLinks'])->name('maps.getListMapLinks');
                Route::post('/maplinks/route/store', [MaplinkController::class, 'storeMapLinkRoute'])->name('maps.map_link.route.store');
                Route::post('/maplinks/destroy', [MaplinkController::class, 'destroy'])->name('maps.map_link.destroy');
                /*
                |----------------------------------------------------------------------------
                |  MAP
                |----------------------------------------------------------------------------
                */
                Route::post('/map_three/list', [MapasController::class, 'getListMapthree'])->name('maps.three.list');
                Route::post('/set_session_position', [MapasController::class, 'setSessionPosition'])->name('maps.session_position.set');
                Route::post('/fiber_cut', [MapasController::class, 'fiberCutStore'])->name('maps.fiber_cut.store');
                Route::post('/fiber_cut/update', [MapasController::class, 'fiberCutUpdate'])->name('maps.fiber_cut.update');
                Route::post('/fiber_cut/destroy', [MapasController::class, 'fiberCutDestroy'])->name('maps.cut_fiber.destroy');
                /*
                |----------------------------------------------------------------------------
                |  CONNECTIONS
                |----------------------------------------------------------------------------
                */
                Route::post('fusions/update/', [EquipmentLinkController::class, 'fusionUpdate'])->name('maps.fusion.update');
                Route::post('fiber_connection/update/', [EquipmentLinkController::class, 'fiberConnectionUpdate'])->name('maps.fiber_connection.update');
                Route::post('splitter_in/update/', [EquipmentLinkController::class, 'splitterInConnectionUpdate'])->name('maps.splitter_in_connection.update');
                Route::post('splitter_out/update/', [EquipmentLinkController::class, 'splitterOutConnectionUpdate'])->name('maps.splitter_out_connection.update');
                /*
                |----------------------------------------------------------------------------
                |  MAP_ROUTE
                |----------------------------------------------------------------------------
                */
                Route::post('map_route/index', [MapRouteController::class, 'index'])->name('maps.map_route.index');
                Route::post('map_route/create', [MapRouteController::class, 'create'])->name('maps.map_route.create');
                Route::post('map_route/store', [MapRouteController::class, 'store'])->name('maps.map_route.store');
                Route::post('map_route/show', [MapRouteController::class, 'show'])->name('maps.map_route.show');
                Route::post('map_route/update', [MapRouteController::class, 'update'])->name('maps.map_route.update');
                /*
                |----------------------------------------------------------------------------
                |  RACK
                |----------------------------------------------------------------------------
                */
                Route::post('rack/index', [RackController::class, 'index'])->name('maps.rack.index');
                Route::post('rack/store', [RackController::class, 'store'])->name('maps.rack.store');
                Route::post('rack/update/', [RackController::class, 'update'])->name('maps.rack.update');
                Route::post('rack/destroy', [RackController::class, 'destroy'])->name('maps.rack.destroy');
                Route::post('rack/list/', [RackController::class, 'getListToSelect'])->name('maps.rack.list');
                /*
                |----------------------------------------------------------------------------
                |  ACTIVE_EQUIPMENT
                |----------------------------------------------------------------------------
                */
                Route::post('active_equipment/index', [ActiveEquipmentController::class, 'index'])->name('maps.active_equipment.index');
                Route::post('active_equipment/store', [ActiveEquipmentController::class, 'store'])->name('maps.active_equipment.store');
                Route::post('active_equipment/update', [ActiveEquipmentController::class, 'update'])->name('maps.active_equipment.update');
                Route::post('active_equipment/destroy', [ActiveEquipmentController::class, 'destroy'])->name('maps.active_equipment.destroy');
                /*
                |----------------------------------------------------------------------------
                |  ACTIVE_EQUIPMENT_TYPE
                |----------------------------------------------------------------------------
                */
                Route::post('active_equipment_types/index', [ActiveEquipmentTypeController::class, 'index'])->name('maps.active_equipment_type.index');
                Route::post('active_equipment_types/store', [ActiveEquipmentTypeController::class, 'store'])->name('maps.active_equipment_type.store');
                Route::post('active_equipment_types/update', [ActiveEquipmentTypeController::class, 'update'])->name('maps.active_equipment_type.update');
                /*
                /*
                |----------------------------------------------------------------------------
                |  Buffer
                |----------------------------------------------------------------------------
                */
                Route::post('buffer/list', [BufferController::class, 'list'])->name('maps.buffer.list');
                Route::post('buffer/list_by_input_box', [BufferController::class, 'listByInputBox'])->name('maps.buffer.list_by_input_box');
                /*
                |----------------------------------------------------------------------------
                |  Fiber
                |----------------------------------------------------------------------------
                */
                Route::post('fiber/list', [FiberController::class, 'list'])->name('maps.fiber.list');
                Route::post('fiber/list_by_input_box', [FiberController::class, 'listByInputBox'])->name('maps.fiber.list_by_input_box');
                /*
                |----------------------------------------------------------------------------
                |  PASSIVE_EQUIPMENT
                |----------------------------------------------------------------------------
                */
                Route::post('passive_equipment/index', [PassiveEquipmentController::class, 'index'])->name('maps.passive_equipment.index');
                Route::post('passive_equipment/store', [PassiveEquipmentController::class, 'store'])->name('maps.passive_equipment.store');
                Route::post('passive_equipment/update', [PassiveEquipmentController::class, 'update'])->name('maps.passive_equipment.update');
                Route::post('passive_equipment/destroy', [PassiveEquipmentController::class, 'destroy'])->name('maps.passive_equipment.destroy');
                /*
                |----------------------------------------------------------------------------
                |  PASSIVE_EQUIPMENT_TYPE
                |----------------------------------------------------------------------------
                */
                Route::post('passive_equipment_types/index', [PassiveEquipmentTypeController::class, 'index'])->name('maps.passive_equipment_type.index');
                Route::post('passive_equipment_types/store', [PassiveEquipmentTypeController::class, 'store'])->name('maps.passive_equipment_type.store');
                Route::post('passive_equipment_types/update', [PassiveEquipmentTypeController::class, 'update'])->name('maps.passive_equipment_type.update');
                /*
                |----------------------------------------------------------------------------
                |  CARD
                |----------------------------------------------------------------------------
                */
                Route::post('card/index', [CardController::class, 'index'])->name('maps.card.index');
                Route::post('card/store', [CardController::class, 'store'])->name('maps.card.store');
                Route::post('card/update', [CardController::class, 'update'])->name('maps.card.update');
                Route::post('card/list', [CardController::class, 'list'])->name('maps.card.list');
                Route::post('card/destroy', [CardController::class, 'destroy'])->name('maps.card.destroy');
                /*
                |----------------------------------------------------------------------------
                |  PORT
                |----------------------------------------------------------------------------
                */
                Route::post('port/index', [PortController::class, 'index'])->name('maps.port.index'); //lista completa de puertos
                Route::post('port/store', [PortController::class, 'store'])->name('maps.port.store'); //guardar un puerto
                Route::post('port/search', [PortController::class, 'search'])->name('maps.port.search'); //buscar por datos varios puertos
                Route::post('port/update/', [PortController::class, 'update'])->name('maps.port.update');
                Route::post('port/passive_equipment/index', [PortController::class, 'passiveEquipmentIndex'])->name('maps.port.passive_equipment.index');
                Route::post('port/passive_equipment/show', [PortController::class, 'passiveEquipmentShow'])->name('maps.port.passive_equipment.show');
                Route::post('port/list/object', [PortController::class, 'listByObject'])->name('maps.port.list.object.index');
                Route::post('port/destroy', [PortController::class, 'destroy'])->name('maps.port.destroy');
                /*
                |----------------------------------------------------------------------------
                |  EQUIPMENT_LINKS
                |----------------------------------------------------------------------------
                */
                Route::post('equipment_links/index', [EquipmentLinkController::class, 'index'])->name('maps.equipment_link.index');
                Route::post('equipment_links/store', [EquipmentLinkController::class, 'store'])->name('maps.equipment_link.store');
                /*
                |----------------------------------------------------------------------------
                |  SITE
                |----------------------------------------------------------------------------
                */
                Route::post('site/update/', [SiteController::class, 'update'])->name('maps.site.update');
                Route::post('site/list/', [SiteController::class, 'getListToSelect'])->name('maps.site.list');
                Route::post('site/destroy', [SiteController::class, 'destroy'])->name('maps.site.destroy');
                /*
                |----------------------------------------------------------------------------
                |  POLE
                |----------------------------------------------------------------------------
                */
                Route::post('pole/update/', [PoleController::class, 'update'])->name('maps.pole.update');
                Route::post('pole/destroy', [PoleController::class, 'destroy'])->name('maps.pole.destroy');
                /*
                |----------------------------------------------------------------------------
                |  POLE ACCESSORY
                |----------------------------------------------------------------------------
                */
                Route::post('pole_accessory/index/', [PoleAccessoryController::class, 'index'])->name('maps.pole_accessory.index');
                Route::post('pole_accessory/store/', [PoleAccessoryController::class, 'store'])->name('maps.pole_accessory.store');
                Route::post('pole_accessory/update/', [PoleAccessoryController::class, 'update'])->name('maps.pole_accessory.update');
                Route::post('pole_accessory/destroy', [PoleAccessoryController::class, 'destroy'])->name('maps.pole_accessory.destroy');
                /*
                |----------------------------------------------------------------------------
                |  MAP PROYECT
                |----------------------------------------------------------------------------
                */
                Route::post('map_proyect/store/', [MapProyectController::class, 'store'])->name('maps.map_proyect.store');
                Route::post('map_proyect/list/', [MapProyectController::class, 'getListToSelect'])->name('maps.map_proyect.list');
                Route::post('map_proyect/delete/', [MapProyectController::class, 'destroy'])->name('maps.map_proyect.delete');
                /*
                |----------------------------------------------------------------------------
                |  TRANSCEIVER
                |----------------------------------------------------------------------------
                */
                Route::post('transceiver/index', [TransceiverController::class, 'index'])->name('maps.transceiver.index');
                Route::post('transceiver/store', [TransceiverController::class, 'store'])->name('maps.transceiver.store');
                Route::post('transceiver/update', [TransceiverController::class, 'update'])->name('maps.transceiver.update');
                /*
                |----------------------------------------------------------------------------
                |  SPLITTERS
                |----------------------------------------------------------------------------
                */
                Route::post('splitter/index', [SplitterController::class, 'index'])->name('maps.splitter.index');
                Route::post('splitter/store', [SplitterController::class, 'store'])->name('maps.splitter.store');
                Route::post('splitter/update', [SplitterController::class, 'update'])->name('maps.splitter.update');
                Route::post('splitter/destroy', [SplitterController::class, 'destroy'])->name('maps.splitter.destroy');
                Route::post('splitter/list', [SplitterController::class, 'list'])->name('maps.splitter.list');
                /*
                |----------------------------------------------------------------------------
                |  TRAY
                |----------------------------------------------------------------------------
                */
                Route::post('tray/index', [TrayController::class, 'index'])->name('maps.tray.index');
                Route::post('tray/list', [TrayController::class, 'list'])->name('maps.tray.list');
                Route::post('tray/store', [TrayController::class, 'store'])->name('maps.tray.store');
                Route::post('tray/update', [TrayController::class, 'update'])->name('maps.tray.update');
                /*
                |----------------------------------------------------------------------------
                |  TABLE
                |----------------------------------------------------------------------------
                */
                Route::post('table', [TableController::class, 'getByName'])->name('maps.table');
            });

            Route::group(['prefix' => 'red'], function () {

                Route::group(['prefix' => 'ipv4', 'namespace' => 'Network'], function () {
                    Route::post('/add', 'NetworkController@store');
                    Route::get('/listar', 'NetworkController@index');
                    Route::get('/success', 'NetworkController@success');
                    Route::get('/crear', 'NetworkController@create');
                    Route::post('/table', 'NetworkController@table');
                    Route::post('/update/{id}', 'NetworkController@update');
                    Route::post('/destroy/{id}', 'NetworkController@destroy');
                    Route::post('/network/{id}', 'NetworkController@getIpByNetwork');

                    Route::get('/ver/{id}', 'NetworkIpController@show');
                    Route::post('/ip/table', 'NetworkIpController@table');
                    Route::post('/ip/update/{id}', 'NetworkIpController@update');

                    Route::post('/calculator', 'Ipv4CalculatorController@calculator');
                });

                Route::group(['prefix' => 'router', 'namespace' => 'Router'], function () {
                    Route::get('/listar', 'RouterController@index');
                    Route::get('/success/{id}', 'RouterController@success');
                    Route::get('/crear', 'RouterController@create');
                    Route::post('/add', 'RouterController@store');
                    Route::get('/editar/{id}', 'RouterController@edit');
                    Route::post('/update/{id}', 'RouterController@update');
                    Route::post('/destroy/{id}', 'RouterController@destroy');
                    Route::post('/table', 'RouterController@table');


                    Route::group(['prefix' => 'mikrotik'], function () {
                        Route::get('/crear', 'MikrotikController@create');
                        Route::post('/add', 'MikrotikController@store');
                        Route::get('/editar/{id}', 'MikrotikController@edit');
                        Route::post('/update/{id}', 'MikrotikController@update');
                        Route::post('/crear/{id}', 'MikrotikController@store');
                        Route::post('/destroy/{id}', 'MikrotikController@destroy');
                        Route::post('/table', 'MikrotikController@table');
                        Route::get('/cleantails', 'MikrotikController@clearMikrotikTails');
                        Route::get('/read-notification/{id}', 'RouterController@readNotification');

                        Route::group(['prefix' => 'config'], function () {
                            Route::get('/editar/{id}', 'MikrotikConfigController@edit');
                            Route::post('/update/{id}', 'MikrotikConfigController@update');
                            Route::post('/crear/{id}', 'MikrotikConfigController@store');
                            Route::post('/destroy/{id}', 'MikrotikConfigController@destroy');
                        });
                    });
                });
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

            Route::group(['prefix' => 'releases', 'namespace' => 'Release'], function () {
                Route::get('/', 'ReleaseController@index');
                Route::get('/{version}', 'ReleaseController@show');
                Route::post('/store', 'ReleaseController@store');
                Route::post('/update/{id}', 'ReleaseController@update');

                Route::get('/{releaseId}/descriptions', 'ReleaseDescriptionController@index');
                Route::post('/description/store', 'ReleaseDescriptionController@store');
                Route::post('/description/update/{id}', 'ReleaseDescriptionController@update');
                Route::delete('/description/delete/{id}', 'ReleaseDescriptionController@destroy');
            });
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

            Route::group(['namespace' => 'Router'], function () {
                Route::post('/status-by-router/{id}', 'MikrotikController@getMikrotikStatus');
                Route::post('/remove-rules-by-router/{id}', 'MikrotikController@getMikrotikRemoveRules');
                Route::post('/create-rules-by-router/{id}', 'MikrotikController@getMikrotikCreateRules');
                Route::post('/request-clone-client-to-mikrotik/{id}', 'MikrotikController@cloneClientToMikrotik');
            });
        });
    });

    Route::post('/cliente/get-receipt-for-client', 'Utils\ReceiptController@getReceiptForClient');
    Route::post('/get-payment-period', 'Utils\UtilController@getPaymentPeriod');

    Route::get('/setting-table/get/{table_id}', 'Module\Setting\Table\SettingTableController@get');
    Route::post('/setting-table/post/{table_id}', 'Module\Setting\Table\SettingTableController@store');

    Route::get('/', 'HomeController@index');
    Route::get('/permissions-auth', 'Module\Administration\Permission\PermissionController@userPermissions');
    Route::post('/get-home-statistics-for-tarjets-by-status-c', 'HomeController@getHomeStatisticsForTarjetsByStatus');
    Route::post('/get-home-statistics-for-text-card-in-dashboard-c', 'HomeController@getStatisticsForTextCardInDashBoard');
    Route::post('/get-stats-client-card-in-dashboard-c', 'HomeController@getStatsCardClientInDashBoard');
    Route::post('/get-stats-ticket-card-in-dashboard-c', 'HomeController@getStatsCardTicketsInDashBoard');
    Route::post('/get-stats-finance-card-in-dashboard-c', 'HomeController@getStatsCardFinanceInDashBoard');
    Route::post('/get-stats-server-card-in-dashboard-c', 'HomeController@getStatsCardServerInDashBoard');

    Route::get('/index', 'HomeController@index');

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

    Route::post('/save-app-config-layout', 'ConfigAppLayoutController@saveAppConfigLayout');
    Route::post('/getDataTable', 'Controller@getDataToTable');

    Route::post('/get-config-tabs', 'ConfigAppLayoutController@getConfigTabs');
    Route::post('/set-config-tabs', 'ConfigAppLayoutController@setConfigTabs');
    Route::get('/read-all-notifications', 'Utils\NotificationController@readAll');
    Route::get('/read-notification/{id}', 'Utils\NotificationController@readNotification');

    Route::group(['prefix' => '/statics'], function () {
        Route::post('/sales-and-prospects/{id}', [StaticsController::class, 'salesAndProspects']);
        Route::post('/sales-and-prospects', [StaticsController::class, 'salesAndProspects']);
        Route::post('/sales-by-medium/{id}', [StaticsController::class, 'salesByMedium']);
        Route::post('/sales-by-medium', [StaticsController::class, 'salesByMedium']);
        Route::post('/compare-sales/{id}', [StaticsController::class, 'compareSales']);
        Route::post('/compare-sales', [StaticsController::class, 'compareSales']);
        Route::post('/prospects-by-status/{id}', [StaticsController::class, 'prospectsByStatus']);
        Route::post('/prospects-by-status', [StaticsController::class, 'prospectsByStatus']);
        Route::post('/ranking-sales', [StaticsController::class, 'rankingSales']);
        Route::post('/total-prospects', [StaticsController::class, 'getTotalProspects']);
        Route::post('/total-sales', [StaticsController::class, 'getTotalSales']);
        Route::post('/total-lost-sales', [StaticsController::class, 'getLostSales']);
    });
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::group(['prefix' => '/register-vendor'], function () {
    Route::get('', [RegisterVendorController::class, 'index'])->name('register-vendor.index');
    Route::post('/register', [RegisterVendorController::class, 'register'])->name('register-vendor.register');
});

Route::get('/notification-email', function () {
    $n = TaskNotification::find(1);
    return (new StandardNotification($n, ['email'], ['title' => 'usted tiene asignada una nueva tarea', 'url' => '/scheduling/task/editar/' . $n->task_id]))
        ->toMail($n->user);
});
