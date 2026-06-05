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
use App\Modules\Addons\Talento\Controllers\TalentoCredentialController;
use App\Modules\Addons\Talento\Controllers\TalentoLoanSettlementController;
use App\Modules\Addons\Talento\Controllers\TalentoAcademyController;
use App\Modules\Addons\Talento\Controllers\TalentoLevelController;
use App\Modules\Addons\Talento\Controllers\TalentoDashboardController;
use App\Modules\Addons\Talento\Controllers\TalentoEscalafonController;
use App\Modules\Addons\Talento\Controllers\TalentoEmbajadoresController;
use App\Modules\Addons\Talento\Controllers\TalentoMobileApiController;
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
        Route::get('/credenciales',   [TalentoCredentialController::class, 'index']);
        Route::get('/finiquito',      [TalentoLoanSettlementController::class, 'index']);
        Route::get('/academia',       [TalentoAcademyController::class, 'index']);
        Route::get('/niveles',        [TalentoLevelController::class, 'index']);
        Route::get('/dashboard',      [TalentoDashboardController::class, 'index']);
        Route::get('/escalafon',      [TalentoEscalafonController::class, 'index']);
        Route::get('/embajadores-colabs', [TalentoEmbajadoresController::class, 'index']);

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
            Route::get('/asistencia/{id}',         [TalentoAttendanceController::class, 'show'])->where('id', '[0-9]+');
            Route::put('/asistencia/{id}',         [TalentoAttendanceController::class, 'updateAdmin'])->where('id', '[0-9]+');
            Route::post('/asistencia/{id}/extension', [TalentoAttendanceController::class, 'addExtension'])->where('id', '[0-9]+');

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

            // ── Credenciales ──────────────────────────────────────────────
            Route::get('/credentials/expiring',              [TalentoCredentialController::class, 'allExpiring']);
            Route::get('/credentials/funds-alert',           [TalentoCredentialController::class, 'allFundsAlert']);
            Route::get('/colaboradores/{id}/credentials',    [TalentoCredentialController::class, 'listForColaborador']);
            Route::post('/credentials',                      [TalentoCredentialController::class, 'store']);
            Route::put('/credentials/{id}',                  [TalentoCredentialController::class, 'update']);

            // ── Fondos ────────────────────────────────────────────────────
            Route::get('/colaboradores/{id}/funds',          [TalentoCredentialController::class, 'listFunds']);
            Route::post('/funds',                            [TalentoCredentialController::class, 'storeFund']);
            Route::post('/funds/{id}/authorize',             [TalentoCredentialController::class, 'authorizeFund']);
            Route::post('/funds/{id}/spent',                 [TalentoCredentialController::class, 'markFundSpent']);

            // ── Préstamos ─────────────────────────────────────────────────
            Route::get('/loans',                             [TalentoLoanSettlementController::class, 'loansIndex']);
            Route::post('/loans',                            [TalentoLoanSettlementController::class, 'storeLoan']);
            Route::post('/loans/{id}/authorize',             [TalentoLoanSettlementController::class, 'authorizeLoan']);
            Route::get('/colaboradores/{id}/loans',          [TalentoLoanSettlementController::class, 'loansForColaborador']);

            // ── Finiquito ─────────────────────────────────────────────────
            Route::get('/settlements',                               [TalentoLoanSettlementController::class, 'settlementsIndex']);
            Route::post('/colaboradores/{id}/settlement/draft',      [TalentoLoanSettlementController::class, 'draftSettlement']);
            Route::get('/settlements/{id}',                          [TalentoLoanSettlementController::class, 'showSettlement']);
            Route::put('/settlement-items/{id}',                     [TalentoLoanSettlementController::class, 'updateSettlementItem']);
            Route::post('/settlements/{id}/close',                   [TalentoLoanSettlementController::class, 'closeSettlement']);

            // ── Academia — Cursos ─────────────────────────────────────────
            Route::get('/courses',                    [TalentoAcademyController::class, 'courses']);
            Route::post('/courses',                   [TalentoAcademyController::class, 'storeCourse']);
            Route::get('/courses/{id}',               [TalentoAcademyController::class, 'showCourse']);
            Route::put('/courses/{id}',               [TalentoAcademyController::class, 'updateCourse']);
            Route::post('/courses/{id}/materials',    [TalentoAcademyController::class, 'storeMaterial']);
            Route::delete('/course-materials/{id}',   [TalentoAcademyController::class, 'destroyMaterial']);

            // ── Academia — Exámenes (admin/evaluador) ─────────────────────
            Route::post('/courses/{id}/exams',        [TalentoAcademyController::class, 'storeExam']);
            Route::post('/exams/{id}/questions',      [TalentoAcademyController::class, 'storeQuestion']);

            // ── Academia — Exámenes (colaborador) ────────────────────────
            Route::get('/exams/{id}/take',            [TalentoAcademyController::class, 'examForStudent']);
            Route::post('/exams/{id}/submit',         [TalentoAcademyController::class, 'submitExam']);
            Route::get('/exams/{id}/my-attempts',     [TalentoAcademyController::class, 'myAttempts']);

            // ── Academia — Evaluaciones prácticas ────────────────────────
            Route::post('/courses/{id}/practical',    [TalentoAcademyController::class, 'storePractical']);

            // ── Academia — Certificaciones ────────────────────────────────
            Route::get('/my-certifications',                          [TalentoAcademyController::class, 'myCertifications']);
            Route::get('/colaboradores/{id}/certifications',          [TalentoAcademyController::class, 'certificationsForColaborador']);
            Route::get('/colaboradores/{id}/academy-progress',        [TalentoAcademyController::class, 'progressForColaborador']);
            Route::post('/certifications/{id}/revoke',                [TalentoAcademyController::class, 'revokeCertification']);
            Route::get('/colaboradores/{id}/academy-progress',        [TalentoAcademyController::class, 'progressForColaborador']);

            // ── Niveles ───────────────────────────────────────────────────
            Route::get('/levels',                              [TalentoLevelController::class, 'levelsIndex']);
            Route::post('/levels',                             [TalentoLevelController::class, 'storeLevel']);
            Route::put('/levels/{id}',                         [TalentoLevelController::class, 'updateLevel']);
            Route::get('/colaboradores/{id}/level-eligibility',[TalentoLevelController::class, 'eligibility']);
            Route::post('/colaboradores/{id}/promote',         [TalentoLevelController::class, 'promote']);
            Route::get('/colaboradores/{id}/level-history',    [TalentoLevelController::class, 'levelHistory']);

            // ── Gating config ─────────────────────────────────────────────
            Route::get('/work-order-types',                    [TalentoLevelController::class, 'workOrderTypes']);
            Route::put('/work-order-types/{id}/level',         [TalentoLevelController::class, 'setWorkOrderTypeLevel']);
            Route::get('/activity-types-config',               [TalentoLevelController::class, 'activityTypes']);
            Route::put('/activity-types/{id}/level',           [TalentoLevelController::class, 'setActivityTypeLevel']);

            // ── Dashboard / Escalafón ─────────────────────────────────────
            Route::get('/dashboard/info-cards',                [TalentoDashboardController::class, 'infoCards']);
            Route::get('/dashboard/daily-production',          [TalentoDashboardController::class, 'dailyProduction']);
            Route::get('/dashboard/tecnico/{id}',              [TalentoDashboardController::class, 'tecnicoPreview']);
            Route::post('/dashboard/simulate-pay',             [TalentoDashboardController::class, 'simulatePay']);
            Route::get('/dashboard/equipo/{id}',               [TalentoDashboardController::class, 'equipoPreview']);

            // ── Escalafón ─────────────────────────────────────────────────
            Route::get('/escalafon',                           [TalentoEscalafonController::class, 'ranking']);
            Route::get('/escalafon/config',                    [TalentoEscalafonController::class, 'getConfig']);
            Route::put('/escalafon/config',                    [TalentoEscalafonController::class, 'saveConfig']);

            // ── Embajadores cross-link ────────────────────────────────────
            Route::get('/colaboradores/{id}/embajador-data',   [TalentoEmbajadoresController::class, 'embajadorData']);
            Route::get('/colaboradores/{id}/seller-data',      [TalentoEmbajadoresController::class, 'sellerData']);
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

Route::middleware(['web', 'auth'])
    ->get('/talento/credential-doc/{id}', [TalentoCredentialController::class, 'serveDocument'])
    ->name('talento.credential.doc');

Route::middleware(['web', 'auth'])
    ->get('/talento/practical-evidence/{id}', [TalentoAcademyController::class, 'serveEvidencePractical'])
    ->name('talento.practical.evidence');

// ══════════════════════════════════════════════════════════════════════════════
// App móvil "Talento Equipo" — API stateless con Sanctum Bearer token
// Prefijo: /talento/api/  (mismo que la SPA pero sin web/session middleware)
// ══════════════════════════════════════════════════════════════════════════════

// Login público (sin token)
Route::post('/talento/api/auth/login', [TalentoMobileApiController::class, 'login'])
    ->middleware([]);

// Consulta de última release (pública — no requiere token)
Route::get('/talento/api/app/latest', [TalentoMobileApiController::class, 'latestRelease'])
    ->middleware([]);

// Rutas protegidas con Sanctum
Route::middleware(['auth:sanctum'])
    ->prefix('talento/api')
    ->group(function () {
        Route::post('/auth/logout',          [TalentoMobileApiController::class, 'logout']);
        Route::get('/me',                    [TalentoMobileApiController::class, 'me']);

        Route::get('/asistencia/hoy',        [TalentoMobileApiController::class, 'asistenciaHoy']);
        Route::post('/asistencia/checkin',   [TalentoMobileApiController::class, 'checkin']);
        Route::post('/asistencia/checkout',  [TalentoMobileApiController::class, 'checkout']);

        Route::get('/ots/hoy',                        [TalentoMobileApiController::class, 'otsHoy']);
        Route::get('/ots/{id}',                       [TalentoMobileApiController::class, 'otShow']);
        Route::get('/ots/{id}/tipos-evidencia',       [TalentoMobileApiController::class, 'tiposEvidencia']);
        Route::post('/ots/{id}/evidencia',            [TalentoMobileApiController::class, 'otEvidencia']);
        Route::post('/ots/{id}/iniciar',              [TalentoMobileApiController::class, 'iniciarOT']);
        Route::post('/ots/{id}/completar',            [TalentoMobileApiController::class, 'completarOT']);

        Route::get('/compensacion/semana',   [TalentoMobileApiController::class, 'compensacionSemana']);
    });
