<?php

use App\Modules\Addons\Talento\Controllers\TalentoColaboradorController;
use App\Modules\Addons\Talento\Controllers\TalentoCustodiaController;
use App\Modules\Addons\Talento\Controllers\TalentoDeviceController;
use App\Modules\Addons\Talento\Controllers\TalentoRoadmapController;
use App\Modules\Addons\Talento\Controllers\TalentoWorkOrderController;
use App\Modules\Addons\Talento\Controllers\TalentoCompensacionController;
use App\Modules\Addons\Talento\Controllers\TalentoLiquidacionController;
use App\Modules\Addons\Talento\Controllers\TalentoAttendanceController;
use App\Modules\Addons\Talento\Controllers\TalentoWorkSiteController;
use App\Modules\Addons\Talento\Controllers\TalentoFieldFlowController;
use App\Modules\Addons\Talento\Controllers\TalentoCajaController;
use App\Modules\Addons\Talento\Controllers\TalentoWarrantyController;
use App\Modules\Addons\Talento\Controllers\TalentoRouteController;
use App\Modules\Addons\Talento\Controllers\TalentoProjectController;
use App\Modules\Addons\Talento\Controllers\TalentoQualityController;
use App\Modules\Addons\Talento\Controllers\TalentoPenaltyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('talento')
    ->group(function () {

        // ── Vistas web (ANTES de rutas con parámetros) ──────────────────────
        Route::get('/',               [TalentoColaboradorController::class,  'index']);
        Route::get('/custodia',       [TalentoCustodiaController::class,     'index']);
        Route::get('/dispositivos',   [TalentoDeviceController::class,       'index']);
        Route::get('/roadmap',        [TalentoRoadmapController::class,      'index']);
        Route::get('/ordenes',        [TalentoWorkOrderController::class,    'index']);
        Route::get('/compensacion',   [TalentoCompensacionController::class, 'index']);
        Route::get('/liquidaciones',  [TalentoLiquidacionController::class,  'index']);
        Route::get('/asistencia',     [TalentoAttendanceController::class,   'index']);
        Route::get('/mapa-en-vivo',   fn() => view('addon-talento::talento.mapa_vivo'));
        Route::get('/sitios',         fn() => view('addon-talento::talento.sitios'));
        Route::get('/campo',          [TalentoFieldFlowController::class,   'index']);
        Route::get('/cajas',          [TalentoCajaController::class,        'index']);
        Route::get('/rutas',          [TalentoRouteController::class,       'index']);
        Route::get('/proyectos',      [TalentoProjectController::class,     'index']);
        Route::get('/calidad',        fn() => view('addon-talento::talento.calidad'));
        Route::get('/penalizaciones', [TalentoPenaltyController::class, 'index']);

        // ── API JSON ─────────────────────────────────────────────────────────
        Route::prefix('api')->group(function () {

            // ── Colaboradores ────────────────────────────────────────────────
            Route::get('/colaboradores',                   [TalentoColaboradorController::class, 'data']);
            Route::post('/colaboradores',                  [TalentoColaboradorController::class, 'store']);
            Route::get('/colaboradores/users-disponibles', [TalentoColaboradorController::class, 'usersDisponibles']);
            Route::get('/colaboradores/{id}',              [TalentoColaboradorController::class, 'show']);
            Route::put('/colaboradores/{id}',              [TalentoColaboradorController::class, 'update']);
            Route::delete('/colaboradores/{id}',           [TalentoColaboradorController::class, 'destroy']);

            // ── Custodia (solo lectura) ───────────────────────────────────────
            Route::get('/colaboradores/{id}/custodia',     [TalentoCustodiaController::class, 'show']);

            // ── Dispositivos ─────────────────────────────────────────────────
            Route::get('/colaboradores/{id}/dispositivos',                    [TalentoDeviceController::class, 'forColaborador']);
            Route::post('/colaboradores/{id}/dispositivos',                   [TalentoDeviceController::class, 'bind']);
            Route::post('/colaboradores/{id}/dispositivos/{devId}/approve',   [TalentoDeviceController::class, 'approve']);
            Route::delete('/colaboradores/{id}/dispositivos/{devId}',         [TalentoDeviceController::class, 'revoke']);

            // ── Compensación — regla del colaborador ──────────────────────────
            Route::post('/colaboradores/{id}/regla',       [TalentoCompensacionController::class, 'assignRule']);
            Route::get('/colaboradores/{id}/regla',        [TalentoCompensacionController::class, 'currentRule']);
            Route::get('/colaboradores/{id}/regla/historial', [TalentoCompensacionController::class, 'historyForColaborador']);

            // ── Avance semanal (endpoint para app) ───────────────────────────
            Route::get('/colaboradores/{id}/avance',       [TalentoLiquidacionController::class, 'avance']);

            // ── Roadmap ───────────────────────────────────────────────────────
            Route::get('/roadmap',        [TalentoRoadmapController::class, 'data']);
            Route::put('/roadmap/{id}',   [TalentoRoadmapController::class, 'update']);

            // ── Tipos de orden ────────────────────────────────────────────────
            Route::get('/order-types',       [TalentoWorkOrderController::class, 'types']);
            Route::post('/order-types',      [TalentoWorkOrderController::class, 'storeType']);
            Route::put('/order-types/{id}',  [TalentoWorkOrderController::class, 'updateType']);

            // ── Órdenes de trabajo ────────────────────────────────────────────
            Route::get('/ordenes',                          [TalentoWorkOrderController::class, 'data']);
            Route::post('/ordenes',                         [TalentoWorkOrderController::class, 'store']);
            Route::get('/ordenes/{id}',                     [TalentoWorkOrderController::class, 'show']);
            Route::put('/ordenes/{id}',                     [TalentoWorkOrderController::class, 'update']);
            Route::put('/ordenes/{id}/status',              [TalentoWorkOrderController::class, 'changeStatus']);
            Route::post('/ordenes/{id}/validate',           [TalentoWorkOrderController::class, 'validateOrder']);
            Route::post('/ordenes/{id}/actividades',        [TalentoWorkOrderController::class, 'addActivity']);

            // ── Reglas de compensación ────────────────────────────────────────
            Route::get('/reglas',        [TalentoCompensacionController::class, 'rules']);
            Route::post('/reglas',       [TalentoCompensacionController::class, 'storeRule']);
            Route::put('/reglas/{id}',   [TalentoCompensacionController::class, 'updateRule']);

            // ── Liquidaciones ─────────────────────────────────────────────────
            Route::get('/liquidaciones',          [TalentoLiquidacionController::class, 'data']);
            Route::post('/liquidaciones/calcular',[TalentoLiquidacionController::class, 'calcular']);
            Route::get('/liquidaciones/{id}',     [TalentoLiquidacionController::class, 'show']);
            Route::post('/liquidaciones/{id}/cerrar', [TalentoLiquidacionController::class, 'cerrar']);

            // ── Work Sites ────────────────────────────────────────────────────
            Route::get('/sitios',             [TalentoWorkSiteController::class, 'data']);
            Route::post('/sitios',            [TalentoWorkSiteController::class, 'store']);
            Route::put('/sitios/{id}',        [TalentoWorkSiteController::class, 'update']);
            Route::delete('/sitios/{id}',     [TalentoWorkSiteController::class, 'destroy']);
            Route::get('/sitios/config/radio',  [TalentoWorkSiteController::class, 'defaultRadius']);
            Route::put('/sitios/config/radio',  [TalentoWorkSiteController::class, 'updateDefaultRadius']);

            // ── Asistencia — App endpoints ────────────────────────────────────
            Route::post('/asistencia/check-in',  [TalentoAttendanceController::class, 'checkIn']);
            Route::post('/asistencia/check-out', [TalentoAttendanceController::class, 'checkOut']);
            Route::post('/asistencia/ping',      [TalentoAttendanceController::class, 'ping']);

            // ── Asistencia — Admin endpoints ──────────────────────────────────
            Route::get('/asistencia',              [TalentoAttendanceController::class, 'data']);
            Route::get('/asistencia/{id}',         [TalentoAttendanceController::class, 'show']);
            Route::put('/asistencia/{id}',         [TalentoAttendanceController::class, 'updateAdmin']);
            Route::post('/asistencia/{id}/extension', [TalentoAttendanceController::class, 'addExtension']);

            // ── Ubicación en vivo ─────────────────────────────────────────────
            Route::get('/ubicacion/en-vivo',              [TalentoAttendanceController::class, 'liveAll']);
            Route::get('/ubicacion/{colaboradorId}/ruta',  [TalentoAttendanceController::class, 'liveLocation']);

            // ── Flujo de campo (Fase 4a) ──────────────────────────────────────
            // Media
            Route::post('/campo/{workOrderId}/media',          [TalentoFieldFlowController::class, 'uploadMedia']);
            Route::get('/campo/{workOrderId}/media',           [TalentoFieldFlowController::class, 'listMedia']);

            // IA Validation
            Route::post('/campo/{workOrderId}/ia-validacion',  [TalentoFieldFlowController::class, 'runIaValidation']);
            Route::get('/campo/{workOrderId}/ia-validacion',   [TalentoFieldFlowController::class, 'getIaValidation']);
            Route::post('/campo/ia-validacion/{validationId}/override', [TalentoFieldFlowController::class, 'overrideIaValidation']);

            // Firmas
            Route::post('/campo/{workOrderId}/firma',          [TalentoFieldFlowController::class, 'storeSignature']);
            Route::get('/campo/{workOrderId}/firmas',          [TalentoFieldFlowController::class, 'getSignatures']);

            // Accept
            Route::post('/campo/{workOrderId}/aceptar',        [TalentoFieldFlowController::class, 'accept']);

            // Activación
            Route::post('/campo/{workOrderId}/activar',        [TalentoFieldFlowController::class, 'confirmActivation']);
            Route::get('/campo/{workOrderId}/activacion',      [TalentoFieldFlowController::class, 'getActivation']);

            // Onboarding + Encuesta
            Route::post('/campo/{workOrderId}/onboarding',     [TalentoFieldFlowController::class, 'onboard']);
            Route::get('/campo/{workOrderId}/encuesta',        [TalentoFieldFlowController::class, 'getSurvey']);
            Route::post('/campo/{workOrderId}/encuesta',       [TalentoFieldFlowController::class, 'submitSurvey']);

            // Estado completo del flujo de campo
            Route::get('/campo/{workOrderId}/estado',          [TalentoFieldFlowController::class, 'fieldFlowState']);

            // ── Cajas / Baselines / Health Bonus (Fase 4b) ───────────────────
            Route::get('/cajas',                        [TalentoCajaController::class, 'data']);
            Route::get('/cajas/latest',                 [TalentoCajaController::class, 'latestPerCaja']);
            Route::post('/cajas',                       [TalentoCajaController::class, 'store']);
            Route::get('/cajas/settings',               [TalentoCajaController::class, 'getSettings']);
            Route::put('/cajas/settings',               [TalentoCajaController::class, 'updateSettings']);
            Route::get('/cajas/bonus-log',              [TalentoCajaController::class, 'bonusLog']);
            Route::post('/cajas/bonus-log/{workOrderId}/evaluate', [TalentoCajaController::class, 'evaluateBonus']);

            // ── Ventana de garantía (Fase 4b) ─────────────────────────────────
            Route::get('/warranty/classify',            [TalentoWarrantyController::class, 'classify']);
            Route::get('/warranty',                     [TalentoWarrantyController::class, 'data']);
            Route::post('/warranty/{workOrderId}/override', [TalentoWarrantyController::class, 'override']);
            Route::get('/warranty/{workOrderId}/overrides', [TalentoWarrantyController::class, 'overrides']);

            // ── Rutas de planta interna (Fase 4b) ─────────────────────────────
            Route::get('/rutas',                        [TalentoRouteController::class, 'data']);
            Route::post('/rutas',                       [TalentoRouteController::class, 'store']);
            Route::get('/rutas/{id}',                   [TalentoRouteController::class, 'show']);
            Route::post('/rutas/{id}/activar',          [TalentoRouteController::class, 'activate']);
            Route::post('/rutas/{id}/analizar-desvios', [TalentoRouteController::class, 'analyzeDeviations']);
            Route::put('/rutas/{id}/stops/reordenar',   [TalentoRouteController::class, 'reorderStops']);

            // ── Proyectos planta externa (Fase 5a) ────────────────────────────
            // Tipos de actividad
            Route::get('/activity-types',       [TalentoProjectController::class, 'activityTypes']);
            Route::post('/activity-types',      [TalentoProjectController::class, 'storeActivityType']);
            Route::put('/activity-types/{id}',  [TalentoProjectController::class, 'updateActivityType']);

            // Proyectos CRUD
            Route::get('/proyectos',                    [TalentoProjectController::class, 'data']);
            Route::post('/proyectos',                   [TalentoProjectController::class, 'store']);
            Route::get('/proyectos/{id}',               [TalentoProjectController::class, 'show']);
            Route::put('/proyectos/{id}',               [TalentoProjectController::class, 'update']);

            // Pool de actividades del proyecto
            Route::post('/proyectos/{id}/actividades',         [TalentoProjectController::class, 'addActivity']);
            Route::put('/proyectos/{id}/actividades/{actId}',  [TalentoProjectController::class, 'updateActivity']);

            // Reportes diarios
            Route::get('/proyectos/{id}/actividades/{actId}/reportes',  [TalentoProjectController::class, 'listReports']);
            Route::post('/proyectos/{id}/actividades/{actId}/reportes', [TalentoProjectController::class, 'submitReport']);
            Route::post('/project-reports/{reportId}/approve',          [TalentoProjectController::class, 'approveReport']);

            // Bono de proyecto
            Route::post('/proyectos/{id}/bono',         [TalentoProjectController::class, 'awardBonus']);

            // Corredor del proyecto
            Route::get('/proyectos/{id}/corredor',              [TalentoProjectController::class, 'getCorridor']);
            Route::put('/proyectos/{id}/corredor',              [TalentoProjectController::class, 'saveCorridor']);
            Route::post('/proyectos/{id}/corredor/analizar',    [TalentoProjectController::class, 'analyzeCorridor']);
            Route::get('/proyectos/{id}/corredor/desvios',      [TalentoProjectController::class, 'listDeviations']);

            // Avance por colaborador
            Route::get('/colaboradores/{id}/proyecto-avance', [TalentoProjectController::class, 'colaboradorProgress']);

            // ── Calidad de caja — Estándares ──────────────────────────────
            Route::get('/standards',            [TalentoQualityController::class, 'standardsIndex']);
            Route::post('/standards',           [TalentoQualityController::class, 'storeStandard']);
            Route::put('/standards/{id}',       [TalentoQualityController::class, 'updateStandard']);
            Route::post('/standards/{id}/image',[TalentoQualityController::class, 'uploadStandardImage']);
            Route::delete('/standards/{id}',    [TalentoQualityController::class, 'destroyStandard']);

            // ── Calidad de caja — Inspecciones ────────────────────────────
            Route::get('/inspecciones',         [TalentoQualityController::class, 'inspectionsIndex']);
            Route::post('/inspecciones',        [TalentoQualityController::class, 'storeInspection']);
            Route::get('/inspecciones/{id}',    [TalentoQualityController::class, 'showInspection']);
            Route::post('/inspecciones/{id}/ia',    [TalentoQualityController::class, 'runIaAnalysis']);
            Route::post('/inspecciones/{id}/validate', [TalentoQualityController::class, 'supervisorValidate']);

            // ── Penalizaciones — Catálogo ─────────────────────────────────
            Route::get('/penalty-types',            [TalentoPenaltyController::class, 'typesIndex']);
            Route::post('/penalty-types',           [TalentoPenaltyController::class, 'storeType']);
            Route::put('/penalty-types/{id}',       [TalentoPenaltyController::class, 'updateType']);
            Route::post('/penalty-types/{id}/image',[TalentoPenaltyController::class, 'uploadTypeImage']);

            // ── Penalizaciones — Aplicación ───────────────────────────────
            Route::get('/penalties',                [TalentoPenaltyController::class, 'penaltiesIndex']);
            Route::post('/penalties',               [TalentoPenaltyController::class, 'applyPenalty']);
            Route::get('/penalties/{id}',           [TalentoPenaltyController::class, 'showPenalty']);

            // ── Penalizaciones — Apelaciones ──────────────────────────────
            Route::get('/penalty-appeals',              [TalentoPenaltyController::class, 'appealsIndex']);
            Route::post('/penalties/{id}/appeal',       [TalentoPenaltyController::class, 'submitAppeal']);
            Route::post('/penalty-appeals/{id}/resolve',[TalentoPenaltyController::class, 'resolveAppeal']);
        });
    });

// Media serve — named route (no auth middleware on the path, auth done in controller)
Route::middleware(['web', 'auth'])
    ->get('/talento/media/{id}', [TalentoFieldFlowController::class, 'serveMedia'])
    ->name('talento.media.serve');

Route::middleware(['web', 'auth'])
    ->get('/talento/inspection-photo/{id}', [TalentoQualityController::class, 'servePhoto'])
    ->name('talento.inspection.photo');

Route::middleware(['web', 'auth'])
    ->get('/talento/penalty-evidence/{id}', [TalentoPenaltyController::class, 'serveEvidencePhoto'])
    ->name('talento.penalty.evidence');
