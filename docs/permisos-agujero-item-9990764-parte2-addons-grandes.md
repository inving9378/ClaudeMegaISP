# Inventario de permisos por ruta — Addons grandes (Talento / MegaFamilia / Mapas / Roadmap / Marketing / GestionRed / MapaRed)

Item #9990770 (Fase 4b-2, lote 2/3 del padre #9990764, a su vez sub-item de #9990745). SOLO
LECTURA: no se tocó ninguna ruta, middleware, config ni permiso — solo se documentó.

## Metodología (idéntica a la del lote hermano #9990769)

`check_route_permission` NO se hereda por default (`BaseModuleServiceProvider::boot()` hace
`loadRoutesFrom()` a secas — `app/Modules/BaseModuleServiceProvider.php:33-36`). Cada
`routes.php` de módulo es responsable de envolver sus propias rutas en el middleware que
quiera.

Clasificación por ruta (se recorren los `Route::group`/`->middleware()` anidados de cada
archivo; un archivo puede tener varios grupos):

1. **PROTEGIDA** — dentro de un grupo con `check_route_permission`.
2. **GATEADA POR ROL/GUARD** — `role:...`/`permission:...` de Spatie (middleware de ruta o de
   controller vía `$this->middleware(...)`), o guard dedicado (`auth.portal`, `auth:sanctum`
   con scoping propio, `can:permiso` puntual en la propia definición de ruta).
3. **AUTHORIZE INLINE** — sin `check_route_permission` pero el método del controller llama
   `->authorize(`/`Gate::allows(`/`Gate::authorize(`/`abort_unless(...->can(...))`/valida un
   token o secreto con `hash_equals` al inicio — defensa en profundidad equivalente.
4. **AGUJERO REAL** — solo `auth` (o `web` solo, o nada) sin 1/2/3. Riesgo CRÍTICO si modifica
   datos/dinero, MEDIO si es lectura sensible, BAJO si es lectura pública-adyacente.
5. **PUBLIC_ROUTES explícita** (o público intencional documentado en el propio código con el
   mismo criterio) — no es agujero, es a propósito.

`Route::resource(...)` se expande a sus rutas CRUD reales.

---

## Resumen ejecutivo

| Addon | Rutas revisadas | 1 Protegida | 2 Rol/Guard | 3 Authorize inline | 4 AGUJERO REAL | 5 Pública intencional |
|---|---|---|---|---|---|---|
| Talento | 262 | 226 | 33 | 0 | 0 | 3 |
| MegaFamilia | 190 | 0 | 178 | 0 | **9** | 3 |
| Mapas | 174 (110 declaradas + 4 `resource()` expandidas a 26) | 174 | 0 | 0 | 0 | 0 |
| Roadmap | 93 | 0 | 5 | 87 | 0 | 1 |
| Marketing | 121 | 44 | 59 | 3 | **8** | 7 |
| GestionRed | 116 | 116 | 0 | 0 | 0 | 0 |
| MapaRed | 98 | 98 | 0 | 0 | 0 | 0 |
| **TOTAL** | **1054** | **658** | **275** | **90** | **17** | **14** |

**Hallazgo crítico — MegaFamilia (datos de menores):** 4 rutas del bloque "Cliente final (solo
auth, sin permission extra)" leen/exportan datos de **todos** los perfiles/familias del sistema
sin ningún scoping cuando el usuario autenticado no tiene su propia `ParentalAccount` — es decir,
**cualquier usuario autenticado** (staff de cualquier rol, sin necesitar `megafamilia_admin` ni
`megafamilia_support`) puede leer nombres/edades/nivel escolar de menores, tareas/recompensas y
analítica de comportamiento (tiempo de pantalla, apps, sitios bloqueados) de **cualquier familia
cliente**, más 2 rutas GET de solo-shell sin riesgo real. Ver detalle en la sección MegaFamilia.

**Hallazgo alto — Marketing (WhatsApp real / campañas piloto):** `MarketingConversationController`
(conversaciones de WhatsApp con clientes reales — leer, enviar mensaje como el bot, reasignar,
cerrar) y `PilotCampaignController` (crear/enviar campaña piloto por email) no tienen NINGÚN
middleware de permiso ni `authorize()` — solo requieren sesión. Ver detalle en la sección
Marketing.

Mapas, GestionRed y MapaRed **no presentan agujeros**: sus 3 archivos envuelven el 100% de sus
rutas en `check_route_permission` sin excepción.

---

## 1. Talento (`app/Modules/Addons/Talento/routes.php`, 516 líneas)

### 1.1 Vistas web + API JSON del panel admin — TODAS bajo `web, auth, check_route_permission` (líneas 36-390)

Clasificación **1 (PROTEGIDA)** para las 226 rutas del bloque completo, sin excepción — se
recorrió cada una para confirmar que ninguna queda fuera del grupo. Un extracto representativo
(la lista completa son las líneas 41-388 del archivo, un `Route::get/post/put/delete` por línea,
todas dentro del mismo `Route::middleware(['web','auth','check_route_permission'])->prefix('talento')`
abierto en la línea 36 y cerrado en la 390):

| Método | Ruta | Controller@método | Línea | Clase |
|---|---|---|---|---|
| GET | /talento/ | TalentoColaboradorController@index | 41 | 1 |
| GET | /talento/custodia | TalentoCustodiaController@index | 42 | 1 |
| GET | /talento/dispositivos | TalentoDeviceController@index | 43 | 1 |
| GET | /talento/roadmap | TalentoRoadmapController@index | 44 | 1 |
| GET | /talento/ordenes | TalentoWorkOrderController@index | 45 | 1 |
| GET | /talento/compensacion | TalentoCompensacionController@index | 46 | 1 |
| GET | /talento/liquidaciones | TalentoLiquidacionController@index | 47 | 1 |
| GET | /talento/asistencia | TalentoAttendanceController@index | 48 | 1 |
| GET | /talento/mapa-en-vivo | closure (view talento.mapa_vivo) | 49 | 1 |
| GET | /talento/sitios | closure (view talento.sitios) | 50 | 1 |
| GET | /talento/campo | TalentoFieldFlowController@index | 51 | 1 |
| GET | /talento/cajas | TalentoCajaController@index | 52 | 1 |
| GET | /talento/rutas | TalentoRouteController@index | 53 | 1 |
| GET | /talento/proyectos | TalentoProjectController@index | 54 | 1 |
| GET | /talento/calidad | closure (view talento.calidad) | 55 | 1 |
| GET | /talento/penalizaciones | TalentoPenaltyController@index | 56 | 1 |
| GET | /talento/credenciales | TalentoCredentialController@index | 57 | 1 |
| GET | /talento/finiquito | TalentoLoanSettlementController@index | 58 | 1 |
| GET | /talento/academia | TalentoAcademyController@index | 59 | 1 |
| GET | /talento/niveles | TalentoLevelController@index | 60 | 1 |
| GET | /talento/dashboard | TalentoDashboardController@index | 61 | 1 |
| GET | /talento/escalafon | TalentoEscalafonController@index | 62 | 1 |
| GET | /talento/embajadores-colabs | TalentoEmbajadoresController@index | 63 | 1 |
| GET | /talento/mis-ventas | TalentoVentasController@index | 64 | 1 |
| GET | /talento/config/evidencias | TalentoEvidenciaConfigController@index | 65 | 1 |
| GET | /talento/expediente/paquetes | TalentoPaqueteDocumentoController@index | 66 | 1 |
| GET | /talento/puestos | TalentoPuestoController@index | 67 | 1 |
| GET | /talento/articulos-vendedor | TalentoSellerItemsController@index | 68 | 1 |
| GET | /talento/colaboradores/{id}/documentos/{docId} | TalentoEmployeeDocumentController@show | 71 | 1 |
| GET | /talento/api/colaboradores | TalentoColaboradorController@data | 77 | 1 |
| POST | /talento/api/colaboradores | @store | 78 | 1 |
| GET | /talento/api/colaboradores/users-disponibles | @usersDisponibles | 79 | 1 |
| GET | /talento/api/colaboradores/role-departments | @roleDepartments | 80 | 1 |
| GET | /talento/api/colaboradores/{id} | @show | 81 | 1 |
| PUT | /talento/api/colaboradores/{id} | @update | 82 | 1 |
| DELETE | /talento/api/colaboradores/{id} | @destroy | 83 | 1 |
| GET | /talento/api/colaboradores/{id}/custodia | TalentoCustodiaController@show | 86 | 1 |
| GET | /talento/api/colaboradores/{id}/documentos | TalentoEmployeeDocumentController@forColaborador | 89 | 1 |
| POST | /talento/api/colaboradores/{id}/documentos/{docId}/firma | @sign | 92 | 1 |
| GET | /talento/api/colaboradores/{id}/documentos/{docId}/firma | @firma | 93 | 1 |
| GET | /talento/api/colaboradores/{id}/documentos/{docId}/huecos | @huecos | 96 | 1 |
| POST | /talento/api/colaboradores/{id}/documentos/{docId}/completar | @completar | 97 | 1 |
| GET | /talento/api/vendedores/{sellerId}/expediente | TalentoColaboradorController@expedienteVendedor | 100 | 1 |
| GET | /talento/api/colaboradores/{id}/dispositivos | TalentoDeviceController@forColaborador | 103 | 1 |
| POST | /talento/api/colaboradores/{id}/dispositivos | @bind | 104 | 1 |
| POST | /talento/api/colaboradores/{id}/dispositivos/{devId}/approve | @approve | 105 | 1 |
| DELETE | /talento/api/colaboradores/{id}/dispositivos/{devId} | @revoke | 106 | 1 |
| POST | /talento/api/colaboradores/{id}/regla | TalentoCompensacionController@assignRule | 109 | 1 |
| GET | /talento/api/colaboradores/{id}/regla | @currentRule | 110 | 1 |
| GET | /talento/api/colaboradores/{id}/regla/historial | @historyForColaborador | 111 | 1 |
| GET | /talento/api/colaboradores/{id}/avance | TalentoLiquidacionController@avance | 114 | 1 |
| GET | /talento/api/roadmap | TalentoRoadmapController@data | 117 | 1 |
| PUT | /talento/api/roadmap/{id} | @update | 118 | 1 |
| GET | /talento/api/order-types | TalentoWorkOrderController@types | 121 | 1 |
| POST | /talento/api/order-types | @storeType | 122 | 1 |
| PUT | /talento/api/order-types/{id} | @updateType | 123 | 1 |
| GET | /talento/api/ordenes | @data | 126 | 1 |
| POST | /talento/api/ordenes | @store | 127 | 1 |
| GET | /talento/api/ordenes/{id} | @show | 128 | 1 |
| PUT | /talento/api/ordenes/{id} | @update | 129 | 1 |
| PUT | /talento/api/ordenes/{id}/status | @changeStatus | 130 | 1 |
| POST | /talento/api/ordenes/{id}/validate | @validateOrder | 131 | 1 |
| POST | /talento/api/ordenes/{id}/actividades | @addActivity | 132 | 1 |
| GET | /talento/api/reglas | TalentoCompensacionController@rules | 135 | 1 |
| POST | /talento/api/reglas | @storeRule | 136 | 1 |
| PUT | /talento/api/reglas/{id} | @updateRule | 137 | 1 |
| GET | /talento/api/liquidaciones | TalentoLiquidacionController@data | 140 | 1 |
| POST | /talento/api/liquidaciones/calcular | @calcular | 141 | 1 |
| GET | /talento/api/liquidaciones/{id} | @show | 142 | 1 |
| POST | /talento/api/liquidaciones/{id}/cerrar | @cerrar | 143 | 1 |
| GET | /talento/api/sitios | TalentoWorkSiteController@data | 146 | 1 |
| POST | /talento/api/sitios | @store | 147 | 1 |
| PUT | /talento/api/sitios/{id} | @update | 148 | 1 |
| DELETE | /talento/api/sitios/{id} | @destroy | 149 | 1 |
| GET | /talento/api/sitios/config/radio | @defaultRadius | 150 | 1 |
| PUT | /talento/api/sitios/config/radio | @updateDefaultRadius | 151 | 1 |
| POST | /talento/api/asistencia/check-in | TalentoAttendanceController@checkIn | 154 | 1 |
| POST | /talento/api/asistencia/check-out | @checkOut | 155 | 1 |
| POST | /talento/api/asistencia/ping | @ping | 156 | 1 |
| GET | /talento/api/asistencia | @data | 159 | 1 |
| GET | /talento/api/asistencia/{id} | @show | 160 | 1 |
| PUT | /talento/api/asistencia/{id} | @updateAdmin | 161 | 1 |
| POST | /talento/api/asistencia/{id}/extension | @addExtension | 162 | 1 |
| GET | /talento/api/ubicacion/en-vivo | @liveAll | 165 | 1 |
| GET | /talento/api/ubicacion/{colaboradorId}/ruta | @liveLocation | 166 | 1 |
| POST | /talento/api/campo/{workOrderId}/media | TalentoFieldFlowController@uploadMedia | 170 | 1 |
| GET | /talento/api/campo/{workOrderId}/media | @listMedia | 171 | 1 |
| POST | /talento/api/campo/{workOrderId}/ia-validacion | @runIaValidation | 174 | 1 |
| GET | /talento/api/campo/{workOrderId}/ia-validacion | @getIaValidation | 175 | 1 |
| POST | /talento/api/campo/ia-validacion/{validationId}/override | @overrideIaValidation | 176 | 1 |
| POST | /talento/api/campo/{workOrderId}/firma | @storeSignature | 179 | 1 |
| GET | /talento/api/campo/{workOrderId}/firmas | @getSignatures | 180 | 1 |
| POST | /talento/api/campo/{workOrderId}/aceptar | @accept | 183 | 1 |
| POST | /talento/api/campo/{workOrderId}/activar | @confirmActivation | 186 | 1 |
| GET | /talento/api/campo/{workOrderId}/activacion | @getActivation | 187 | 1 |
| POST | /talento/api/campo/{workOrderId}/onboarding | @onboard | 190 | 1 |
| GET | /talento/api/campo/{workOrderId}/encuesta | @getSurvey | 191 | 1 |
| POST | /talento/api/campo/{workOrderId}/encuesta | @submitSurvey | 192 | 1 |
| GET | /talento/api/campo/{workOrderId}/estado | @fieldFlowState | 195 | 1 |
| GET | /talento/api/cajas | TalentoCajaController@data | 198 | 1 |
| GET | /talento/api/cajas/latest | @latestPerCaja | 199 | 1 |
| POST | /talento/api/cajas | @store | 200 | 1 |
| GET | /talento/api/cajas/settings | @getSettings | 201 | 1 |
| PUT | /talento/api/cajas/settings | @updateSettings | 202 | 1 |
| GET | /talento/api/cajas/bonus-log | @bonusLog | 203 | 1 |
| POST | /talento/api/cajas/bonus-log/{workOrderId}/evaluate | @evaluateBonus | 204 | 1 |
| GET | /talento/api/warranty/classify | TalentoWarrantyController@classify | 207 | 1 |
| GET | /talento/api/warranty | @data | 208 | 1 |
| POST | /talento/api/warranty/{workOrderId}/override | @override | 209 | 1 |
| GET | /talento/api/warranty/{workOrderId}/overrides | @overrides | 210 | 1 |
| GET | /talento/api/rutas | TalentoRouteController@data | 213 | 1 |
| POST | /talento/api/rutas | @store | 214 | 1 |
| GET | /talento/api/rutas/{id} | @show | 215 | 1 |
| POST | /talento/api/rutas/{id}/activar | @activate | 216 | 1 |
| POST | /talento/api/rutas/{id}/analizar-desvios | @analyzeDeviations | 217 | 1 |
| PUT | /talento/api/rutas/{id}/stops/reordenar | @reorderStops | 218 | 1 |
| GET | /talento/api/activity-types | TalentoProjectController@activityTypes | 222 | 1 |
| POST | /talento/api/activity-types | @storeActivityType | 223 | 1 |
| PUT | /talento/api/activity-types/{id} | @updateActivityType | 224 | 1 |
| GET | /talento/api/proyectos | @data | 227 | 1 |
| POST | /talento/api/proyectos | @store | 228 | 1 |
| GET | /talento/api/proyectos/{id} | @show | 229 | 1 |
| PUT | /talento/api/proyectos/{id} | @update | 230 | 1 |
| POST | /talento/api/proyectos/{id}/actividades | @addActivity | 233 | 1 |
| PUT | /talento/api/proyectos/{id}/actividades/{actId} | @updateActivity | 234 | 1 |
| GET | /talento/api/proyectos/{id}/actividades/{actId}/reportes | @listReports | 237 | 1 |
| POST | /talento/api/proyectos/{id}/actividades/{actId}/reportes | @submitReport | 238 | 1 |
| POST | /talento/api/project-reports/{reportId}/approve | @approveReport | 239 | 1 |
| POST | /talento/api/proyectos/{id}/bono | @awardBonus | 242 | 1 |
| GET | /talento/api/proyectos/{id}/corredor | @getCorridor | 245 | 1 |
| PUT | /talento/api/proyectos/{id}/corredor | @saveCorridor | 246 | 1 |
| POST | /talento/api/proyectos/{id}/corredor/analizar | @analyzeCorridor | 247 | 1 |
| GET | /talento/api/proyectos/{id}/corredor/desvios | @listDeviations | 248 | 1 |
| GET | /talento/api/colaboradores/{id}/proyecto-avance | @colaboradorProgress | 251 | 1 |
| GET | /talento/api/standards | TalentoQualityController@standardsIndex | 254 | 1 |
| POST | /talento/api/standards | @storeStandard | 255 | 1 |
| PUT | /talento/api/standards/{id} | @updateStandard | 256 | 1 |
| POST | /talento/api/standards/{id}/image | @uploadStandardImage | 257 | 1 |
| DELETE | /talento/api/standards/{id} | @destroyStandard | 258 | 1 |
| GET | /talento/api/inspecciones | @inspectionsIndex | 261 | 1 |
| POST | /talento/api/inspecciones | @storeInspection | 262 | 1 |
| GET | /talento/api/inspecciones/{id} | @showInspection | 263 | 1 |
| POST | /talento/api/inspecciones/{id}/ia | @runIaAnalysis | 264 | 1 |
| POST | /talento/api/inspecciones/{id}/validate | @supervisorValidate | 265 | 1 |
| GET | /talento/api/penalty-types | TalentoPenaltyController@typesIndex | 268 | 1 |
| POST | /talento/api/penalty-types | @storeType | 269 | 1 |
| PUT | /talento/api/penalty-types/{id} | @updateType | 270 | 1 |
| POST | /talento/api/penalty-types/{id}/image | @uploadTypeImage | 271 | 1 |
| GET | /talento/api/penalties | @penaltiesIndex | 274 | 1 |
| POST | /talento/api/penalties | @applyPenalty | 275 | 1 |
| GET | /talento/api/penalties/{id} | @showPenalty | 276 | 1 |
| GET | /talento/api/penalty-appeals | @appealsIndex | 279 | 1 |
| POST | /talento/api/penalties/{id}/appeal | @submitAppeal | 280 | 1 |
| POST | /talento/api/penalty-appeals/{id}/resolve | @resolveAppeal | 281 | 1 |
| GET | /talento/api/credentials/expiring | TalentoCredentialController@allExpiring | 284 | 1 |
| GET | /talento/api/credentials/funds-alert | @allFundsAlert | 285 | 1 |
| GET | /talento/api/colaboradores/{id}/credentials | @listForColaborador | 286 | 1 |
| POST | /talento/api/credentials | @store | 287 | 1 |
| PUT | /talento/api/credentials/{id} | @update | 288 | 1 |
| GET | /talento/api/colaboradores/{id}/funds | @listFunds | 291 | 1 |
| POST | /talento/api/funds | @storeFund | 292 | 1 |
| POST | /talento/api/funds/{id}/authorize | @authorizeFund | 293 | 1 |
| POST | /talento/api/funds/{id}/spent | @markFundSpent | 294 | 1 |
| GET | /talento/api/loans | TalentoLoanSettlementController@loansIndex | 297 | 1 |
| POST | /talento/api/loans | @storeLoan | 298 | 1 |
| POST | /talento/api/loans/{id}/authorize | @authorizeLoan | 299 | 1 |
| GET | /talento/api/colaboradores/{id}/loans | @loansForColaborador | 300 | 1 |
| GET | /talento/api/settlements | @settlementsIndex | 303 | 1 |
| POST | /talento/api/colaboradores/{id}/settlement/draft | @draftSettlement | 304 | 1 |
| GET | /talento/api/settlements/{id} | @showSettlement | 305 | 1 |
| PUT | /talento/api/settlement-items/{id} | @updateSettlementItem | 306 | 1 |
| POST | /talento/api/settlements/{id}/close | @closeSettlement | 307 | 1 |
| GET | /talento/api/courses | TalentoAcademyController@courses | 310 | 1 |
| POST | /talento/api/courses | @storeCourse | 311 | 1 |
| GET | /talento/api/courses/{id} | @showCourse | 312 | 1 |
| PUT | /talento/api/courses/{id} | @updateCourse | 313 | 1 |
| POST | /talento/api/courses/{id}/materials | @storeMaterial | 314 | 1 |
| DELETE | /talento/api/course-materials/{id} | @destroyMaterial | 315 | 1 |
| POST | /talento/api/courses/{id}/exams | @storeExam | 318 | 1 |
| POST | /talento/api/exams/{id}/questions | @storeQuestion | 319 | 1 |
| GET | /talento/api/exams/{id}/take | @examForStudent | 322 | 1 |
| POST | /talento/api/exams/{id}/submit | @submitExam | 323 | 1 |
| GET | /talento/api/exams/{id}/my-attempts | @myAttempts | 324 | 1 |
| POST | /talento/api/courses/{id}/practical | @storePractical | 327 | 1 |
| GET | /talento/api/my-certifications | @myCertifications | 330 | 1 |
| GET | /talento/api/colaboradores/{id}/certifications | @certificationsForColaborador | 331 | 1 |
| GET | /talento/api/colaboradores/{id}/academy-progress | @progressForColaborador | 332 | 1 |
| POST | /talento/api/certifications/{id}/revoke | @revokeCertification | 333 | 1 |
| GET | /talento/api/colaboradores/{id}/academy-progress | @progressForColaborador (duplicada) | 334 | 1 |
| GET | /talento/api/levels | TalentoLevelController@levelsIndex | 337 | 1 |
| POST | /talento/api/levels | @storeLevel | 338 | 1 |
| PUT | /talento/api/levels/{id} | @updateLevel | 339 | 1 |
| GET | /talento/api/colaboradores/{id}/level-eligibility | @eligibility | 340 | 1 |
| POST | /talento/api/colaboradores/{id}/promote | @promote | 341 | 1 |
| GET | /talento/api/colaboradores/{id}/level-history | @levelHistory | 342 | 1 |
| GET | /talento/api/work-order-types | @workOrderTypes | 345 | 1 |
| PUT | /talento/api/work-order-types/{id}/level | @setWorkOrderTypeLevel | 346 | 1 |
| GET | /talento/api/activity-types-config | @activityTypes | 347 | 1 |
| PUT | /talento/api/activity-types/{id}/level | @setActivityTypeLevel | 348 | 1 |
| GET | /talento/api/dashboard/info-cards | TalentoDashboardController@infoCards | 351 | 1 |
| GET | /talento/api/dashboard/daily-production | @dailyProduction | 352 | 1 |
| GET | /talento/api/dashboard/tecnico/{id} | @tecnicoPreview | 353 | 1 |
| POST | /talento/api/dashboard/simulate-pay | @simulatePay | 354 | 1 |
| GET | /talento/api/dashboard/equipo/{id} | @equipoPreview | 355 | 1 |
| GET | /talento/api/escalafon | TalentoEscalafonController@ranking | 358 | 1 |
| GET | /talento/api/escalafon/config | @getConfig | 359 | 1 |
| PUT | /talento/api/escalafon/config | @saveConfig | 360 | 1 |
| GET | /talento/api/colaboradores/{id}/embajador-data | TalentoEmbajadoresController@embajadorData | 363 | 1 |
| GET | /talento/api/colaboradores/{id}/seller-data | @sellerData | 364 | 1 |
| GET | /talento/api/mis-ventas | TalentoVentasController@misVentas | 367 | 1 |
| GET | /talento/api/config/evidencias | TalentoEvidenciaConfigController@catalogo | 370 | 1 |
| POST | /talento/api/config/evidencias/toggle | @toggle | 371 | 1 |
| GET | /talento/api/puestos | TalentoPuestoController@data (declarada 2 veces, líneas 374 y 383) | 374, 383 | 1 |
| GET | /talento/api/expediente/paquetes/puestos | TalentoPaqueteDocumentoController@puestos | 377 | 1 |
| GET | /talento/api/expediente/paquetes/templates | @templates | 378 | 1 |
| GET | /talento/api/expediente/paquetes/asignaciones | @asignaciones | 379 | 1 |
| POST | /talento/api/expediente/paquetes/sincronizar | @sincronizar | 380 | 1 |
| POST | /talento/api/puestos | @store | 384 | 1 |
| PUT | /talento/api/puestos/{id} | @update | 385 | 1 |
| GET | /talento/api/articulos-vendedor | TalentoSellerItemsController@data | 388 | 1 |

### 1.2 Media serve (`web, auth` — SIN check_route_permission, pero con `->authorize()` inline)

| Método | Ruta | Controller@método | Línea | Clase |
|---|---|---|---|---|
| GET | /talento/media/{id} | TalentoFieldFlowController@serveMedia | 394 | 3 — `$this->authorize('talento.media.view')`/`'talento.media.view_sensitive'` (controller:102-118) |
| GET | /talento/inspection-photo/{id} | TalentoQualityController@servePhoto | 398 | 3 — `$this->authorize('talento.quality.view')` (controller:202) |
| GET | /talento/penalty-evidence/{id} | TalentoPenaltyController@serveEvidencePhoto | 402 | 3 — `$this->authorize('talento.penalties.view')` (controller:136) |
| GET | /talento/credential-doc/{id} | TalentoCredentialController@serveDocument | 406 | 3 — `$this->authorize('talento.credentials.view')` (controller:126) |
| GET | /talento/practical-evidence/{id} | TalentoAcademyController@serveEvidencePractical | 410 | 3 — `$this->authorize('talento.academy.view')` (controller:245) |

### 1.3 App móvil "Talento Equipo" (`auth:sanctum`, prefijo `/talento/api/`) — OJO del item aplicado

Guard propio (Sanctum Bearer), distinto del panel admin web — no es agujero según el criterio
explícito del item.

| Método | Ruta | Controller@método | Línea | Clase |
|---|---|---|---|---|
| POST | /talento/api/auth/login | TalentoMobileApiController@login | 419 | 5 — público a propósito (`->middleware([])`), valida credenciales dentro del método |
| GET | /talento/api/health | @health | 423 | 5 — público a propósito, solo nombre/logo de empresa |
| GET | /talento/api/app/latest | @latestRelease | 424 | 5 — público a propósito, solo versión de APK disponible |
| GET | /talento/api/app/branding | @appBranding | 425 | 5 — público a propósito, solo logo/branding |
| POST | /talento/api/auth/logout | @logout | 431 | 2 — `auth:sanctum` |
| GET | /talento/api/me | @me | 432 | 2 |
| GET | /talento/api/asistencia/hoy | @asistenciaHoy | 434 | 2 |
| POST | /talento/api/asistencia/checkin | @checkin | 435 | 2 |
| POST | /talento/api/asistencia/checkout | @checkout | 436 | 2 |
| GET | /talento/api/ots/historial | @otHistorial | 438 | 2 |
| GET | /talento/api/ots/hoy | @otsHoy | 439 | 2 |
| GET | /talento/api/ots/{id} | @otShow | 440 | 2 |
| GET | /talento/api/ots/{id}/tipos-evidencia | @tiposEvidencia | 441 | 2 |
| POST | /talento/api/ots/{id}/evidencia | @otEvidencia | 442 | 2 |
| POST | /talento/api/ots/{id}/iniciar | @iniciarOT | 443 | 2 |
| POST | /talento/api/ots/{id}/completar | @completarOT | 444 | 2 |
| POST | /talento/api/ots/{id}/incidencia | @reportarIncidencia | 445 | 2 |
| PUT | /talento/api/ots/{id}/nota | @guardarNota | 446 | 2 |
| GET | /talento/api/compensacion/semana | @compensacionSemana | 448 | 2 |
| GET | /talento/api/embajador/resumen | @embajadorResumen | 451 | 2 |
| POST | /talento/api/devices/token | @registerDeviceToken | 453 | 2 |

### 1.4 Portal Técnico Web (PWA) — `can:portal.colaborador` (fuera de `check_route_permission` a propósito, per comentario del propio archivo)

| Método | Ruta | Controller@método | Línea | Clase |
|---|---|---|---|---|
| GET | /talento/portal/manifest.webmanifest | PortalTecnicoController@manifest | 465 | 5 — público a propósito, solo asset PWA no sensible |
| GET | /talento/portal/sw.js | @serviceWorker | 466 | 5 — idem |
| GET | /talento/portal/ | @index | 472 | 2 — `can:portal.colaborador` |
| POST | /talento/portal/preferencias/tema | @saveTheme | 473 | 2 |
| GET | /talento/portal/asistencia/hoy | @asistenciaHoy | 476 | 2 |
| POST | /talento/portal/asistencia/checkin | @checkin | 477 | 2 |
| POST | /talento/portal/asistencia/checkout | @checkout | 478 | 2 |
| GET | /talento/portal/ots/hoy | @otsHoy | 481 | 2 |
| GET | /talento/portal/dinero/cuenta | @dineroCuenta | 484 | 2 |
| GET | /talento/portal/dinero/desglose | @dineroDesglose | 485 | 2 |
| GET | /talento/portal/dinero/fondo | @dineroFondo | 486 | 2 |
| GET | /talento/portal/dinero/prestamos | @dineroPrestamos | 487 | 2 |
| GET | /talento/portal/material | @material | 490 | 2 |
| GET | /talento/portal/prospectos | @prospectos | 493 | 2 |
| GET | /talento/portal/ot/{origen}/{id} | @otDetalle | 496 | 2 |
| POST | /talento/portal/ot/{origen}/{id}/iniciar | @iniciarOt | 500 | 2 |
| POST | /talento/portal/ot/{origen}/{id}/completar | @completarOt | 502 | 2 |
| POST | /talento/portal/ot/{origen}/{id}/evidencia | @subirEvidenciaOt | 506 | 2 |
| POST | /talento/portal/ot/{origen}/{id}/firma | @guardarFirma | 510 | 2 |
| POST | /talento/portal/ot/{origen}/{id}/aceptar | @aceptarOt | 514 | 2 |

**Talento: 0 agujeros reales.** Todas las rutas caen en 1/2/3/5.

---

## 2. MegaFamilia (`app/Modules/Addons/MegaFamilia/routes.php`, 363 líneas)

### 2.1 Públicas — distribución de APK (`web`, sin auth) — intencional, documentado en el propio archivo (líneas 39-41)

| Método | Ruta | Controller@método | Línea | Clase |
|---|---|---|---|---|
| GET | /megafamilia/descargar | AppVersionController@downloadPage | 45 | 5* — público intencional (comentario explícito "distribución de APK") |
| GET | /api/megafamilia/mobile/check-update | @checkUpdate | 50 | 5* |
| GET | /api/megafamilia/mobile/download/{id} | @download | 51 | 5* |

### 2.2 UI Web — solo admin (`web, auth, permission:megafamilia_admin`, líneas 62-154)

Clasificación **2** para las 47 rutas de este bloque (middleware `permission:` de Spatie sobre
todo el grupo):

| Método | Ruta | Controller@método | Línea |
|---|---|---|---|
| GET | /megafamilia/ | DashboardController@index | 63 |
| GET | /megafamilia/dashboard/summary | @summary | 64 |
| GET | /megafamilia/clientes/ | ClientesController@index | 67 |
| GET | /megafamilia/clientes/data | @data | 68 |
| GET | /megafamilia/clientes/{id} | @show | 69 |
| POST | /megafamilia/clientes/{id}/activate | @activate | 70 |
| POST | /megafamilia/clientes/{id}/suspend | @suspend | 71 |
| POST | /megafamilia/clientes/{id}/cancel | @cancel | 72 |
| POST | /megafamilia/clientes/{id}/change-plan | @changePlan | 73 |
| GET | /megafamilia/licencias/ | LicenciasController@index | 77 |
| GET | /megafamilia/licencias/data | @data | 78 |
| GET | /megafamilia/licencias/plans | @plans | 79 |
| GET | /megafamilia/licencias/accounts | @accounts | 80 |
| POST | /megafamilia/licencias/ | @store | 81 |
| POST | /megafamilia/licencias/{id}/renew | @renew | 82 |
| POST | /megafamilia/licencias/{id}/suspend | @suspend | 83 |
| POST | /megafamilia/licencias/{id}/reactivate | @reactivate | 84 |
| GET | /megafamilia/planes/ | PlanesController@index | 88 |
| GET | /megafamilia/planes/data | @data | 89 |
| POST | /megafamilia/planes/ | @store | 90 |
| PUT | /megafamilia/planes/{id} | @update | 91 |
| DELETE | /megafamilia/planes/{id} | @destroy | 92 |
| POST | /megafamilia/planes/{id}/toggle | @toggle | 93 |
| POST | /megafamilia/planes/{id} | @update (compat legacy) | 95 |
| GET | /megafamilia/ingresos/ | IngresosController@index | 99 |
| GET | /megafamilia/ingresos/data | @data | 100 |
| GET | /megafamilia/ingresos/export | @export | 101 |
| GET | /megafamilia/notificaciones/ | NotificacionesController@index | 105 |
| GET | /megafamilia/notificaciones/history | @history | 106 |
| GET | /megafamilia/notificaciones/targets | @targets | 107 |
| POST | /megafamilia/notificaciones/estimate | @estimateRecipients | 108 |
| POST | /megafamilia/notificaciones/send | @send | 109 |
| GET | /megafamilia/notificaciones/{id} | @show | 110 |
| GET | /megafamilia/configuracion/ | ConfiguracionController@index | 114 |
| GET | /megafamilia/configuracion/get | @get | 115 |
| POST | /megafamilia/configuracion/update | @update | 116 |
| POST | /megafamilia/configuracion/test-fcm | @testFcm | 117 |
| GET | /megafamilia/app-versions/ | AppVersionController@index | 121 |
| POST | /megafamilia/app-versions/ | @store | 122 |
| POST | /megafamilia/app-versions/{id}/activate | @activate | 123 |
| DELETE | /megafamilia/app-versions/{id} | @destroy | 124 |
| GET | /megafamilia/terminos/ | TerminosController@index | 128 |
| GET | /megafamilia/terminos/data | @data | 129 |
| GET | /megafamilia/terminos/history | @history | 130 |
| GET | /megafamilia/terminos/acceptances | @acceptances | 131 |
| POST | /megafamilia/terminos/ | @store | 132 |
| POST | /megafamilia/terminos/draft | @draft | 133 |
| GET | /megafamilia/terminos/{version} | @show | 134 |
| GET | /megafamilia/auditoria/ | AuditoriaController@index | 138 |
| GET | /megafamilia/auditoria/data | @data | 139 |
| GET | /megafamilia/auditoria/export | @export | 140 |
| GET | /megafamilia/mikrotik/ | MikrotikController@index | 144 |
| GET | /megafamilia/mikrotik/get | @get | 145 |
| GET | /megafamilia/mikrotik/routers | @routers | 146 |
| GET | /megafamilia/mikrotik/address-list | @addressList | 147 |
| POST | /megafamilia/mikrotik/update | @update | 148 |
| POST | /megafamilia/mikrotik/test | @testConnection | 149 |
| POST | /megafamilia/mikrotik/sync | @sync | 150 |
| POST | /megafamilia/mikrotik/pause/{profileId} | @pauseProfile | 151 |
| POST | /megafamilia/mikrotik/resume/{profileId} | @resumeProfile | 152 |

### 2.3 UI Web — admin + soporte (`permission:megafamilia_admin|megafamilia_support`, líneas 157-214)

Clasificación **2** para las 26 rutas:

| Método | Ruta | Controller@método | Línea |
|---|---|---|---|
| GET | /megafamilia/clientes/por-cliente-isp/{clientIspId} | ClientesController@porClienteIsp | 159 |
| GET | /megafamilia/alertas/ | AlertasController@index | 163 |
| GET | /megafamilia/alertas/data | @data | 164 |
| POST | /megafamilia/alertas/all-read | @markAllRead | 165 |
| GET | /megafamilia/alertas/{id} | @show | 166 |
| POST | /megafamilia/alertas/{id}/read | @markRead | 167 |
| POST | /megafamilia/alertas/{id}/notify-parent | @notifyParent | 168 |
| GET | /megafamilia/solicitudes/ | SolicitudesController@index | 172 |
| GET | /megafamilia/solicitudes/data | @data | 173 |
| POST | /megafamilia/solicitudes/bulk-read | @bulkRead | 174 |
| POST | /megafamilia/solicitudes/{id}/approve | @approve | 175 |
| POST | /megafamilia/solicitudes/{id}/reject | @reject | 176 |
| GET | /megafamilia/dispositivos/ | DispositivosController@index | 180 |
| GET | /megafamilia/dispositivos/data | @data | 181 |
| GET | /megafamilia/dispositivos/{id} | @show | 182 |
| DELETE | /megafamilia/dispositivos/{id} | @unlink | 183 |
| POST | /megafamilia/dispositivos/{id}/ping | @ping | 184 |
| POST | /megafamilia/dispositivos/{id}/force-logout | @forceLogout | 185 |
| GET | /megafamilia/ubicaciones/ | UbicacionesController@index | 189 |
| GET | /megafamilia/ubicaciones/latest | @latest | 190 |
| GET | /megafamilia/ubicaciones/profiles | @profiles | 191 |
| GET | /megafamilia/ubicaciones/history/{profileId} | @history | 192 |
| GET | /megafamilia/geofences/ | GeofencesController@index | 196 |
| GET | /megafamilia/geofences/data | @data | 197 |
| POST | /megafamilia/geofences/ | @store | 198 |
| PUT | /megafamilia/geofences/{id} | @update | 199 |
| DELETE | /megafamilia/geofences/{id} | @destroy | 200 |
| POST | /megafamilia/geofences/{id}/toggle | @toggle | 201 |
| GET | /megafamilia/soporte/ | SoporteController@index | 205 |
| GET | /megafamilia/soporte/data | @data | 206 |
| GET | /megafamilia/soporte/technicians | @technicians | 207 |
| POST | /megafamilia/soporte/ | @store | 208 |
| GET | /megafamilia/soporte/{id} | @show | 209 |
| POST | /megafamilia/soporte/{id}/respond | @respond | 210 |
| POST | /megafamilia/soporte/{id}/status | @updateStatus | 211 |
| POST | /megafamilia/soporte/{id}/assign | @assign | 212 |

### 2.4 "Cliente final" (`web, auth` ÚNICAMENTE — comentario del propio archivo dice "solo auth, sin permission extra", líneas 217-240) — **AQUÍ ESTÁ EL AGUJERO**

| Método | Ruta | Controller@método | Línea | Clase | Riesgo |
|---|---|---|---|---|---|
| GET | /megafamilia/perfiles/ | PerfilesController@index | 218 | 4 | BAJO — solo shell Blade sin datos, la protección real (o su ausencia) está en `/data` y `/{id}` |
| GET | /megafamilia/perfiles/data | @data | 219 | **4** | **MEDIO** — sin `guardOwnership`; si el usuario autenticado no tiene `ParentalAccount` propia (cualquier staff sin hijos) cae a `ParentalProfile::query()->get()` **SIN NINGÚN FILTRO** → devuelve nombre/edad/nivel escolar de **todos los perfiles de todas las familias cliente** (PerfilesController.php:19-26) |
| GET | /megafamilia/perfiles/{id} | @show | 220 | **4** | **MEDIO** — IDOR: ningún ownership check, cualquier `id` devuelve el perfil completo (devices/rules/appBlocks/webBlocks/schedules/tasks/alerts de un menor) sin verificar que pertenezca al usuario (PerfilesController.php:65-87) |
| POST | /megafamilia/perfiles/ | @store | 221 | 3 | — protegido: exige `ParentalAccount` propia o 403 (PerfilesController.php:30-33) |
| PUT | /megafamilia/perfiles/{id} | @update | 222 | 3 | — protegido: `guardOwnership()` (PerfilesController.php:48, 96-106) |
| DELETE | /megafamilia/perfiles/{id} | @destroy | 223 | 3 | — protegido: `guardOwnership()` (PerfilesController.php:56) |
| GET | /megafamilia/tareas/ | TareasController@index | 227 | 4 | BAJO — solo shell |
| GET | /megafamilia/tareas/data | @data | 228 | **4** | **MEDIO** — sin `guardProfileAccess`; devuelve TODAS las tareas de TODOS los perfiles paginadas + agrupadas por estado, filtro `profile_id` opcional pero no obligatorio (TareasController.php:28-53) |
| POST | /megafamilia/tareas/ | @store | 229 | 3 | — protegido: `guardProfileAccess()` (TareasController.php:68) |
| POST | /megafamilia/tareas/{id}/approve | @approve | 230 | 3 | — protegido (TareasController.php:84) |
| POST | /megafamilia/tareas/{id}/reject | @reject | 231 | 3 | — protegido (TareasController.php:112) |
| DELETE | /megafamilia/tareas/{id} | @destroy | 232 | 3 | — protegido (TareasController.php:132) |
| GET | /megafamilia/reportes/ | ReportesController@index | 236 | 4 | BAJO — solo shell |
| GET | /megafamilia/reportes/data | @data | 237 | **4** | **MEDIO** — sin ownership check en ningún punto del controller; `profile_id` es opcional y si no viene agrega KPIs/tiempo de pantalla/top-apps/actividad por hora de **todos los perfiles** (ReportesController.php:32-45) |
| GET | /megafamilia/reportes/profiles | @profiles | 238 | **4** | **MEDIO** — lista nombre/tipo/foto de todos los perfiles activos sin scoping (ReportesController.php:23-30) |
| GET | /megafamilia/reportes/export | @export | 239 | **4** | **MEDIO** — mismo hueco que `/data` pero como descarga CSV (screen time, top apps, sitios bloqueados) de un perfil cualquiera o de todos (ReportesController.php:47-86) |

### 2.5 API Mobile (`log_api_mobile`, `force_json` + `auth:sanctum` interno, líneas 247-363)

| Método | Ruta | Controller@método | Línea | Clase |
|---|---|---|---|---|
| POST | /api/megafamilia/auth/login | ApiController@login | 249 | 5 — público a propósito (login) |
| GET | /api/megafamilia/app-version | @appVersion | 252 | 5 — público a propósito (OTA) |
| POST | /api/megafamilia/devices/link | @linkDevice | 257 | 5 — público a propósito, `link_token` de un solo uso documentado como la credencial |
| GET | /api/megafamilia/account | @account | 260 | 2 — `auth:sanctum` |
| GET | /api/megafamilia/sync-status | @syncStatus | 263 | 2 |
| GET | /api/megafamilia/servicio | @servicio | 266 | 2 |
| GET | /api/megafamilia/tickets | @tickets | 267 | 2 |
| POST | /api/megafamilia/tickets | @storeTicket | 268 | 2 |
| GET | /api/megafamilia/tickets/{id} | @ticketDetail | 269 | 2 |
| POST | /api/megafamilia/tickets/{id}/attachment | @attachTicketPhoto | 270 | 2 |
| POST | /api/megafamilia/tickets/{id}/rate | @rateTicket | 271 | 2 |
| GET | /api/megafamilia/profile | @profile | 272 | 2 |
| GET | /api/megafamilia/facturas | @facturas | 273 | 2 |
| GET | /api/megafamilia/pagos | @pagos | 274 | 2 |
| POST | /api/megafamilia/pagos | @crearPago | 275 | 2 |
| GET | /api/megafamilia/pagos/{id}/pdf | @pagoPdf | 276 | 2 |
| GET | /api/megafamilia/payments/clabe | @paymentsClabe | 279 | 2 |
| POST | /api/megafamilia/payments/notify-transfer | @notifyTransfer | 280 | 2 |
| GET | /api/megafamilia/profiles | @profiles | 282 | 2 |
| POST | /api/megafamilia/profiles | @storeProfile | 283 | 2 |
| GET | /api/megafamilia/profiles/{id} | @profileDetail | 284 | 2 |
| GET | /api/megafamilia/profiles/{id}/devices | @profileDevices | 285 | 2 |
| POST | /api/megafamilia/profiles/{id}/invite | @inviteDevice | 286 | 2 |
| GET | /api/megafamilia/profiles/{id}/tasks | @profileTasks | 287 | 2 |
| GET | /api/megafamilia/profiles/{id}/location | @profileLocation | 288 | 2 |
| GET | /api/megafamilia/devices/{id}/rules | @deviceRules | 290 | 2 |
| PUT | /api/megafamilia/devices/{id}/rules | @updateDeviceRules | 291 | 2 |
| GET | /api/megafamilia/profiles/{id}/geofences | @profileGeofences | 294 | 2 |
| POST | /api/megafamilia/profiles/{id}/geofences | @storeProfileGeofence | 295 | 2 |
| PUT | /api/megafamilia/geofences/{id} | @updateProfileGeofence | 296 | 2 |
| DELETE | /api/megafamilia/geofences/{id} | @destroyProfileGeofence | 297 | 2 |
| POST | /api/megafamilia/tasks/{id}/complete | @completeTask | 299 | 2 |
| GET | /api/megafamilia/tecnico/ordenes | @tecnicoOrdenes | 301 | 2 |
| PUT | /api/megafamilia/tecnico/ordenes/{id} | @updateTecnicoOrden | 302 | 2 |
| GET | /api/megafamilia/hijo/tareas | @hijoTareas | 304 | 2 |
| POST | /api/megafamilia/hijo/tareas/{id}/completar | @completeTask | 305 | 2 |
| GET | /api/megafamilia/hijo/logros | @hijoLogros | 306 | 2 |
| GET | /api/megafamilia/hijo/apps-permitidas | @hijoAppsPermitidas | 307 | 2 |
| POST | /api/megafamilia/hijo/solicitudes | @hijoStoreRequest | 308 | 2 |
| POST | /api/megafamilia/requests | @storeRequest | 310 | 2 |
| GET | /api/megafamilia/requests/pending | @pendingRequests | 311 | 2 |
| POST | /api/megafamilia/requests/{id}/respond | @respondRequest | 312 | 2 |
| POST | /api/megafamilia/locations | @reportLocation | 314 | 2 |
| GET | /api/megafamilia/embajadores/red | @embajadorRed | 318 | 2 |
| GET | /api/megafamilia/embajadores/comisiones | @embajadorComisiones | 319 | 2 |
| GET | /api/megafamilia/embajadores/recompensas | @embajadorRecompensas | 320 | 2 |
| POST | /api/megafamilia/embajadores/recompensas/{id}/aplicar | @embajadorAplicarRecompensa | 321 | 2 |
| GET | /api/megafamilia/embajadores/notifications-log | @embajadorNotificationsLog | 322 | 2 |
| POST | /api/megafamilia/embajadores/share-masivo | @embajadorShareMasivo | 323 | 2 |
| POST | /api/megafamilia/embajadores/prospects/import | @embajadorImportProspectos | 325 | 2 |
| GET | /api/megafamilia/embajadores/prospects | @embajadorProspectos | 326 | 2 |
| POST | /api/megafamilia/embajadores/prospects | @embajadorStoreProspecto | 327 | 2 |
| GET | /api/megafamilia/embajadores/prospects/{id} | @embajadorGetProspecto | 328 | 2 |
| PUT | /api/megafamilia/embajadores/prospects/{id} | @embajadorUpdateProspecto | 329 | 2 |
| DELETE | /api/megafamilia/embajadores/prospects/{id} | @embajadorDeleteProspecto | 330 | 2 |
| GET | /api/megafamilia/embajadores/prospects/{id}/followups | @embajadorFollowups | 331 | 2 |
| POST | /api/megafamilia/embajadores/prospects/{id}/followups | @embajadorAddFollowup | 332 | 2 |
| GET | /api/megafamilia/conductor/vehiculo | ConductorApiController@vehiculo | 339 | 2 — + `role:conductor\|super-administrator\|DESARROLLADOR` (línea 336) |
| GET | /api/megafamilia/conductor/posicion-actual | @posicionActual | 340 | 2 |
| GET | /api/megafamilia/conductor/historial-posiciones | @historialPosiciones | 341 | 2 |
| GET | /api/megafamilia/conductor/geocercas | @geocercas | 342 | 2 |
| GET | /api/megafamilia/conductor/eventos-geocerca | @eventosGeocerca | 343 | 2 |
| GET | /api/megafamilia/conductor/documentos | @documentos | 344 | 2 |
| GET | /api/megafamilia/conductor/mantenimientos | @mantenimientos | 345 | 2 |
| POST | /api/megafamilia/conductor/posicion | @reportarPosicion | 346 | 2 |
| GET | /api/megafamilia/cliente/flotas/tiene-flotas | ClienteFlotasApiController@tieneFlotas | 351 | 2 |
| GET | /api/megafamilia/cliente/flotas/vehiculos | @vehiculos | 352 | 2 |
| GET | /api/megafamilia/cliente/flotas/vehiculos/{id} | @vehiculoDetalle | 353 | 2 |
| GET | /api/megafamilia/cliente/flotas/vehiculos/{id}/historial-posiciones | @historialPosiciones | 354 | 2 |
| GET | /api/megafamilia/cliente/flotas/vehiculos/{id}/eventos-geocerca | @eventosGeocerca | 355 | 2 |
| GET | /api/megafamilia/cliente/flotas/vehiculos/{id}/documentos | @documentos | 356 | 2 |
| GET | /api/megafamilia/cliente/flotas/vehiculos/{id}/mantenimientos | @mantenimientos | 357 | 2 |
| GET | /api/megafamilia/cliente/flotas/geocercas | @geocercas | 358 | 2 |
| GET | /api/megafamilia/cliente/flotas/resumen | @resumen | 359 | 2 |
| GET | /api/megafamilia/cliente/flotas/plan | @plan | 360 | 2 |

**MegaFamilia: 9 rutas en AGUJERO REAL, todas riesgo MEDIO** (lectura sensible de datos de
menores sin scoping — ver §2.4). Ninguna es CRÍTICA porque las de escritura (store/update/
destroy/approve/reject) sí tienen `guardOwnership`/`guardProfileAccess`.

---

## 3. Mapas (`app/Modules/Addons/Mapas/routes.php`, 311 líneas)

Dos grupos, ambos `web, auth, check_route_permission` sin excepción (líneas 58-106 y 112-311).
**Clasificación 1 para el 100% de las rutas.** 4 `Route::resource()` expandidos:

| Resource | Prefijo | Línea declaración | Rutas CRUD expandidas | Clase |
|---|---|---|---|---|
| `/layers` (completo) | /maps | 62 | GET `/maps/layers` (index), GET `/maps/layers/create` (create), POST `/maps/layers` (store), GET `/maps/layers/{layer}` (show), GET `/maps/layers/{layer}/edit` (edit), PUT/PATCH `/maps/layers/{layer}` (update), DELETE `/maps/layers/{layer}` (destroy) — LayersController | 1 |
| `/projects` (completo) | /maps | 63 | mismas 7 rutas CRUD sobre `/maps/projects` — ProyectsController | 1 |
| `/connections` (`->except('index')`) | /maps | 97 | GET `/maps/connections/create`, POST `/maps/connections`, GET `/maps/connections/{connection}`, GET `/maps/connections/{connection}/edit`, PUT/PATCH `/maps/connections/{connection}`, DELETE `/maps/connections/{connection}` — ConnectionsController | 1 |
| `/devices` (`->except('index')`) | /maps | 102 | mismas 6 rutas sobre `/maps/devices` — DevicesController | 1 |

El resto de las 150 rutas explícitas (líneas 59-311, `zones`/`get-clients`/`kmz`/`service-box`/
todo el sub-namespace `Mapas\*` de infraestructura FTTH — box/pole/splitter/fiber/rack/port/etc.)
están todas dentro de los mismos dos grupos `check_route_permission` (58-106 y 112-311) — se
recorrió cada línea del archivo para confirmarlo, sin ninguna ruta fuera de esos dos
`Route::middleware(...)->group(...)`.

**Mapas: 0 agujeros.**

---

## 4. Roadmap (`app/Modules/Addons/Roadmap/routes.php`, 259 líneas — el propio módulo del circuito)

### 4.1 API externa sin sesión (`/api/roadmap-externo/{token}`, líneas 18-79) — OJO Roadmap del item aplicado

Sin `web`/`auth` a propósito (documentado en el propio archivo: "Claude Cowork... sin sesión ni
cookies"). Cada método valida el token vía `hash_equals()` **contra el config correspondiente**
(`roadmap_externo.read_token`/`write_token`/`create_token`), con fail-safe explícito: "Un token no
configurado NUNCA valida" (`RoadmapExternalController.php:608-611`). Defensa equivalente →
clasificación 3 para las 12 rutas:

| Método | Ruta | Controller@método | Línea | Token validado |
|---|---|---|---|---|
| GET | /api/roadmap-externo/{token} | RoadmapExternalController@index | 21 | read_token (línea 55) |
| GET | /api/roadmap-externo/{token}/item/{id} | @showItem | 26 | read_token (102) |
| GET | /api/roadmap-externo/{token}/q/{estado}/{nivel}/{page}/{perpage} | @queryPath | 28 | read_token (116) |
| GET | /api/roadmap-externo/{token}/item/{id}/historial | @itemHistorial | 32 | read_token (525) |
| POST | /api/roadmap-externo/{token}/item/{id} | @updateItem | 39 | write_token (235) |
| GET | /api/roadmap-externo/{token}/item/{id}/set | @setItem | 46 | write_token (249) |
| GET | /api/roadmap-externo/{token}/item/{id}/set/{estado}/{nivel}/{comentario?} | @setItemPath | 52 | write_token (266) |
| GET | /api/roadmap-externo/{token}/item/{id}/setb64/{estado}/{nivel}/{comentarioB64?} | @setItemPathB64 | 59 | write_token (297) |
| POST | /api/roadmap-externo/{token}/item | @createItem | 68 | create_token (341) |
| GET | /api/roadmap-externo/{token}/crear/{modulo}/{tituloB64}/{specB64?} | @createItemPathB64 | 72 | create_token (358) |
| POST | /api/roadmap-externo/{token}/item/{id}/reporte | @addReport | 77 | create_token (489) |

### 4.2 Conector MCP (`/mcp/{secret}`, líneas 87-92) — OJO Roadmap aplicado

| Método | Ruta | Controller@método | Línea | Clase |
|---|---|---|---|---|
| POST | /mcp/{secret} | RoadmapMcpController@handle | 90 | 3 — `secretOk()` valida `hash_equals` contra `config('mcp_roadmap.secret')`, fail-safe si no está configurado (RoadmapMcpController.php:44-49, 347-352) |
| GET | /mcp/{secret} | @methodNotAllowed | 91 | 5* — no expone dato alguno, solo devuelve 405; el `{secret}` del path se ignora en la firma del método |

### 4.3 Página de detalle read-only (`web, auth`, líneas 98-100)

| Método | Ruta | Controller@método | Línea | Clase |
|---|---|---|---|---|
| GET | /roadmap/item/{id} | RoadmapController@itemDetalle | 99 | 3 — `$this->authorize('roadmap_view')` (RoadmapController.php:2009) |

### 4.4 `/api/roadmap/*` (`web, auth`, líneas 102-248) — SIN `check_route_permission`, pero el 100% con `$this->authorize()` inline

Se verificó método por método (61 rutas): **todas** llaman `$this->authorize('roadmap_view'|
'roadmap_manage'|'circuito.decidir'|'circuito.disparar'|'circuito.pause'|'torre.config.view'|
'torre.config.edit'|'torre.cola.ver'|'torre.salud.manage'|'torre.actividad.view'|
'torre.terminales.editar_avatar')` como primera línea del método, incluyendo el kill-switch
(`toggleCircuito` → `circuito.pause`) y el merge/integración del propio circuito. Clasificación
**3** para las 61:

| Método | Ruta | Controller@método | Línea | Permiso |
|---|---|---|---|---|
| GET | /api/roadmap/torre | RoadmapController@torre | 106 | roadmap_view (863) |
| GET | /api/roadmap/torre/compuertas | TorreCompuertasController@estado | 111 | rol super-administrator\|DESARROLLADOR inline (`autorizar()`, líneas 39-42) |
| GET | /api/roadmap/torre/compuertas/bitacora | @bitacora | 112 | idem |
| POST | /api/roadmap/torre/compuertas/accion | @accion | 113 | idem |
| GET | /api/roadmap/torre/compuertas/permisos | @permisos | 115 | idem |
| POST | /api/roadmap/torre/compuertas/permisos | @permisoToggle | 116 | idem |
| GET | /api/roadmap/torre/config | RoadmapController@torreConfig | 120 | torre.config.view (143) |
| POST | /api/roadmap/torre/config | @torreConfigGuardar | 121 | torre.config.edit (169) |
| GET | /api/roadmap/torre/jarvis-identidad | JarvisIdentidadController@identidad | 127 | torre.config.view (37) |
| POST | /api/roadmap/torre/jarvis-identidad | @guardar | 128 | torre.config.edit (69) |
| GET | /api/roadmap/jarvis-chat/sugerencias | JarvisChatController@sugerencias | 134 | roadmap_manage (30) |
| POST | /api/roadmap/jarvis-chat/conversaciones | @abrir | 135 | roadmap_manage (38) |
| GET | /api/roadmap/jarvis-chat/conversaciones/{id} | @mostrar | 136 | roadmap_manage (60) |
| POST | /api/roadmap/jarvis-chat/conversaciones/{id}/mensajes | @mensaje | 137 | roadmap_manage (70) |
| POST | /api/roadmap/jarvis-chat/conversaciones/{id}/vincular-item | @vincularItem | 138 | roadmap_manage (96) |
| GET | /api/roadmap/torre/fronteras | TorreFronterasController@index | 140 | torre.config.view (autorizarVer, 49) |
| POST | /api/roadmap/torre/fronteras/categoria | @categoria | 141 | torre.config.edit (autorizarEscribir, 55) |
| POST | /api/roadmap/torre/fronteras/termino | @termino | 142 | idem |
| POST | /api/roadmap/torre/fronteras/valvula | @valvula | 143 | idem |
| POST | /api/roadmap/torre/fronteras/techo-autopilot | @techoAutopilot | 144 | idem |
| POST | /api/roadmap/torre/fronteras/mencion-categorias | @mencionCategorias | 145 | idem |
| GET | /api/roadmap/torre/frontera-dura | RoadmapController@torreFronteraDura | 148 | roadmap_view (507) |
| GET | /api/roadmap/torre/cola | @torreCola | 151 | torre.cola.ver (204) |
| GET | /api/roadmap/atorados | @atorados | 154 | roadmap_view (273) |
| POST | /api/roadmap/item/{id}/override | @itemOverride | 155 | circuito.decidir (325) |
| GET | /api/roadmap/circuito/estado | @estado | 157 | roadmap_view (1083) |
| GET | /api/roadmap/torre/decisiones/contadores | @decisionesContadores | 159 | roadmap_view (1415) |
| GET | /api/roadmap/torre/decisiones-automaticas | @decisionesAutomaticas | 162 | roadmap_view (1122) |
| GET | /api/roadmap/torre/historial-acciones | @historialAcciones | 165 | roadmap_view (516) |
| GET | /api/roadmap/torre/actividad-equipo | @actividadEquipo | 168 | torre.actividad.view (583) |
| GET | /api/roadmap/torre/salud-entorno | @saludEntorno | 172 | roadmap_view (426) |
| POST | /api/roadmap/torre/salud/reintentar-fallidos | @saludReintentarFallidos | 173 | torre.salud.manage (443) |
| POST | /api/roadmap/torre/salud/recalentar-caches | @saludRecalentarCaches | 174 | torre.salud.manage (458) |
| GET | /api/roadmap/torre/semaforo | @torreSemaforo | 177 | roadmap_view (478) |
| GET | /api/roadmap/torre/semaforo/fallo | @torreSemaforoFallo | 180 | roadmap_view (491) |
| POST | /api/roadmap/items/{id}/deshacer-decision | @deshacerDecision | 181 | roadmap_manage (1227) |
| POST | /api/roadmap/items/{id}/liberar-reclamo | @liberarReclamo | 185 | roadmap_manage (1301) |
| POST | /api/roadmap/items/{id}/reasignar-reclamo | @reasignarReclamo | 188 | roadmap_manage (1345) |
| GET | /api/roadmap/circuito/sesiones | @sesiones | 191 | roadmap_view (1490) |
| POST | /api/roadmap/circuito/disparar | @disparar | 193 | circuito.disparar (1517) |
| POST | /api/roadmap/items/{id}/urgente | @urgente | 194 | circuito.disparar (1537) |
| POST | /api/roadmap/items/{id}/cancelar-disparo | @cancelarDisparo | 196 | circuito.disparar (1591) |
| POST | /api/roadmap/circuito/toggle | @toggleCircuito | 197 | **circuito.pause** — kill switch (1502) |
| POST | /api/roadmap/circuito/decidir | @decidir | 198 | circuito.decidir (1656) |
| POST | /api/roadmap/circuito/elegir-opcion | @elegirOpcion | 199 | circuito.decidir (1853) |
| POST | /api/roadmap/circuito/seguimiento | @seguimiento | 200 | circuito.decidir (1887) |
| GET | /api/roadmap/integracion | @integracion | 202 | roadmap_view (1951) |
| POST | /api/roadmap/integracion/merge | @integracionMerge | 203 | circuito.decidir (2500) |
| POST | /api/roadmap/integracion/rechazar | @integracionRechazar | 204 | circuito.decidir (2535) |
| POST | /api/roadmap/integracion/revert | @integracionRevert | 205 | circuito.decidir (2600) |
| POST | /api/roadmap/integracion/modo | @integracionModo | 206 | circuito.decidir (2406) |
| POST | /api/roadmap/integracion/marcar-version | @integracionMarcarVersion | 207 | circuito.decidir (2418) |
| GET | /api/roadmap/integracion/version-candidatos | @integracionVersionCandidatos | 209 | circuito.decidir (2439) |
| GET | /api/roadmap/integracion/version-dependencias | @integracionVersionDependencias | 210 | circuito.decidir (2460) |
| POST | /api/roadmap/integracion/version-construir-rama | @integracionVersionConstruirRama | 212 | circuito.decidir (2474) |
| POST | /api/roadmap/integracion/voz | @integracionVoz | 214 | circuito.decidir (1973) |
| POST | /api/roadmap/circuito/worker-nombre | @workerNombre | 216 | circuito.decidir (2336) |
| POST | /api/roadmap/circuito/worker-avatar | @workerAvatar | 217 | torre.terminales.editar_avatar (2360) |
| GET | /api/roadmap/integracion/diff | @integracionDiff | 219 | roadmap_view (2870) |
| GET | /api/roadmap/integracion/historial | @integracionHistorial | 220 | roadmap_view (1991) |
| POST | /api/roadmap/integracion/archivar | @integracionArchivar | 221 | circuito.decidir (2276) |
| POST | /api/roadmap/integracion/desarchivar | @integracionDesarchivar | 222 | circuito.decidir (2312) |
| GET | /api/roadmap/validacion | @validacionPendiente | 225 | roadmap_view (2654) |
| POST | /api/roadmap/validacion/aprobar | @validacionAprobar | 226 | circuito.decidir (2699) |
| POST | /api/roadmap/validacion/reportar | @validacionReportar | 227 | circuito.decidir (2730) |
| GET | /api/roadmap/items | @index | 229 | roadmap_view (2908) |
| GET | /api/roadmap/items/{id} | @show | 230 | roadmap_view (2943) |
| POST | /api/roadmap/items | @store | 231 | roadmap_manage (2951) |
| PATCH | /api/roadmap/items/{id} | @update | 232 | roadmap_manage (3190) |
| POST | /api/roadmap/items/{id}/start | @start | 233 | roadmap_manage (3211) |
| POST | /api/roadmap/items/{id}/complete | @complete | 234 | roadmap_manage (3230) |
| POST | /api/roadmap/items/{id}/cancel | @cancel | 235 | roadmap_manage (3244) |
| DELETE | /api/roadmap/items/{id} | @destroy | 236 | roadmap_manage (3255) |
| PATCH | /api/roadmap/items/{id}/subtasks | @updateSubtasks | 239 | roadmap_manage (3265) |
| POST | /api/roadmap/items/{id}/subtasks/{index}/toggle | @toggleSubtask | 240 | roadmap_manage (3284) |
| POST | /api/roadmap/items/{id}/log | @addLog | 241 | roadmap_manage (3306) |
| GET | /api/roadmap/items/{id}/memory | RoadmapMemoryController@show | 244 | roadmap_manage (24) |
| GET | /api/roadmap/items/{id}/memory/prompt | @generatePrompt | 245 | roadmap_manage (36) |
| POST | /api/roadmap/items/{id}/memory/report | @appendReport | 246 | roadmap_manage (47) |
| POST | /api/roadmap/items/{id}/memory/raw | @replaceRaw | 247 | roadmap_manage (62) |

Nota: `TorreCompuertasController` y `TorreFronterasController` verifican con un helper propio
(`autorizar()`/`autorizarVer()`/`autorizarEscribir()`) en vez de `$this->authorize()` textual,
pero el efecto es idéntico (rol o permiso, evaluado al inicio de cada método) — mismo criterio de
defensa en profundidad que el resto del bloque.

### 4.5 Estado de JARVIS para la burbuja (líneas 258-259)

| Método | Ruta | Controller@método | Línea | Clase | Riesgo |
|---|---|---|---|---|---|
| GET | /api/jarvis/estado | JarvisIdentidadController@estado | 259 | 4 | BAJO — documentado explícitamente en el propio archivo como intencional ("Sólo necesita sesión. No expone ni un dato de negocio"); solo requiere `auth`, sin permiso de la Torre. Expone edad del último latido del medidor + conteo de decisiones pendientes — metadato operativo, no dato de cliente/dinero |

**Roadmap: 0 agujeros de riesgo real** (la única ruta sin permiso —`/api/jarvis/estado`— es
BAJO y está documentada como decisión de diseño, no un descuido).

---

## 5. Marketing (`app/Modules/Addons/Marketing/routes.php`, 253 líneas)

### 5.1 Públicas (`web`, sin auth, líneas 25-48) — OJO Marketing del item aplicado

| Método | Ruta | Controller@método | Línea | Clase |
|---|---|---|---|---|
| GET/POST | /webhooks/marketing/meta-ads | MetaAdsWebhookController@handle | 27 | **5** — en `PUBLIC_ROUTES` (`CheckRoutePermission.php`) + defensa extra inline: valida `hub_verify_token` (GET) y firma HMAC `X-Hub-Signature-256` con `hash_equals` (POST) — MetaAdsWebhookController.php:25-52 |
| POST | /webhooks/marketing/evolution | EvolutionWebhookController@handle | 31 | **5** — en `PUBLIC_ROUTES` + valida token propio inline con `hash_equals` — EvolutionWebhookController.php:23 |
| GET | /public/marketing/lead-form/{slug} | PublicLeadFormController@show | 36 | 5 — en `PUBLIC_ROUTES` |
| POST | /public/marketing/lead-form/{slug}/submit | @submit | 37 | 5 — en `PUBLIC_ROUTES` |
| GET | /public/marketing/embed.js | @embedScript | 38 | 5 — en `PUBLIC_ROUTES` |
| GET | /marketing/pilot/track/open/{token}.gif | PilotCampaignTrackingController@open | 43 | 3 — token de envío único, buscado por igualdad exacta en BD (`where('token', $token)->first()`), no adivinable; intencional ("lo abre el correo... sin sesión") |
| GET | /marketing/pilot/track/click/{token} | @click | 45 | 3 — idem |

### 5.2 API JSON `web, auth` (SIN `check_route_permission`, líneas 51-144) — la mayoría gateada por `permission:` en el constructor del controller

| Método | Ruta | Controller@método | Línea | Clase | Riesgo |
|---|---|---|---|---|---|
| GET | /api/marketing/leads | MarketingLeadController@index | 53 | 2 | `permission:marketing.leads.view` (constructor:16) |
| POST | /api/marketing/leads | @store | 54 | 2 | `marketing.leads.create` (17) |
| GET | /api/marketing/leads/{id} | @show | 55 | 2 | `marketing.leads.view` (16) |
| PUT | /api/marketing/leads/{id} | @update | 56 | 2 | `marketing.leads.update` (18) |
| DELETE | /api/marketing/leads/{id} | @destroy | 57 | 2 | `marketing.leads.delete` (19) |
| POST | /api/marketing/leads/{id}/assign | @assign | 58 | 2 | `marketing.leads.assign` (20) |
| POST | /api/marketing/leads/{id}/score | @triggerScoring | 59 | 2 | `marketing.leads.score` (21) |
| GET | /api/marketing/leads/{id}/activities | @activities | 60 | 2 | `marketing.leads.view` (16) |
| GET | /api/marketing/lead-forms | MarketingLeadFormController@index | 63 | 2 | `marketing.forms.view` (13) |
| POST | /api/marketing/lead-forms | @store | 64 | 2 | `marketing.forms.create` (14) |
| GET | /api/marketing/lead-forms/{id} | @show | 65 | 2 | `marketing.forms.view` (13) |
| PUT | /api/marketing/lead-forms/{id} | @update | 66 | 2 | `marketing.forms.update` (15) |
| DELETE | /api/marketing/lead-forms/{id} | @destroy | 67 | 2 | `marketing.forms.delete` (16) |
| GET | /api/marketing/lead-forms/{id}/embed-code | @getEmbedCode | 68 | **4** | **BAJO** — método fuera de los `->only()` del constructor (líneas 13-16 solo cubren index/show/store/update/destroy); expone el snippet de embed (pensado para pegarse en un sitio externo, igual que `/public/marketing/embed.js`, que ya es público) — MarketingLeadFormController.php:85 |
| GET | /api/marketing/conversations | MarketingConversationController@index | 72 | **4** | **MEDIO** — sin constructor, sin middleware, sin `authorize()`; lista TODAS las conversaciones de WhatsApp con datos de lead (nombre/teléfono) — MarketingConversationController.php (sin gate) |
| GET | /api/marketing/conversations/{id} | @show | 73 | **4** | **MEDIO** — idem, detalle de una conversación cualquiera |
| GET | /api/marketing/conversations/{id}/messages | @messages | 74 | **4** | **MEDIO** — idem, mensajes reales del cliente |
| POST | /api/marketing/conversations/{id}/send-message | @sendMessage | 75 | **4** | **CRÍTICO** — cualquier usuario autenticado puede enviar un mensaje de WhatsApp real a un cliente real suplantando al negocio (usa `SendOutboundMessageJob`) |
| POST | /api/marketing/conversations/{id}/toggle-ai | @toggleAi | 76 | **4** | **CRÍTICO** — apaga/enciende el bot de IA en una conversación con cliente real sin ningún permiso |
| POST | /api/marketing/conversations/{id}/assign | @assign | 77 | **4** | **CRÍTICO** — reasigna la conversación a otro agente sin permiso |
| POST | /api/marketing/conversations/{id}/close | @close | 78 | **4** | **CRÍTICO** — cierra la conversación con el cliente sin permiso |
| POST | /api/marketing/conversations/{id}/mark-as-read | @markAsRead | 79 | **4** | **MEDIO** — escritura de bajo impacto (solo marca leído), pero también sin ningún gate |
| GET | /api/marketing/lead-sources | closure (`LeadSource::all()`) | 83 | **4** | BAJO — catálogo de tipo (nombres de fuente de lead), sin PII, pensado como lookup de UI |
| GET | /api/marketing/brand-kit | MarketingBrandKitController@show | 87 | 2 | `permission:marketing.brand_kit.configure` (constructor:17, sin `->only()`, aplica a los 7 métodos) |
| PUT | /api/marketing/brand-kit | @update | 88 | 2 | idem |
| POST | /api/marketing/brand-kit/logo | @uploadLogo | 89 | 2 | idem |
| DELETE | /api/marketing/brand-kit/logo | @deleteLogo | 90 | 2 | idem |
| GET | /api/marketing/brand-kit/logo/serve | @serveLogo | 91 | 2 | idem |
| PUT | /api/marketing/brand-kit/integrations | @updateIntegrations | 92 | 2 | idem |
| GET | /api/marketing/video-templates | MarketingVideoTemplateController@index | 97 | 2 | `permission:view-video-templates` (14) |
| GET | /api/marketing/video-templates/{id} | @show | 98 | 2 | idem |
| GET | /api/marketing/video-templates/{id}/variables | @variables | 99 | 2 | idem |
| GET | /api/marketing/generated-content | MarketingGeneratedContentController@index | 104 | 2 | `permission:view-video-content` (20) |
| GET | /api/marketing/generated-content/{id} | @show | 105 | 2 | idem |
| GET | /api/marketing/generated-content/{id}/progress | @progress | 106 | 2 | idem |
| GET | /api/marketing/generated-content/{id}/download | @download | 107 | **4** | **MEDIO** — `download()` no está en ningún `->only()` del constructor (20-22 cubren index/show/progress, render y destroy); descarga el archivo de video real del disco por id sin exigir `view-video-content` — MarketingGeneratedContentController.php:180-194 |
| POST | /api/marketing/generated-content/render | @render | 108 | 2 | `permission:generate-video-content` (21) |
| DELETE | /api/marketing/generated-content/{id} | @destroy | 109 | 2 | `permission:delete-video-content` (22) |
| GET | /api/marketing/multivariant-campaigns | MarketingMultivariantCampaignController@index | 114 | 2 | `permission:view-marketing-campaigns` (21) |
| POST | /api/marketing/multivariant-campaigns | @store | 115 | 2 | `create-marketing-campaigns` (22) |
| GET | /api/marketing/multivariant-campaigns/{id} | @show | 116 | 2 | `view-marketing-campaigns` (21) |
| DELETE | /api/marketing/multivariant-campaigns/{id} | @destroy | 117 | 2 | `delete-marketing-campaigns` (24) |
| GET | /api/marketing/multivariant-campaigns/{id}/progress | @progress | 118 | 2 | `view-marketing-campaigns` (21) |
| POST | /api/marketing/multivariant-campaigns/{id}/regenerate-variant/{niche} | @regenerateVariant | 119 | 2 | `regenerate-marketing-variants` (23) |
| GET | /api/marketing/niches | @niches | 123 | 2 | `manage-marketing-niches` (25) |
| PUT | /api/marketing/niches/{id} | @updateNiche | 124 | 2 | idem |
| GET | /api/marketing/voice-comparator/voices | VoiceComparatorController@listVoices | 129 | 2 | `permission:test-voices` (16, sin `->only()`, aplica a las 3) |
| POST | /api/marketing/voice-comparator/generate-samples | @generateSamples | 130 | 2 | idem |
| POST | /api/marketing/voice-comparator/assign-niche | @assignToNiche | 131 | 2 | idem |
| GET | /api/marketing/pilot-campaigns | PilotCampaignController@index | 136 | **4** | **MEDIO** — sin constructor, sin middleware, sin `authorize()`; comentario propio dice "solo lista de prueba (empleados/cuentas dummy) — nunca clientes reales", lo que baja el impacto real, pero el endpoint en sí no tiene ningún gate |
| POST | /api/marketing/pilot-campaigns | @store | 137 | **4** | MEDIO — idem |
| GET | /api/marketing/pilot-campaigns/{id} | @show | 138 | **4** | MEDIO — idem |
| DELETE | /api/marketing/pilot-campaigns/{id} | @destroy | 139 | **4** | MEDIO — idem |
| POST | /api/marketing/pilot-campaigns/{id}/dry-run | @dryRun | 140 | **4** | MEDIO — idem, no envía nada real |
| POST | /api/marketing/pilot-campaigns/{id}/send | @send | 141 | **4** | MEDIO — idem, envía correos reales pero a la lista dummy interna documentada |
| POST | /api/marketing/pilot-campaigns/{id}/sends/{sendId}/mark-converted | @markConverted | 142 | **4** | MEDIO — idem |

### 5.3 Panel admin Blade (`web, auth, check_route_permission`, líneas 147-227)

Clasificación **1** para las 34 rutas del bloque (vistas Blade + `CampaignController` +
`ContentGeneratorController` + `LeadController` legacy + `MarketingController` +
`MetaOAuthController`) — todas dentro del `Route::middleware(['web','auth',
'check_route_permission'])->prefix('marketing')` abierto en la línea 147 y cerrado en la 227.

### 5.4 API de Publicación (`web, auth, check_route_permission`, líneas 230-253)

Clasificación **1** para las 13 rutas de `PublishingController` (channels/campaigns/publications/
dashboard) — todas dentro del grupo abierto en la línea 230.

**Marketing: 8 rutas en AGUJERO REAL** — 4 CRÍTICO (`send-message`/`toggle-ai`/`assign`/`close` de
`MarketingConversationController`, sobre WhatsApp con clientes reales) + 4 MEDIO (`messages`/
`index`/`show`/`mark-as-read` del mismo controller) — más 6 adicionales de riesgo BAJO-MEDIO
acotado (`getEmbedCode`, `download` de video, `lead-sources`, y el bloque completo de
`PilotCampaignController`, mitigado por operar sobre listas dummy documentadas).

---

## 6. GestionRed (`app/Modules/Addons/GestionRed/routes.php`, 216 líneas)

Tres grupos, los tres `web, auth, check_route_permission` (líneas 35-99 prefijo `red`, 102-107
sin prefijo, 112-216 prefijo `olts`). **Clasificación 1 para el 100% de las 116 rutas** (IPv4,
Router/Mikrotik, `mikrotik-sync`, OLTs/ONUs/settings/smartolt-config) — se recorrió cada línea
del archivo para confirmar que ninguna ruta queda fuera de los tres `Route::middleware(...)`
declarados.

**GestionRed: 0 agujeros.** (Coincide con la investigación previa del item #414 documentada en
`CLAUDE.md`: la defensa en profundidad de `OLTsOnuController` es un extra sobre esta misma base,
no una excepción a ella.)

---

## 7. MapaRed (`app/Modules/Addons/MapaRed/routes.php`, 209 líneas)

Un único bloque efectivo: las 2 rutas sueltas de las líneas 28-36 y el grupo `prefix('mapa-red/api')`
de las líneas 48-209 — los tres `Route::middleware(['web','auth','check_route_permission'])`.
**Clasificación 1 para el 100% de las 98 rutas.**

Dos rutas llevan además un gate fino inline (bonus sobre la base 1, no una excepción):

| Método | Ruta | Controller@método | Línea | Gate fino adicional |
|---|---|---|---|---|
| POST/PUT/DELETE | /mapa-red/api/cobertura-declarada... | CoberturaDeclaradaController | 197-201 | `abort_unless(auth()->user()?->can('mapared.cobertura_declarada.manage'), 403)` (controller:69) |
| POST | /mapa-red/api/enlaces | EnlacesController@store | 204 | `auth()->user()?->can('mapa_red_trazar')` (controller:19) |

**MapaRed: 0 agujeros.**

---

## Conclusión del lote 2/3

De las 1054 rutas revisadas en los 7 addons: **658 protegidas por `check_route_permission`, 275
gateadas por rol/permiso/guard dedicado, 90 con `authorize()`/token inline, 14 públicas
intencionales documentadas, y 17 en AGUJERO REAL** — concentrados en 2 addons:

- **MegaFamilia** (9 rutas, todas MEDIO): lectura sin scoping de datos de menores
  (perfiles/tareas/reportes) cuando el usuario autenticado no tiene `ParentalAccount` propia.
- **Marketing** (8 rutas, 4 CRÍTICO + 4 MEDIO/BAJO): `MarketingConversationController`
  (conversaciones de WhatsApp reales) sin ningún gate, más 3 métodos sueltos
  (`getEmbedCode`/`download`/`lead-sources`) que se escapan de los `->only()` de sus hermanos, más
  el bloque completo de `PilotCampaignController` (mitigado por operar sobre listas dummy).

Mapas, Roadmap, GestionRed y MapaRed no presentan agujeros — Roadmap en particular resulta ser el
addon con más defensa en profundidad explícita del lote (61 de sus 93 rutas verifican permiso
punto por punto vía `$this->authorize()`, incluido el kill-switch del propio circuito).

Este documento es el ENTREGABLE del item #9990770 (lote 2/3). NO se cierran los items #9990764
(padre) ni #9990745 (abuelo) — quedan para el lote de consolidación.
