<?php

use App\Modules\Core\Clientes\Controllers\ClientBillingAddressController;
use App\Modules\Core\Clientes\Controllers\ClientBillingConfigurationController;
use App\Modules\Core\Clientes\Controllers\ClientBillingRemindersConfigurationController;
use App\Modules\Core\Clientes\Controllers\ClientBundleServiceController;
use App\Modules\Core\Clientes\Controllers\ClientController;
use App\Modules\Core\Clientes\Controllers\ClientCustomServiceController;
use App\Modules\Core\Clientes\Controllers\ClientInformationController;
use App\Modules\Core\Clientes\Controllers\ClientInternetServiceController;
use App\Modules\Core\Clientes\Controllers\ClientInvoiceController;
use App\Modules\Core\Clientes\Controllers\ClientPaymentController;
use App\Modules\Core\Clientes\Controllers\ClientPingController;
use App\Modules\Core\Clientes\Controllers\ClientServiceController;
use App\Modules\Core\Clientes\Controllers\ClientStatisticsController;
use App\Modules\Core\Clientes\Controllers\ClientTransactionController;
use App\Modules\Core\Clientes\Controllers\ClientVozServiceController;
use App\Modules\Core\Clientes\Controllers\DashboardController;
use App\Modules\Core\Clientes\Controllers\DocumentClientController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo Core/Clientes (core-clientes).
 *
 * Origen previo en routes/web.php (líneas 527-671):
 *   Route::group(['middleware' => ['auth']], function () {
 *     Route::group(['middleware' => ['check_route_permission']], function () {
 *       Route::group(['namespace' => 'Module'], function () {
 *         Route::group(['prefix' => 'cliente', 'namespace' => 'Client'], function () { ... });
 *       });
 *     });
 *   });
 *
 * Se preserva la combinación de middleware (`web` se agrega porque
 * loadRoutesFrom no aplica el grupo `web` automáticamente — mismo patrón
 * que CRM/IA/MegaFamilia).
 */

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('cliente')
    ->group(function () {

        // Dashboard
        Route::get('/', [DashboardController::class, 'index']);

        // Cliente principal
        Route::get('/success/{id}', [ClientController::class, 'success']);
        Route::post('/debit/{id}', [ClientController::class, 'getClientDebit']);
        Route::get('/listar', [ClientController::class, 'index'])->name('cliente');
        Route::get('/actives', [ClientController::class, 'getActiveClients']);
        Route::get('/get-client-filtered-by-bundle-service/{id}', [ClientController::class, 'getClientFilteredByBundleService']);
        Route::get('/get-client-filtered-by-internet-service/{id}', [ClientController::class, 'getClientFilteredByInternetService']);
        Route::get('/get-client-filtered-by-custom-service/{id}', [ClientController::class, 'getClientFilteredByCustomService']);
        Route::get('/get-client-filtered-by-voz-service/{id}', [ClientController::class, 'getClientFilteredByVozService']);
        Route::get('/crear', [ClientController::class, 'create']);
        Route::post('/add', [ClientController::class, 'store']);
        Route::get('/editar/{id}', [ClientController::class, 'edit']);
        Route::post('/update/{id}', [ClientInformationController::class, 'update']);
        Route::post('/edit_id', [ClientInformationController::class, 'editId']);
        Route::get('/payment_today', [ClientController::class, 'getClientToPaymentToDay']);
        Route::get('/suspend_today', [ClientController::class, 'getClientToSuspendToDay']);
        Route::post('/edit_court_date', [ClientController::class, 'editCourtDate']);
        Route::post('/force_delete', [ClientController::class, 'forceDelete']);
        Route::post('/edit_date_payment', [ClientController::class, 'editDatePayment']);
        Route::post('/edit_balance', [ClientController::class, 'editBalance']);
        Route::post('/payment_instalation_cost/services', [ClientController::class, 'paymentInstalationCostServices']);
        Route::post('/payment_activation_cost', [ClientController::class, 'paymentActivationCost']);

        Route::post('/{clientId}/get-client-main-information-id-and-client-additional-information-id', [ClientController::class, 'getClientMainInformationIdAndClientAdditionalInformationId']);
        Route::post('/get-is-promise-payment/{id}', [ClientController::class, 'getIsPromisePayment']);
        Route::post('/get-client-id-by-client-main-information-id/{id}', [ClientController::class, 'geClientIdByClientMainInformationId']);
        Route::post('/get-promotions/{id}', [ClientController::class, 'getPromotionsByClient']);
        Route::post('/get-period-by-amount/{id}', [ClientController::class, 'getPaymentPeriodByAmount']);

        // Documentos del cliente
        Route::prefix('document')->group(function () {
            Route::post('/add/{idClient}', [DocumentClientController::class, 'store']);
            Route::post('/update/{idClient}', [DocumentClientController::class, 'update']);
            Route::post('/upload-file/{id}', [DocumentClientController::class, 'uploadFile']);
            Route::post('/table', [DocumentClientController::class, 'table']);
            Route::post('/destroy/{id}', [DocumentClientController::class, 'destroy']);
            Route::post('/generate_contract/{id}', [DocumentClientController::class, 'generateContract']);
            Route::get('/load_content_template', [DocumentClientController::class, 'loadContentTemplate']);
            Route::post('/show_content_template', [DocumentClientController::class, 'showContentTemplate']);
        });

        // Información del cliente
        Route::post('/get-client-with-balance/{id}', [ClientInformationController::class, 'getClientWithBalance']);
        Route::post('/get-tickets-open/{id}', [ClientInformationController::class, 'getClientTicketsOpen']);
        Route::post('/get-client-status/{id}', [ClientInformationController::class, 'getClientStatus']);
        Route::post('/get-data-client-to-select-component/{id}', [ClientInformationController::class, 'getDataClientToSelectComponent']);

        Route::post('/destroy/{id}', [ClientController::class, 'destroy']);
        Route::post('/table', [ClientController::class, 'table']);

        Route::post('/has-service/{id}', [ClientServiceController::class, 'hasService']);
        Route::post('/can-add-service/{id}', [ClientServiceController::class, 'canAddService']);

        // Servicios bundle
        Route::prefix('clientbundleservice')->group(function () {
            Route::post('/bundle/{id}', [ClientBundleServiceController::class, 'getPlansById']);
            Route::post('/bundle/edit/{id}', [ClientBundleServiceController::class, 'getEditedServiceBundleById']);
            Route::post('/table', [ClientBundleServiceController::class, 'table']);
            Route::post('/update/{id}', [ClientBundleServiceController::class, 'update']);
            Route::post('/crear/{id}', [ClientBundleServiceController::class, 'store']);
            Route::post('/destroy/{id}', [ClientBundleServiceController::class, 'destroy']);
            Route::post('/bundle/change-bundle/{id}', [ClientBundleServiceController::class, 'changeBundle']);
            Route::post('/bundle/get-equals/{id}', [ClientBundleServiceController::class, 'getEqualsPlansById']);
            Route::post('/bundle/get-plans-to-change/{id}', [ClientBundleServiceController::class, 'getPlansToChangeById']);
        });

        // Servicios internet
        Route::prefix('clientinternetservice')->group(function () {
            Route::post('/table', [ClientInternetServiceController::class, 'table']);
            Route::post('/update/{id}', [ClientInternetServiceController::class, 'update']);
            Route::post('/crear/{id}', [ClientInternetServiceController::class, 'store']);
            Route::post('/destroy/{id}', [ClientInternetServiceController::class, 'destroy']);
            Route::post('/change-internet/{id}', [ClientInternetServiceController::class, 'changeInternet']);
            Route::post('/refresh-ip/{id}', [ClientInternetServiceController::class, 'refreshIp']);
        });

        // Servicios voz
        Route::prefix('clientvozservice')->group(function () {
            Route::post('/table', [ClientVozServiceController::class, 'table']);
            Route::post('/update/{id}', [ClientVozServiceController::class, 'update']);
            Route::post('/crear/{id}', [ClientVozServiceController::class, 'store']);
            Route::post('/destroy/{id}', [ClientVozServiceController::class, 'destroy']);
            Route::post('/change-voz/{id}', [ClientVozServiceController::class, 'changeVoz']);
        });

        // Servicios custom
        Route::prefix('clientcustomservice')->group(function () {
            Route::post('/table', [ClientCustomServiceController::class, 'table']);
            Route::post('/update/{id}', [ClientCustomServiceController::class, 'update']);
            Route::post('/crear/{id}', [ClientCustomServiceController::class, 'store']);
            Route::post('/destroy/{id}', [ClientCustomServiceController::class, 'destroy']);
            Route::post('/change-custom/{id}', [ClientCustomServiceController::class, 'changeCustom']);
        });

        // Alias legacy — preserva la ruta que existía duplicada en routes/web.php:614
        Route::post('/clientbundleservice/table', [ClientBundleServiceController::class, 'table']);

        // Billing
        Route::prefix('billing')->group(function () {
            Route::post('/update-billing-configuration/{id}', [ClientBillingConfigurationController::class, 'update']);
            Route::post('/client-debit-rectification-agreement/{id}', [ClientBillingConfigurationController::class, 'getClientDebitRectificationAgreement']);

            Route::post('/update-billing-address/{id}', [ClientBillingAddressController::class, 'update']);
            Route::post('/update-reminders-configuration/{id}', [ClientBillingRemindersConfigurationController::class, 'update']);
            Route::post('/get-reminder-payment-amount/{id}', [ClientBillingRemindersConfigurationController::class, 'getReminderPaymentAmount']);
            Route::post('/get-billing-information-block/{id}', [ClientBillingConfigurationController::class, 'getBillingInformationBlock']);
            Route::post('/get-payment-method/{id}', [ClientBillingConfigurationController::class, 'getPaymentMethod']);
            Route::post('/get-type-of-billing-by-client-id/{id}', [ClientBillingConfigurationController::class, 'getTypeOfBillingByClientId']);

            // Pagos
            Route::prefix('payment')->group(function () {
                Route::post('/crear/{id}', [ClientPaymentController::class, 'store']);
                Route::post('/update/{id}', [ClientPaymentController::class, 'update']);
                Route::post('/destroy/{id}', [ClientPaymentController::class, 'destroy']);
                Route::post('/table', [ClientPaymentController::class, 'table']);
                Route::get('/pdf/{id}', [ClientPaymentController::class, 'getPrintPdf']);
                Route::post('/get-totals/{id}', [ClientPaymentController::class, 'getTotals']);
                Route::post('/get-cost-all-service-active/{id}', [ClientPaymentController::class, 'getCostAllServiceActive']);
                Route::post('/get-active-service-expiration/{id}', [ClientPaymentController::class, 'getActiveServiceExpiration']);
                Route::post('/get-cost-all-service/{id}', [ClientPaymentController::class, 'getCostAllService']);
            });

            // Transacciones
            Route::prefix('transaction')->group(function () {
                Route::post('/crear/{id}', [ClientTransactionController::class, 'store']);
                Route::post('/update/{id}', [ClientTransactionController::class, 'update']);
                Route::post('/get-totals/{id}', [ClientTransactionController::class, 'getTotals']);
                Route::post('/destroy/{id}', [ClientTransactionController::class, 'destroy']);
                Route::post('/table', [ClientTransactionController::class, 'table']);
            });

            // Facturas
            Route::prefix('invoice')->group(function () {
                Route::post('/crear/{id}', [ClientInvoiceController::class, 'store']);
                Route::post('/update/{id}', [ClientInvoiceController::class, 'update']);
                Route::post('/get-totals/{id}', [ClientInvoiceController::class, 'getTotals']);
                Route::post('/destroy/{id}', [ClientInvoiceController::class, 'destroy']);
                Route::post('/table', [ClientInvoiceController::class, 'table']);
                Route::get('/pdf/{id}', [ClientInvoiceController::class, 'getPrintPdf']);
                Route::get('/create-new-client-invoice/{id}', [ClientInvoiceController::class, 'createManualClientInvoice']);
            });
        });

        // Estadísticas + ping monitoring
        Route::prefix('statistics')->group(function () {
            Route::get('/get-active-connections/{id}', [ClientStatisticsController::class, 'getActiveConnections']);
            Route::get('/get-consumption-summary/{id}', [ClientStatisticsController::class, 'getConsumptionSummary']);
            Route::get('/get-daily-usage/{id}', [ClientStatisticsController::class, 'getDailyUsage']);
            Route::get('/get-fup-stats/{id}', [ClientStatisticsController::class, 'getFupStatistics']);
            Route::get('/get-history/{id}', [ClientStatisticsController::class, 'getConnectionHistory']);
            // Ping monitoring (clientes dedicados)
            Route::get('/get-ping-status/{id}',  [ClientPingController::class, 'getPingStatus']);
            Route::get('/get-ping-history/{id}', [ClientPingController::class, 'getPingHistory']);
            Route::get('/get-ping-daily/{id}',   [ClientPingController::class, 'getPingDailySummary']);
        });
    });

// Helper utility consumido por formularios de varios módulos (búsqueda de
// servicios contratados de un cliente). Ruta global sin prefix por compat.
Route::middleware(['web', 'auth', 'check_route_permission'])->group(function () {
    Route::post('/helper/get-services-by-client-main-information', [\App\Modules\Core\Clientes\Controllers\Helpers\ComponentSearchServiceController::class, 'getServiceByClientMainInformationId']);
});
