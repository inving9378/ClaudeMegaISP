<?php

use App\Modules\Core\Configuracion\Controllers\BillingReminder\BillingReminderController;
use App\Modules\Core\Configuracion\Controllers\CommandConfigController;
use App\Modules\Core\Configuracion\Controllers\Commission\ComissionController;
use App\Modules\Core\Configuracion\Controllers\CompanyInformation\CompanyInformationController;
use App\Modules\Core\Configuracion\Controllers\Credential\CredentialUpdateController;
use App\Modules\Core\Configuracion\Controllers\EmailSetting\EmailSettingController;
use App\Modules\Core\Configuracion\Controllers\Finance\ConfigFinanceNotificationController;
use App\Modules\Core\Configuracion\Controllers\ListTemplateVerification\ListTemplateVerificationController;
use App\Modules\Core\Configuracion\Controllers\MediumSale\MediumSaleController;
use App\Modules\Core\Configuracion\Controllers\MethodPayment\MethodPaymentController;
use App\Modules\Core\Configuracion\Controllers\Nomenclature\NomenclatureController;
use App\Modules\Core\Configuracion\Controllers\Rules\RuleController;
use App\Modules\Core\Configuracion\Controllers\ServiceInAddressList\ServiceInAddressListController;
use App\Modules\Core\Configuracion\Controllers\SettingAdditionalFieldController;
use App\Modules\Core\Configuracion\Controllers\SettingApiMovilController;
use App\Modules\Core\Configuracion\Controllers\SettingController;
use App\Modules\Core\Configuracion\Controllers\SettingDebitPaymentCustomController;
use App\Modules\Core\Configuracion\Controllers\StatusSeller\StatusSellerController;
use App\Modules\Core\Configuracion\Controllers\Team\TeamController;
use App\Modules\Core\Configuracion\Controllers\TemplateTask\TemplateTaskController;
use App\Modules\Core\Configuracion\Controllers\Tools\ImportController;
use App\Modules\Core\Configuracion\Controllers\TypeSeller\TypeSellerController;
use App\Modules\Core\Configuracion\Controllers\WorkFlow\WorkFlowController;
// Controllers que NO pertenecen a este módulo pero se enrutan bajo /configuracion:
use App\Modules\Addons\Mapas\Controllers\Mapas\MapCredentialController;
use App\Modules\Addons\Vendedores\Controllers\Vendors\Billing\CommissionRuleController;
use App\Modules\Addons\Vendedores\Controllers\Vendors\Billing\RangeSaleController;
// Catálogos folded into Configuracion (decision 2026-05-20). Rutas siguen
// expuestas bajo `administracion/*` para compatibilidad con frontend.
use App\Modules\Core\Configuracion\Controllers\Ift\IftController;
use App\Modules\Core\Configuracion\Controllers\MethodOfPayment\MethodOfPaymentController;
use App\Modules\Core\Configuracion\Controllers\Partner\PartnerController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo Core/Configuracion (core-configuracion).
 *
 * Origen previo en routes/web.php:
 *   Route::group(['middleware' => ['auth']], function () {
 *     Route::group(['middleware' => ['check_route_permission']], function () {
 *       Route::group(['namespace' => 'Module'], function () {
 *         Route::group(['prefix' => 'configuracion', 'namespace' => 'Setting'], function () { ... });
 *       });
 *     });
 *   });
 *
 * Se preserva la combinación de middleware (`web` agregado porque
 * loadRoutesFrom no aplica el grupo `web` automáticamente —
 * memory/feedback_module_routes_web_middleware.md).
 *
 * `MapCredentialController`, `CommissionRuleController` y `RangeSaleController`
 * pertenecen a otros módulos (Mapas, Vendors) y siguen viviendo allí — sólo
 * se montan bajo este prefijo por motivos históricos de UI.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('configuracion')
    ->group(function () {
        Route::get('/', [SettingController::class, 'index']);
        Route::post('/debt-payment-client-recurrent', [SettingController::class, 'debtPaymentClientRecurrent']);
        Route::post('/debt-payment-client-custom', [SettingController::class, 'debtPaymentClientCustom']);

        Route::group(['prefix' => 'debitcustom'], function () {
            Route::get('/', [SettingDebitPaymentCustomController::class, 'index']);
            Route::post('/add', [SettingDebitPaymentCustomController::class, 'store']);
            Route::get('/editar/{id}', [SettingDebitPaymentCustomController::class, 'edit']);
            Route::post('/update/{id}', [SettingDebitPaymentCustomController::class, 'update']);
            Route::post('/destroy/{id}', [SettingDebitPaymentCustomController::class, 'destroy']);
            Route::post('/table', [SettingDebitPaymentCustomController::class, 'table']);
        });

        Route::group(['prefix' => 'company-information'], function () {
            Route::get('/', [CompanyInformationController::class, 'index']);
            Route::post('/add', [CompanyInformationController::class, 'store']);
            Route::get('/editar/{id}', [CompanyInformationController::class, 'edit']);
            Route::post('/get-data-company', [CompanyInformationController::class, 'getDataCompany']);
            Route::post('/update/{id}', [CompanyInformationController::class, 'update']);
            Route::post('/destroy/{id}', [CompanyInformationController::class, 'destroy']);
        });

        Route::group(['prefix' => 'billing-reminder'], function () {
            Route::get('/editar/{id}', [BillingReminderController::class, 'edit']);
            Route::post('/update/{id}', [BillingReminderController::class, 'update']);
        });

        Route::group(['prefix' => 'email-setting'], function () {
            Route::get('/editar/{id}', [EmailSettingController::class, 'edit']);
            Route::post('/update/{id}', [EmailSettingController::class, 'update']);
        });

        Route::group(['prefix' => 'command'], function () {
            Route::get('/', [CommandConfigController::class, 'index']);
            Route::post('/update/{id}', [CommandConfigController::class, 'update']);
        });

        // Additional fields
        Route::group(['prefix' => 'additional-fields'], function () {
            Route::get('/', [SettingAdditionalFieldController::class, 'index']);
            Route::post('/add', [SettingAdditionalFieldController::class, 'store']);
            Route::get('/editar/{id}', [SettingAdditionalFieldController::class, 'edit']);
            Route::post('/update/{id}', [SettingAdditionalFieldController::class, 'update']);
            Route::post('/destroy/{id}', [SettingAdditionalFieldController::class, 'destroy']);
            Route::post('/table', [SettingAdditionalFieldController::class, 'table']);
            Route::post('/get-required-value/{id}', [SettingAdditionalFieldController::class, 'getRequiredValue']);
            Route::post('/update-position-field', [SettingAdditionalFieldController::class, 'updatePositionField']);
        });

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

        Route::group(['prefix' => 'credencial'], function () {
            Route::get('/modificar-credencial', [CredentialUpdateController::class, 'changeImageCredential']);
            Route::get('/image-front', [CredentialUpdateController::class, 'getFrontalImagePath']);
            Route::get('/image-back', [CredentialUpdateController::class, 'getBackImagePath']);
            Route::get('/image-logo', [CredentialUpdateController::class, 'getLogoImagePath']);
            Route::post('/upload', [CredentialUpdateController::class, 'upload']);
        });

        Route::group(['prefix' => 'medios-de-venta'], function () {
            Route::get('/', [MediumSaleController::class, 'index']);
            Route::get('/get-mediums-sales', [MediumSaleController::class, 'getAll']);
            Route::get('/{id}/get-by-id', [MediumSaleController::class, 'getById']);
            Route::post('/create', [MediumSaleController::class, 'store']);
            Route::post('/{id}/update', [MediumSaleController::class, 'update']);
            Route::delete('/{id}/destroy', [MediumSaleController::class, 'destroy']);
        });

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

        Route::group(['prefix' => 'tipos-vendedores'], function () {
            Route::get('/', [TypeSellerController::class, 'index']);
            Route::get('/get-all-types', [TypeSellerController::class, 'getAll']);
            Route::get('/{id}/edit', [TypeSellerController::class, 'edit']);
            Route::post('/create', [TypeSellerController::class, 'store']);
            Route::post('/{id}/update', [TypeSellerController::class, 'update']);
            Route::delete('/{id}/destroy', [TypeSellerController::class, 'destroy']);
        });

        Route::group(['prefix' => 'estados-vendedores'], function () {
            Route::get('/', [StatusSellerController::class, 'index']);
            Route::get('/get-all-status', [StatusSellerController::class, 'getAll']);
            Route::get('/{id}/edit', [StatusSellerController::class, 'edit']);
            Route::post('/create', [StatusSellerController::class, 'store']);
            Route::post('/{id}/update', [StatusSellerController::class, 'update']);
            Route::delete('/{id}/destroy', [StatusSellerController::class, 'destroy']);
        });

        Route::group(['prefix' => 'metodos-de-pago'], function () {
            Route::get('/', [MethodPaymentController::class, 'index']);
            Route::get('/get-all-methods', [MethodPaymentController::class, 'getAll']);
            Route::get('/{id}/edit', [MethodPaymentController::class, 'edit']);
            Route::post('/create', [MethodPaymentController::class, 'store']);
            Route::post('/{id}/update', [MethodPaymentController::class, 'update']);
            Route::delete('/{id}/destroy', [MethodPaymentController::class, 'destroy']);
        });

        Route::group(['prefix' => 'credenciales-google-maps'], function () {
            Route::get('/', [MapCredentialController::class, 'index']);
            Route::get('/edit', [MapCredentialController::class, 'edit']);
            Route::post('/create', [MapCredentialController::class, 'store']);
            Route::post('/{id}/update', [MapCredentialController::class, 'update']);
            Route::delete('/{id}/destroy', [MapCredentialController::class, 'destroy']);
        });

        Route::group(['prefix' => 'rangos-venta'], function () {
            Route::get('/', [RangeSaleController::class, 'index']);
            Route::get('/get-all-ranges-sales', [RangeSaleController::class, 'getListRangesSales']);
            Route::get('/sector-one', [RangeSaleController::class, 'getSectorOne']);
            Route::get('/sector-two', [RangeSaleController::class, 'getSectorTwo']);
            Route::get('/sector-three', [RangeSaleController::class, 'getSectorThree']);
            Route::get('/{id}/edit', [RangeSaleController::class, 'edit']);
            Route::post('/{id}/update', [RangeSaleController::class, 'update']);
        });

        Route::group(['prefix' => 'work-flow'], function () {
            Route::get('/', [WorkFlowController::class, 'index']);
            Route::post('/add', [WorkFlowController::class, 'store']);
            Route::get('/editar/{id}', [WorkFlowController::class, 'edit']);
            Route::post('/update/{id}', [WorkFlowController::class, 'update']);
            Route::post('/destroy/{id}', [WorkFlowController::class, 'destroy']);
            Route::post('/table', [WorkFlowController::class, 'table']);
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

        Route::group(['prefix' => 'nomenclature'], function () {
            Route::get('/', [NomenclatureController::class, 'index']);
            Route::post('/add', [NomenclatureController::class, 'store']);
            Route::get('/editar/{id}', [NomenclatureController::class, 'edit']);
            Route::post('/update/{id}', [NomenclatureController::class, 'update']);
            Route::post('/destroy/{id}', [NomenclatureController::class, 'destroy']);
            Route::post('/assign_client/{id}', [NomenclatureController::class, 'assignClient']);
            Route::post('/get-nomenclature-by-client/{id}', [NomenclatureController::class, 'getNomenclatureByClient']);
            Route::post('/table', [NomenclatureController::class, 'table']);
            Route::post('/add-multiple-nomenclatures', [NomenclatureController::class, 'generarNomenclatura']);
            Route::post('/change-client', [NomenclatureController::class, 'changeClient']);
        });

        Route::group(['prefix' => 'team'], function () {
            Route::get('/', [TeamController::class, 'index']);
            Route::post('/add', [TeamController::class, 'store']);
            Route::get('/editar/{id}', [TeamController::class, 'edit']);
            Route::post('/update/{id}', [TeamController::class, 'update']);
            Route::post('/destroy/{id}', [TeamController::class, 'destroy']);
            Route::post('/table', [TeamController::class, 'table']);
        });

        Route::group(['prefix' => 'service_in_address_list'], function () {
            Route::get('/', [ServiceInAddressListController::class, 'index']);
            Route::post('/add', [ServiceInAddressListController::class, 'store']);
            Route::get('/editar/{id}', [ServiceInAddressListController::class, 'edit']);
            Route::post('/update/{id}', [ServiceInAddressListController::class, 'update']);
            Route::post('/destroy/{id}', [ServiceInAddressListController::class, 'destroy']);
            Route::post('/table', [ServiceInAddressListController::class, 'table']);
            Route::post('/mikrotik-remove-address-list/{id}', [ServiceInAddressListController::class, 'removeServiceToAddressList']);
        });

        Route::group(['prefix' => 'rules'], function () {
            Route::get('/', [RuleController::class, 'index']);
            Route::get('/get-sellers-by-type/{type}', [RuleController::class, 'getSellersByType']);
            Route::get('/create', [RuleController::class, 'create']);
            Route::post('/store', [RuleController::class, 'store']);
            Route::get('/edit/{id}', [RuleController::class, 'edit']);
            Route::post('/update/{id}', [RuleController::class, 'update']);
            Route::post('/destroy/{id}', [RuleController::class, 'destroy']);
            Route::post('/table', [RuleController::class, 'table']);
            Route::post('/general-config', [RuleController::class, 'generalConfig']);
            Route::post('/save-general-config', [RuleController::class, 'saveGeneralConfig']);
            Route::get('/get-all', [RuleController::class, 'getAll']);
        });

        Route::group(['prefix' => 'finance-notification'], function () {
            Route::get('/', [ConfigFinanceNotificationController::class, 'index']);
            Route::post('/get-data-tabs', [ConfigFinanceNotificationController::class, 'getDataTabs']);
            Route::post('/update/{id}', [ConfigFinanceNotificationController::class, 'update']);
        });

        // API Móvil — configuración, tokens Sanctum activos, docs auto-
        // generadas de /api/megafamilia/* y logs de acceso.
        Route::group(['prefix' => 'api-movil'], function () {
            Route::get('/', [SettingApiMovilController::class, 'index']);
            Route::get('/get', [SettingApiMovilController::class, 'getConfig']);
            Route::post('/update', [SettingApiMovilController::class, 'updateConfig']);

            Route::get('/tokens', [SettingApiMovilController::class, 'tokens']);
            Route::get('/tokens/data', [SettingApiMovilController::class, 'listTokens']);
            Route::post('/tokens/{id}/revoke', [SettingApiMovilController::class, 'revokeToken'])->whereNumber('id');
            Route::post('/tokens/revoke-all', [SettingApiMovilController::class, 'revokeAllTokens']);

            Route::get('/docs', [SettingApiMovilController::class, 'docs']);
            Route::get('/docs/endpoints', [SettingApiMovilController::class, 'endpoints']);

            Route::get('/logs', [SettingApiMovilController::class, 'logs']);
            Route::get('/logs/data', [SettingApiMovilController::class, 'logsData']);
            Route::get('/logs/csv', [SettingApiMovilController::class, 'logsCsv']);
        });
    });

// Catálogos legacy de Administration folded into Configuracion (2026-05-20)
// URL prefix `administracion/*` preservado para compat con frontend.
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('administracion')
    ->group(function () {

        Route::prefix('socios')->group(function () {
            Route::get('/', [PartnerController::class, 'index']);
            Route::post('/add', [PartnerController::class, 'store']);
            Route::get('/editar/{id}', [PartnerController::class, 'edit']);
            Route::post('/update/{id}', [PartnerController::class, 'update']);
            Route::post('/destroy/{id}', [PartnerController::class, 'destroy']);
            Route::post('/table', [PartnerController::class, 'table']);
        });

        Route::prefix('ift')->group(function () {
            Route::get('/', [IftController::class, 'index']);
            Route::post('/add', [IftController::class, 'store']);
            Route::get('/editar/{id}', [IftController::class, 'edit']);
            Route::post('/update/{id}', [IftController::class, 'update']);
            Route::post('/destroy/{id}', [IftController::class, 'destroy']);
            Route::post('/table', [IftController::class, 'table']);
        });

        Route::prefix('metotdo-de-pago')->group(function () {
            Route::get('/', [MethodOfPaymentController::class, 'index']);
            Route::post('/add', [MethodOfPaymentController::class, 'store']);
            Route::get('/editar/{id}', [MethodOfPaymentController::class, 'edit']);
            Route::post('/update/{id}', [MethodOfPaymentController::class, 'update']);
            Route::post('/destroy/{id}', [MethodOfPaymentController::class, 'destroy']);
            Route::post('/table', [MethodOfPaymentController::class, 'table']);
        });
    });
