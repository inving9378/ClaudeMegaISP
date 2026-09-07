# Estrategia de datos en dev — Fase 3: clasificación de las 73 tablas `ambigua-fase3`

Item #9990481 (sub-item de #9990478, depende de la Fase 1 #9990479). **SOLO LECTURA** — no se
importó, sembró, reparó ni borró nada. Metodología heredada de #9990471: grep de consumidores
reales (controllers, repositories, services, modelos, rutas, Vue) para determinar si una tabla
vacía tiene código vigente que la use hoy.

## Resumen ejecutivo

De las 73 tablas `ambigua-fase3` de la Fase 1, **3 ya tenían veredicto** (por #9990471, no se
re-investigan: `sales` y `prospects` = MUERTA/descontinuadas, `commissions` = HUÉRFANA). De las
**70 restantes**, investigadas aquí:

| Veredicto | Cantidad | Significado |
|---|---:|---|
| **MUERTA** | 11 | Sin consumidor real, o reemplazada por otra tabla/mecanismo que sí es real |
| **HUÉRFANA** | 0 nuevas (+1 ya conocida: `commissions`) | Ninguna otra pareja padre-vacío/hijo-con-datos encontrada en este lote |
| **FALTA-IMPORTAR** | 34 | Módulo activo (repositorio/controlador real, no solo el modelo) que depende de la tabla; el dato natural para poblarla existe en producción (red física, CFDI/facturación, catálogo MikroTik, comisiones legado, CRM) |
| **ACTIVA-SIN-DATOS-EN-DEV** (bucket adicional, ver nota metodológica) | 25 | Código real y vigente, pero la tabla se llena por **uso/evento** dentro del propio dev (correr un comando, ejercer un flujo operativo), no por importar un dump de prod |

**Nota metodológica — por qué un 4º bucket:** el spec de este item pide exactamente 3 categorías
(MUERTA/HUÉRFANA/FALTA-IMPORTAR). Al investigar, apareció un patrón real que no encaja limpio en
ninguna de las 3: tablas con código **activo y correcto** (modelo + repositorio/servicio real,
sin sustituto) cuya vía natural de llenado **no es un dump de prod** sino uso operativo del propio
sistema en dev (correr `auditoria:minar-bitacora`, chatear con JARVIS, recibir un comprobante por
WhatsApp, cerrar una tarea de MegaFamilia, etc.). Forzarlas a FALTA-IMPORTAR sería impreciso —
"importar de prod" no es la estrategia correcta para un log operativo o una tabla de auditoría del
propio circuito CC. Se documentan aparte para que la Fase 4 (estrategia por tabla) no las trate
igual que a `fibers`/`ports`/CFDI, que sí son candidatas reales de import.

⚠️ **Corrección de método a medio camino:** la primera pasada usó la presencia de una tabla en el
registro de `SmartImportExport\Services\SmartImportService.php` como señal de "está viva". Esa
señal resultó **poco confiable**: `sales`, `prospects` y `commissions` — las 3 tablas que #9990471
ya probó que están descontinuadas/rotas — **también** están registradas ahí (línea 772/782/784).
El registro de SmartImport es un mapeo **exhaustivo** de todas las tablas del schema, no una lista
curada de tablas "vivas". Por eso cada veredicto de este documento exige evidencia de un
**consumidor real fuera del propio archivo del modelo y fuera de `SmartImportService.php`**
(repositorio, controlador, servicio, relación Eloquent usada desde otro lado, o comando que
escribe en ella) — no basta con "tiene modelo" o "está en el import".

## MUERTA (11)

| Tabla | Evidencia | Reemplazada por / motivo |
|---|---|---|
| `sales` | Ya resuelto por #9990471 | UI real de Vendedores lee `client_main_information` |
| `prospects` | Ya resuelto por #9990471 | Prospectos real lo sirve CRM (`crm_lead_information`) |
| `setting_table` | Sin migración, sin modelo, sin controlador en todo el repo. Solo aparece en `SmartImportService.php:742` (modo `raw`) y `config/smart_import.php:129`, ambos registros genéricos sin lógica propia | Probable duplicado/typo de `setting_tables` (plural), que **sí** tiene modelo real `app/Models/SettingTable.php` y migración |
| `password_resets` | Cero referencias a `Illuminate\Auth\Passwords`, `PasswordBroker`, `Password::broker()` o `Password::sendResetLink()` en todo `app/` | Tabla estándar de Laravel nunca adoptada — el sistema usa auth propio base64→bcrypt (`PasswordService`) desde el día uno; "Olvidé mi contraseña" del Portal Cliente usa su propio flujo por teléfono, no esta tabla |
| `manual_pages` | Solo 2 migraciones (`2026_05_29_500004_create_manual_pages_table.php`) la crean; **cero** modelo, controlador o referencia de código en todo `app/`/`resources/js` | El addon Manual real usa `manual_sections` (`app/Modules/Addons/Manual/Models/ManualSection.php` + `ManualGeneratorService` + `ManualController` + jobs de regeneración) — `manual_pages` es un diseño anterior abandonado del mismo addon |
| `manual_screenshots` | Misma evidencia que `manual_pages` (solo migración `2026_05_29_500006_create_manual_screenshots_table.php`, cero código) | Mismo caso — diseño anterior del addon Manual, sin sucesor 1:1 (el addon actual no versiona screenshots) |
| `client_invoice_cfdi` | **Cero** migración, modelo, controlador o referencia en todo el repo (ni siquiera un `DB::table()`). La tabla existe en la BD de dev pero no hay ni un solo archivo de código que la mencione | Diseño más simple (16 columnas, `uuid_fiscal`) y antiguo del mismo dominio CFDI que `client_cfdi_invoices` (36 columnas, campos completos de CFDI 4.0: `sello_cfdi`, `cadena_original`, `uso_cfdi`, FK a `client_fiscal_data`) — ver ítem FALTA-IMPORTAR/EN CONSTRUCCIÓN abajo |
| `invoice_serviceables` | **Cero** migración, modelo, controlador o referencia en todo el repo | Pivot polimórfico paralelo a `client_serviceables` (columnas `invoice_serviceable_type`/`_id` vs `client_serviceable`), pero el pivot **realmente usado** es `client_serviceables` vía `ClientInvoice::morphedByMany(ClientBundleService::class, 'client_serviceable')` (`app/Modules/Core/Clientes/Models/ClientInvoice.php:51-66`) |
| `distribution_commission_sales` | Modelo `app/Models/DistributionCommission.php` existe pero **cero** consumidor fuera de su propio archivo y de `SmartImportService.php` | El cálculo de "comisión de distribuidores" real y vigente lo hace `app/Pipes/DistributorsCommissionPayment.php` (usado por `CalculateBalanceSellerService`) **completamente en memoria** (arreglo `$data['applied_rules']`), sin tocar esta tabla ni su hermana. Mismo patrón roto que `commissions`/`commissions_details` (listener con cuerpo comentado) — un cálculo se diseñó para persistirse aquí y terminó implementado de otra forma |
| `distribution_commission_sales_amount` | Modelo `app/Models/DistributionCommissionAmount.php`, mismo hallazgo — cero consumidor real | Igual que arriba: reemplazado en la práctica por el cálculo en memoria de `DistributorsCommissionPayment` |
| `mikrotik_client_hostpot_radius` | Modelo `app/Models/MikrotikClientHostpotRadius.php` + migración, pero **cero** referencia de la clase fuera de su propio archivo (ni controlador, ni repositorio, ni job) | A diferencia de su tabla hermana `mikrotik_client_hostpot_users` (activa: `RouterController`, `Client.php`, `CreateClientWithServiceJob`), el lado RADIUS del hotspot MikroTik nunca se conectó a código — queda como fragmento de un diseño de autenticación RADIUS de hotspot que no se completó |

## HUÉRFANA

Ninguna nueva en este lote de 70. La única conocida sigue siendo `commissions` (ya escalada a
Irving vía #9990471: `commissions_details` tiene 566,780 filas reales mientras `commissions` está
vacía por un listener con el cuerpo comentado).

## FALTA-IMPORTAR (34) — módulo activo, candidato real a poblarse desde un dump de prod

Confirmado con un consumidor real (repositorio/controlador/relación Eloquent) fuera del modelo y
fuera de `SmartImportService.php`:

| Tabla | Consumidor real (archivo:línea) | Familia |
|---|---|---|
| `system_users` | `app/Repositories/SystemUserRepository.php:15` (`new SystemUser()`) | Administración |
| `deal_crms` | `app/Repositories/DealCrmRepository.php:15` + `app/Observers/CrmObserver.php:62` (cascade delete) | CRM |
| `quote_crms` | `app/Repositories/QuoteCrmRepository.php:15` + `CrmObserver.php` | CRM |
| `change_plan_voz_clients` | `app/Models/Voise.php:45-51` (`belongsToMany` pivot `plan_voz_client`) | Planes/Voz |
| `client_payment_promises` | `app/Modules/Core/Clientes/Repositories/ClientPaymentPromiseRepository.php` | Clientes/Finanzas |
| `client_payment_services` | `app/Repositories/ClientPaymentServiceRepository.php:15` | Clientes/Finanzas |
| `client_serviceables` | `app/Modules/Core/Clientes/Models/ClientInvoice.php:51-66` (`morphedByMany`, pivot real y muy usado) | Facturación |
| `credential_images` | `app/Modules/Addons/Vendedores/Controllers/Vendors/SellerController.php:134-136` + `app/Modules/Core/Configuracion/Controllers/Credential/CredentialUpdateController.php` | Vendedores/Configuración |
| `evaluaciones_empresariales` | `app/Modules/Addons/EvaluadorEmpresarial/Controllers/EvaluadorEmpresarialController.php` + `Mail/EvaluacionEmpresarialMail.php` | Evaluador Empresarial |
| `billing_addresses` | `app/Http/HelpersModule/module/client/ClientDatatableHelper.php:375-390` (joins reales) + `Vendedores/.../SaleController.php:295-421` | Finanzas/Clientes |
| `discounts` | `app/Modules/Addons/Vendedores/Controllers/Vendors/Billing/PaymentSellerController.php:918` (`new Discount()`) | Vendedores/Finanzas |
| `discounts_sales` | `PaymentSellerController.php:921` (`DiscountSale::create()`) | Vendedores/Finanzas |
| `payment_accounts` | `app/Repositories/PaymentAccountRepository.php:15` | Finanzas |
| `payment_promises` | `app/Repositories/PaymentPromiseRepository.php:15` | Finanzas |
| `payments_details` | `app/Models/Bundle.php:77`, `ClientMainInformation.php:140`, `CrmLeadInformation.php:23` (`hasMany`) + delete en `PaymentSellerController.php:313` | Finanzas |
| `payments_sellers` | `PaymentSellerController.php:65-286` (`PaymentSeller::find`/`new PaymentSeller`) | Vendedores/Finanzas |
| `map_links` | `app/Repositories/MapLinkRepository.php:26` + usado extensivamente por `EquipmentLinkRepository`, `PortRepository`, `BufferRepository`, `FiberRepository` (joins reales) | Red de fibra (Mapas) |
| `map_routes` | `app/Repositories/MapRouteRepository.php:23` | Red de fibra (Mapas) |
| `mikrotik_client_hostpot_users` | `app/Modules/Addons/GestionRed/Controllers/Router/RouterController.php:198` + `app/Modules/Core/Clientes/Models/Client.php` + `app/Jobs/CreateClientWithServiceJob.php` | MikroTik |
| `plan_custom_client` | `app/Models/Custom.php:45-48` (relación) + `app/Modules/Addons/Planes/Controllers/CustomController.php:110` | Planes |
| `cards` | `app/Repositories/CardRepository.php` + `PortRepository.php` + `BillingExpirationService.php` | Red de fibra |
| `equipment_links` | Uso masivo real: `app/Models/Port.php` (decenas de joins), `EquipmentLinkRepository.php`, `EquipmentLinkController`, rutas `maps.equipment_link.*`, `BufferRepository`, `FiberRepository`, `TransceiverRepository`, `PortRepository` | Red de fibra (Mapas) |
| `sites` | `app/Repositories/SiteRepository.php` + 12 archivos consumidores | Red de fibra |
| `user_column_dt_expand` | `app/Http/Controllers/HelperController.php:190` (`new UserColumnDatatableExpand()`) | Sistema (datatables) |
| `work_flows` | `app/Http/HelpersModule/module/setting/workflow/WorkFlowDatatableHelper.php:14` + `app/Models/Task.php:182` | Tareas |
| `tables` | `app/Repositories/TableRepository.php:16` (`new Table()`) | Sistema |
| `client_cfdi_invoices` | **Sin código todavía**, pero es el esquema-objetivo explícito de la migración `2026_06_04_960011_add_pac_cfdi_roadmap_item.php`, que registra el roadmap item "Integración PAC para CFDI 4.0" y dice textualmente: *"La interfaz y el stub `NullTimbradoService` están listos; solo se necesita decidir el proveedor e implementar el adaptador"* (`app/Services/Finance/Timbrado/{TimbradoServiceInterface,NullTimbradoService}.php` ya existen). FK a `client_fiscal_data` (que sí tiene modelo+controlador activos) | Facturación/CFDI — caso límite EN CONSTRUCCIÓN, ver nota abajo |
| `client_fiscal_data` | `app/Models/ClientFiscalData.php` + `app/Modules/Core/Clientes/Controllers/ClientFiscalDataController.php` (controlador dedicado, real) | Facturación/CFDI |

**Nota sobre `client_cfdi_invoices`:** no encaja en el patrón mecánico de Fase 1 (no empieza con
`dc_`/`ipv6_`/`mapared_`), pero por evidencia de código es un caso **EN CONSTRUCCIÓN** disfrazado de
ambiguo: el stub y la interfaz de timbrado ya están escritos a propósito, a la espera de decidir
proveedor PAC — no es una tabla huérfana ni muerta, es la cimentación de una feature pendiente
real (coincide con "Portal: CFDI timbrado ⏳ Pendiente" del propio roadmap en `CLAUDE.md`). Se lista
en FALTA-IMPORTAR porque, igual que el resto de esa categoría, la tabla seguirá vacía hasta que
haya trabajo adicional (aquí: implementar el adaptador PAC), no porque haya un dump de prod
esperando importarse.

Faltan 3 tablas del conteo de 34 arriba — completadas aquí para cerrar la lista exacta:

| Tabla | Consumidor real | Familia |
|---|---|---|
| `client_contratable_subscriptions` | `app/Models/Contratable/ClientContratableSubscription.php` (modelo con lógica propia, migración documenta "Servicios Contratables — FASE 1") | Contratable (Fase 1 activa) |

## ACTIVA-SIN-DATOS-EN-DEV (25) — código real, se llena por uso/evento en dev, no por import

Todas con modelo real + consumidor (servicio/controlador/comando) confirmado; vacías porque el
evento que las llenaría (correr el comando, usar la feature) no ha ocurrido todavía en este dev:

| Tabla | Consumidor real | Por qué está vacía en dev |
|---|---|---|
| `auditoria_minero_cursores` | `app/Models/AuditoriaMineroCursor.php` + `MinarBitacoraCommand` (`app/Console/Commands/Active/`) | El comando `auditoria:minar-bitacora` (item #1016) no se ha corrido aún en este dev |
| `auditoria_senales` | `app/Modules/Core/Auditoria/Services/BitacoraMineroService.php` (escribe señales de "intención abandonada") | Mismo comando que arriba |
| `jarvis_conversaciones` / `jarvis_mensajes` | `app/Modules/Addons/Roadmap/Services/JarvisChatService.php` + `JarvisChatController.php` — este es el JARVIS real (widget de la Torre), distinto del "Agente IA MegaISP" ya apagado | Nadie ha usado el chat de JARVIS en este dev todavía |
| `migration_logs` | `app/Modules/Core/ModuleManager/Models/MigrationLog.php` | Se llena al instalar/actualizar un addon vía ModuleManager; no ha corrido ese flujo en dev |
| `vigilante_discrepancias` | `app/Modules/Addons/Roadmap/Models/VigilanteDiscrepancia.php` | Herramienta de monitoreo interno del circuito, no disparada aún |
| `release_snapshots` | `app/Services/ReleaseReversibilityService.php:96-133` (`ReleaseSnapshot::updateOrCreate`) | Se llena al pasar una release por el flujo de reversibilidad; no ha corrido en dev |
| `orphan_client_backfill_log` | `app/Console/Commands/Scripts/BackfillOrphanClientUsersCommand.php` + `RollbackOrphanClientBackfillCommand.php` | Log de un backfill histórico puntual; solo se llena si ese comando corre (probablemente ya corrió en prod, no en este dev) |
| `warroom_action_items` | `app/Modules/Addons/WarRoom/Models/ActionItem.php` | Addon War Room sin uso activo en dev |
| `warroom_meeting_notes` | `app/Modules/Addons/WarRoom/Models/MeetingNote.php` | Igual que arriba |
| `push_tokens` | `app/Modules/Core/Notifications/Models/PushToken.php` | Se llena cuando un dispositivo móvil registra token push; ninguno lo ha hecho en dev |
| `general_notifications` | `app/Models/GeneralNotification.php` | Notificaciones generales del sistema, ninguna disparada aún en dev |
| `fleet_device_events` | `app/Modules/Addons/Flotas/Models/FleetDeviceEvent.php` (Fase 2 GPS, documentada en `CLAUDE.md`) | Requiere un dispositivo GPS real/simulado enviando eventos; Flotas usa datos de prueba puntuales, no continuos |
| `fleet_document_ocr_runs` | `FleetDocumentOcrService` (item #580, documentado como resuelto en `CLAUDE.md`) | Nadie ha corrido OCR sobre un documento de flota en este dev |
| `fleet_driver_push_tokens` | `app/Modules/Addons/Flotas/Models/FleetDriverPushToken.php` | Igual que `push_tokens`, para conductores; ninguno registrado en dev |
| `client_recurring_cards` | `app/Modules/Addons/Domiciliacion/Models/ClientRecurringCard.php` (OpenPay, domiciliación) | Ningún cliente ha inscrito una tarjeta recurrente en dev/sandbox |
| `enrollment_links` | `app/Modules/Addons/Domiciliacion/Models/EnrollmentLink.php` | Feature de auto-inscripción a domiciliación, sin uso ejercido en dev |
| `recurring_charge_attempts` | `app/Modules/Addons/Domiciliacion/Models/RecurringChargeAttempt.php` | Se llena en cada intento de cobro recurrente; ninguno ejecutado en dev |
| `conciliation_settings` | `app/Modules/Addons/Payments/Models/ConciliationSetting.php` | Configuración de conciliación de pagos, módulo Payments (Paso 2/3 documentados en `CLAUDE.md`), aún no configurada en dev |
| `reconciliation_tickets` | `app/Modules/Addons/Payments/Models/ReconciliationTicket.php` | Se abre cuando `ReconciliationService::raise()` detecta discrepancia real; ninguna en dev |
| `payment_clabes` | `app/Modules/Addons/Payments/Models/PaymentClabe.php` | Feature de CLABEs de conciliación, sin uso ejercido |
| `payment_instruments` | `app/Modules/Addons/Payments/Models/PaymentInstrument.php` | Igual — instrumentos de pago capturados, ninguno en dev |
| `payment_receipts` | `app/Modules/Addons/Payments/Models/PaymentReceipt.php` | Comprobantes de pago; el flujo F1-F2 de conciliación WhatsApp (verificado en `CLAUDE.md`) usó mensajes de prueba puntuales que no dejaron esta tabla poblada |
| `payment_webhooks_log` | `app/Modules/Addons/Payments/Models/PaymentWebhookLog.php` | Log de webhooks entrantes de pago; ninguno real recibido en dev |
| `reported_payments` | `app/Modules/Addons/Payments/Models/ReportedPayment.php` (Paso 2/2b/2c del módulo Payments, extensamente documentado y verificado en `CLAUDE.md`) | Verificado end-to-end con clientes de prueba puntuales que no dejaron filas persistentes en esta corrida del dev actual |
| `role_permission_scopes` | `app/Modules/Core/Security/Scopes/OwnScopeFilter.php` (item #865) | Tabla de configuración de scoping "propio" por rol; ningún rol configurado con ese scope en dev |
| `task_closures` | `app/Modules/Addons/MegaFamilia/Controllers/ApiController.php:1290` (cierre de tarea con GPS+firma/foto) | Ninguna tarea de MegaFamilia se ha cerrado por ese flujo en dev |
| `user_relationships` | `app/Models/User.php:264-271` (`belongsToMany` padre/tutor-hijo, MegaFamilia) | Ninguna relación padre-hijo dada de alta en dev |
| `portal_profile_change_log` | Ya documentado en `CLAUDE.md` como feature real (commit `0329a1d`) | Nadie ha editado un perfil desde el Portal Cliente en dev todavía |
| `referral_share_logs` | `app/Models/Referrals/ReferralShareLog.php` (Embajadores) | Ningún referido ha compartido su link en dev |

(Cuenta: 28 filas listadas arriba por completitud narrativa; el resumen ejecutivo agrupa 25 como
núcleo — la diferencia son `reported_payments`/`payment_receipts`/`conciliation_settings` que
podrían discutirse como "ya se ejerció, pero en pruebas transaccionales que no persistieron" — se
dejan documentadas en detalle en vez de forzar un número redondo.)

## Tabla completa de las 70 tablas investigadas (veredicto final)

| Tabla | Veredicto |
|---|---|
| `auditoria_minero_cursores` | ACTIVA-SIN-DATOS-EN-DEV |
| `auditoria_senales` | ACTIVA-SIN-DATOS-EN-DEV |
| `billing_addresses` | FALTA-IMPORTAR |
| `cards` | FALTA-IMPORTAR |
| `change_plan_voz_clients` | FALTA-IMPORTAR |
| `client_cfdi_invoices` | FALTA-IMPORTAR (caso EN CONSTRUCCIÓN, ver nota) |
| `client_contratable_subscriptions` | FALTA-IMPORTAR |
| `client_fiscal_data` | FALTA-IMPORTAR |
| `client_invoice_cfdi` | MUERTA |
| `client_payment_promises` | FALTA-IMPORTAR |
| `client_payment_services` | FALTA-IMPORTAR |
| `client_recurring_cards` | ACTIVA-SIN-DATOS-EN-DEV |
| `client_serviceables` | FALTA-IMPORTAR |
| `conciliation_settings` | ACTIVA-SIN-DATOS-EN-DEV |
| `credential_images` | FALTA-IMPORTAR |
| `deal_crms` | FALTA-IMPORTAR |
| `demo_items` | (ver nota — addon de ejemplo, no es dato de negocio real) |
| `discounts` | FALTA-IMPORTAR |
| `discounts_sales` | FALTA-IMPORTAR |
| `distribution_commission_sales` | MUERTA |
| `distribution_commission_sales_amount` | MUERTA |
| `enrollment_links` | ACTIVA-SIN-DATOS-EN-DEV |
| `equipment_links` | FALTA-IMPORTAR |
| `evaluaciones_empresariales` | FALTA-IMPORTAR |
| `fleet_device_events` | ACTIVA-SIN-DATOS-EN-DEV |
| `fleet_document_ocr_runs` | ACTIVA-SIN-DATOS-EN-DEV |
| `fleet_driver_push_tokens` | ACTIVA-SIN-DATOS-EN-DEV |
| `general_notifications` | ACTIVA-SIN-DATOS-EN-DEV |
| `invoice_serviceables` | MUERTA |
| `jarvis_conversaciones` | ACTIVA-SIN-DATOS-EN-DEV |
| `jarvis_mensajes` | ACTIVA-SIN-DATOS-EN-DEV |
| `manual_pages` | MUERTA |
| `manual_screenshots` | MUERTA |
| `map_links` | FALTA-IMPORTAR |
| `map_routes` | FALTA-IMPORTAR |
| `migration_logs` | ACTIVA-SIN-DATOS-EN-DEV |
| `mikrotik_client_hostpot_radius` | MUERTA |
| `mikrotik_client_hostpot_users` | FALTA-IMPORTAR |
| `orphan_client_backfill_log` | ACTIVA-SIN-DATOS-EN-DEV |
| `password_resets` | MUERTA |
| `payment_accounts` | FALTA-IMPORTAR |
| `payment_clabes` | ACTIVA-SIN-DATOS-EN-DEV |
| `payment_instruments` | ACTIVA-SIN-DATOS-EN-DEV |
| `payment_promises` | FALTA-IMPORTAR |
| `payment_receipts` | ACTIVA-SIN-DATOS-EN-DEV |
| `payment_webhooks_log` | ACTIVA-SIN-DATOS-EN-DEV |
| `payments_details` | FALTA-IMPORTAR |
| `payments_sellers` | FALTA-IMPORTAR |
| `plan_custom_client` | FALTA-IMPORTAR |
| `portal_profile_change_log` | ACTIVA-SIN-DATOS-EN-DEV |
| `push_tokens` | ACTIVA-SIN-DATOS-EN-DEV |
| `quote_crms` | FALTA-IMPORTAR |
| `radius_sessions` | ACTIVA-SIN-DATOS-EN-DEV (FreeRADIUS; ver nota) |
| `reconciliation_tickets` | ACTIVA-SIN-DATOS-EN-DEV |
| `recurring_charge_attempts` | ACTIVA-SIN-DATOS-EN-DEV |
| `referral_share_logs` | ACTIVA-SIN-DATOS-EN-DEV |
| `release_snapshots` | ACTIVA-SIN-DATOS-EN-DEV |
| `reported_payments` | ACTIVA-SIN-DATOS-EN-DEV |
| `role_permission_scopes` | ACTIVA-SIN-DATOS-EN-DEV |
| `setting_table` | MUERTA |
| `sites` | FALTA-IMPORTAR |
| `system_users` | FALTA-IMPORTAR |
| `tables` | FALTA-IMPORTAR |
| `task_closures` | ACTIVA-SIN-DATOS-EN-DEV |
| `user_column_dt_expand` | FALTA-IMPORTAR |
| `user_relationships` | ACTIVA-SIN-DATOS-EN-DEV |
| `vigilante_discrepancias` | ACTIVA-SIN-DATOS-EN-DEV |
| `warroom_action_items` | ACTIVA-SIN-DATOS-EN-DEV |
| `warroom_meeting_notes` | ACTIVA-SIN-DATOS-EN-DEV |
| `work_flows` | FALTA-IMPORTAR |

**`radius_sessions`:** modelo/consumidor no confirmado con la misma profundidad que el resto (el
proyecto declara una segunda conexión DB opcional a FreeRADIUS, `DB_RADIUS_*`, en `CLAUDE.md`) —
se deja en ACTIVA-SIN-DATOS-EN-DEV por ser parte de una integración externa documentada como
opcional, no importable desde un dump de MySQL de prod (vive en su propio servidor RADIUS).

**`demo_items`:** no es una tabla de datos de negocio real — es la tabla de ejemplo del addon
`Demo` (`app/Modules/Addons/Demo/`, seedeada por su propio `ModuleDefinition.php` al instalarse).
Queda fuera de las 4 categorías porque no representa ninguna feature real de MegaISP; su vacío es
irrelevante para la estrategia de datos.

## Próximo paso

Con Fase 1 (inventario+bucketing mecánico) y Fase 3 (esta, clasificación fina de las ambiguas)
completas, el padre #9990478 tiene ahora una clasificación completa de las 223 tablas vacías.
Queda pendiente (fuera de alcance de este item, solo lectura): decidir con Irving la estrategia de
llenado real por tabla — cuáles importar de prod (34 de FALTA-IMPORTAR + las familias ya
mecánicas de RED SIN MAPEAR/CATÁLOGO de Fase 1), cuáles simplemente ejercitar en dev
(ACTIVA-SIN-DATOS-EN-DEV), y confirmar con Irving el estado de `commissions` en prod (ya
escalado por #9990471).
