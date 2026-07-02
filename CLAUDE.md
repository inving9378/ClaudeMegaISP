# CLAUDE.md

Guía de contexto para Claude Code en este repositorio. Leer antes de explorar.

---

## REGLAS PARA AHORRAR TOKENS

- NO explorar directorios completos sin necesidad
- NO leer archivos que no sean directamente relevantes
- Usar rutas absolutas directamente sin buscar
- Si el archivo ya fue mencionado antes, ir directo a él
- Confirmar cambios con diff, no leyendo el archivo completo
- Para cambios pequeños: Edit directo, no Read+Write
- Máximo 2-3 bash commands por paso, no 10 pasos para algo simple

---

## PROYECTO ACTIVO

- **Path:** `/var/www/megaisp`
- **URL:** `http://192.168.105.11` (puerto 80)
- **Stack:** Laravel 10 + Vue 3 + Quasar + MySQL
- **Compilación frontend:** `npm run dev` (Laravel Mix/Webpack)
- `/var/www/MEGANET` era árbol de pruebas — vhost deshabilitado, puerto 8080 no responde. **No tocar.**

**MegaISP / Meganet Telecomunicaciones** — sistema de gestión para ISP. Maneja clientes, facturación, CRM, planes (Internet, VoIP, Custom, Bundle), integración MikroTik, gestión OLT/ONU, mapeo de red de fibra, tickets, inventario, vendedores/comisiones y agendamiento. Codebase y UI en **español**.

---

## ARQUITECTURA

### Módulos
- Módulos en: `app/Modules/`
- Namespace: `App\Modules\...`
- Layout principal: `app/Modules/Core/Layout/views/master.blade.php`
- Sidebar: `app/Modules/Core/Layout/views/sidebar.blade.php`
  - Blade estático con `@can`/`@hasanyrole` — **NO es Vue ni API**
  - Para agregar items: editar directamente el Blade

### Autenticación
- Login field: `login_user` (**NO** es `email`)
- Passwords: `base64_encode` (**NO** bcrypt)
- Usuario admin: `admin@meganet.com` / rol `DESARROLLADOR` (id=3986, 453 permisos)

### Backend layout
- `app/Http/Controllers/Module/<Domain>/` — controllers por módulo de negocio
- `app/Http/Repository/` — capa repositorio sobre Eloquent
- `app/Services/` — servicios de dominio pesados
- `app/Http/Traits/` y `app/Http/Traits/Models/` — comportamiento compartido de modelos
- `app/Models/BaseModel.php` — todos los modelos extienden este; auto-stamp `created_by`/`updated_by`

### Rutas y permisos
- `routes/web.php` (~1750 líneas) — casi todas las rutas dentro de `middleware => ['auth', 'check_route_permission']`
- `CheckRoutePermission` middleware — controla autorización por URL usando spatie/laravel-permission
- `PUBLIC_ROUTES` — whitelist de rutas que saltan permisos (imágenes, lookups de ayuda)
- Frontend: Vuex store carga permisos via `GET /permissions-auth` en boot, directiva `v-hasPermission`

### Frontend
- **Un solo app Vue**, componentes registrados globalmente en `resources/js/app.js`
- Blade en `resources/views/meganet/` usa tags kebab-case dentro de `<div id="init-vue">`
- Quasar cargado desde `public/plugins/quasar/js/quasar.umd.prod` (no npm)
- Iconos: FontAwesome v5
- Mix compila `resources/js/app.js` → `public/js/app.js`
- Al agregar componente Vue: crear `.vue` + importar y registrar en `app.js`

#### Iconos Font Awesome — cuál se sirve REALMENTE (convención)
La librería FA realmente servida es **Font Awesome 5 Free**, vía `public/assets/css/icons.min.css` (importado en `head.blade.php:15`). **NO** es el `node_modules/font-awesome` 4.7 (ese paquete existe pero no se sirve), ni `app.css` (que no contiene glifos `fa-*`).

Regla al elegir/cambiar un icono `fa-*`:
- Validar el nombre grepeando el **CSS SERVIDO**, no `node_modules` ni `package.json`:
  ```bash
  grep -o "NOMBRE:before{content:[^}]*}" public/assets/css/icons.min.css
  ```
  Debe devolver una regla con `content: "\fXXX"`. Si no hay regla de content, el glifo no existe en la build → el botón saldrá **vacío**.
- Nombres FA4 con sufijo `-o` (`fa-file-text-o`, `fa-file-o`, `fa-handshake-o`) **NO existen en FA5**. En FA5 el `-o` desapareció; usar el nombre FA5 (ej. `fa-file-text-o` → `fa-file-alt`).
- Iconos **solid** de FA5 renderizan con el prefijo `fa` en este bundle (probado con `fa-upload` y `fa-file-contract`).
- Ante la duda, `fa-file-alt` / `fa-file` son dual-style (regular+solid) y renderizan siempre.
- Botones **Quasar `q-btn`** usan **Material Icons** (prop `icon="download"`, `icon="fullscreen"`), NO Font Awesome.

Incidente de origen: se cambió un icono a `fa-file-text-o` (nombre FA4) → botón vacío, porque se validó contra `node_modules` 4.7 en vez del CSS servido FA5. Corregido a `fa-file-contract` (`\f56c`).

---

## BASE DE DATOS

- DB activa: la definida en `/var/www/megaisp/.env`
- SmartImport UI en: `/configuracion/smart-import`

### Importación de BD de producción (proceso activo al 2026-05-23)
- Errores esperados/controlados:
  - `bundles` — `Field 'title' doesn't have a default value` → fila rechazada, sin daño
  - Duplicate PK (1062) en múltiples tablas → filas ya existentes, omitidas correctamente

- **Problema pendiente — cascada de fallos:**
  - `bundles.title` es NOT NULL → rechaza filas de bundles del dump
  - → `client_bundle_services` falla con `Attempt to read property "id" on null`
  - → `client_custom_services` falla con FK violation 1452 (`client_bundle_service_id`)
  - **Solución:** `ALTER TABLE bundles MODIFY COLUMN title VARCHAR(255) NULL;`
  - Aplicar antes de reimportar si los bundles son datos reales de producción

### Sistema de módulos (import/export)
- `ModuleRepository::MODULES_FOR_IMPORT` — lista de módulos importables
- Modelos implementan `getRequestAndStoreMethod()` para integración con SmartImport
- Reglas de validación en `ComunConstantsController::RULES`

### SmartImport — consistencia del merge inteligente (2026-06-06)
- Modo SMART = upsert por identidad: tablas identidad-por-PK (clients, bundles, client_bundle_services…) mergean por `id` y lo **preservan**; tablas de catálogo (`permissions`, `roles`, `migrations`, `system_settings`, marcadas `identity_priority=>override` en `config/smart_import.php`) mergean por **llave de negocio**.
- **Fix 1062**: `flushBulkMergeRows` descartaba mal la PK — ahora, si el merge va por llave de negocio (la PK no está en `$keys`), **descarta la PK del dump** antes del upsert (helper `primaryKeyColumns()`), evitando el choque `ON DUPLICATE` entre PRIMARY y la única de negocio. Detalle completo + verificación en `app/Modules/Addons/SmartImportExport/CHANGELOG-merge-consistency.md`.
- Pendiente decisión: remapeo de `permission_id`/`role_id` en pivotes del dump (ver changelog).

---

## MÓDULOS IMPORTANTES

### DevTools (`app/Modules/Addons/DevTools/`)
- Vista: `views/index.blade.php` → `@extends('core-layout::master')`
- **Fix aplicado:** `DevtoolsPanel.vue` — `position: fixed` → `relative`, `height: calc(100vh - 70px)`
- **Pendiente:** ocultar mini-sidebar duplicado (`.dt-sidebar`) sin romper layout

### MegaFamilia (`app/Modules/Addons/MegaFamilia/`)
- 16 tablas `parental_*` creadas
- Rutas cargadas via `ModuleServiceProvider`

### IaChatFloat (`resources/js/components/IaChatFloat.vue`)
- **Fix aplicado:** CSRF token en `bootstrap.js` (header `X-CSRF-TOKEN` en axios)
- Backend: `app/Http/Controllers/Module/IA/IAChatController.php`
- Env: `CLAUDE_API_KEY`, `CLAUDE_MODEL`

---

## PORTAL CLIENTE (`app/Modules/Addons/PortalCliente/`)

### Estado: ✅ Operativo en /portal/*

**Autenticación (actualizado Fase 2 — unificado contra la ficha):**
El login valida contra la columna **`client_main_information.password`** (la "Contraseña WEB" de la ficha del admin), en **texto plano** vía `hash_equals` — **NO** contra `portal_password`.
- Guard: `cliente` (session driver, provider `portal_clients` → `PortalClient` model)
- Tabla auth: `client_main_information`, columna de credencial = **`password`** (Contraseña WEB)
- `AuthController::login`: acepta como identificador **email**, columna **`user`** (Usuario WEB) o **`client_id`**; tolera ceros a la izquierda (`004981` ↔ `4981` vía `ltrim`). Compara `password` con `hash_equals` y luego `->login($cmi)` solo para abrir sesión.
- "Crear cuenta" (`registro`) y "Olvidé mi contraseña" (`recuperar`): tras verificar **teléfono** (`phone`/`phone2`/`phone3`), escriben la nueva contraseña en la columna **`password`** (texto plano) — **autoservicio activo**. El guard "ya tiene cuenta" fue **eliminado**.
- ⚠️ Cambiar la contraseña desde el portal **se refleja en la Contraseña WEB de la ficha del admin** (es el mismo campo `password`).
- `portal_password` (bcrypt) queda **en desuso para login** (legacy; solo 4 filas históricas auto-registradas, todas con `password` poblado → entran sin fricción). Columnas `portal_registered_at`/`portal_last_login_at` se siguen usando como timestamps.
- Deuda registrada en Hoja de Ruta: evaluar migración a bcrypt (Opción B) y normalizar el padding de `user`.
- Middleware: `auth.portal` (registrado en ModuleServiceProvider)
- Rutas: `/portal/*` (guard `cliente`, independiente del guard admin `web`)

**Aislamiento multi-tenant:**
- `PortalClientScope::clientId()` → scoping forzado en todas las queries
- Test: `php artisan portal:test-idor` (verifica cliente A ≠ cliente B)
- Condición de paro #9: si una query no se puede filtrar por client_id → no se expone

**Módulos activables:**
- MegaFamilia: activable desde `/portal/servicios` (crea `parental_accounts` con plan Demo gratuito)
  - Llave tenant: `parental_accounts.user_id` via `users.login_user = CMI.user`
- Flotas: GATEADO (módulo interno Meganet, `fleet_vehicles.client_id` nullable → no tiene scope por cliente)
- VoIP: GATEADO (en preparación)

**Cliente de prueba:**
- `clients.id` = 6810, nombre "FRANCISCO AGUILAR", teléfono `5510757244`
- Contraseña portal (para validación en browser): `Portal2026Test!`
  ⚠️ Rotar esta contraseña luego de validar — es temporal de prueba

---

## HOJA DE RUTA — Items pendientes

### Catálogo de módulos (form config DB-driven) — data-drift dev↔prod (2026-07-01)

**Contexto:** el generador de contratos ("Generar Contrato" en CRM y Clientes) no pintaba los selectores en dev. El síntoma parecía frontend, pero la causa raíz fue **data-drift**: la config de cada formulario es DB-driven (`Module::getfields()` vía `HelperController::getFieldsByModule`, keyed por `name`), y el row de catálogo `DocumentTemplateClient` (+ sus campos) faltaba en dev porque las migraciones que lo crean fueron archivadas a `database/migrations_old/` y **no corren en un migrate fresco**. `->getfields()` sobre null → 500 mudo → `fieldsJson={}` → form vacío.

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **Data-drift catálogo `modules` (RESUELTO)** | Rows `DocumentTemplateClient` (+3 campos type/template/html) y `FiltersTaskCalendar` ausentes en dev. Repuesto con migración **aditiva idempotente** `2026_07_01_000000_restore_document_template_client_module.php` (`firstOrCreate` por row y por campo, `down()` vacío a propósito — reponer catálogo real de prod). Commit `9b961269` (migración) + `373bee93` (guard de null en `HelperController` ×5). Verificado: `getfields()` devuelve los 3 campos, `select-2-type-template` presente, modal pinta en CRM y Clientes. Config replicada 1:1 del dump canónico `sql_test_unit.sql` (module 62). | ✅ **Resuelto** | — |
| **Barrido de guards de null en `HelperController` (RESUELTO)** | Se agregó `abort_if(!$module, 404, ...)` en los 3 hermanos que faltaban (`getColumnsByModule`, `getColumnDtExpandByModule`, `setColumnDtExpandByModule`) → 8 guards totales en el controller. Commit `2d214089`. | ✅ **Resuelto** | — |
| **Catálogo esencial atrapado en `migrations_old/` (PROCESO — la de fondo)** | Causa raíz del bug de hoy: migraciones que crean rows de catálogo (`modules` + campos) fueron archivadas → cada dev nuevo nace con **huecos**, y el síntoma aparece disfrazado de bug de frontend. **Definir política:** mover el catálogo esencial a un **seeder versionado idempotente** que SÍ corra por entorno, o documentar qué rows son canónicos. Sin esto, el próximo módulo repite el patrón. | ⚠️ Pendiente | **Alta** |
| **`json_decode(null)` en `Module.php` (RESUELTO)** | Guard `!== null` en `options` (186) e `inputs_depend` (190) → sin deprecation PHP 8.1 en `getfields()` (verificado: tinker limpio). Solo 186/190 requerían cambio; la **241** (`default_value`) ya era null-safe vía `isset()`+`isJson()`. Commit `1c2f5f3e`. | ✅ **Resuelto** | — |
| **`ComponentFormDefault.vue:356`** | El `v-if` de `select-2-type-template` es el único (~1 de 45) **sin `&& json.include`**. Inofensivo en los flujos actuales (el padre `v-for` ya filtra por `include`), pero inconsistente; muerde a cualquier consumidor que monte el componente sin el wrapper padre. | ⚠️ Pendiente | Media |
| **`DefaultValueRepository.php:35` (RESUELTO)** | `auth()->user()->id` → `auth()->user()?->id` en las **5** ocurrencias (26/35/44/54/64) → sin NPE en CLI/sin-sesión; path HTTP idéntico. Commit `617d03b4`. | ✅ **Resuelto** | — |
| **IDs de módulo divergentes dev↔prod (NOTA)** | Tras el fix, `FiltersTaskCalendar` quedó **id=133 en dev vs id=78 en prod** (auto-increment distinto). No afecta hoy (todo resuelve por `name`), pero si algún código referencia módulos por **id numérico hardcodeado**, esa divergencia podría morder. | 📝 Nota | Baja |

### Infraestructura de producción — verificación pendiente

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **Cron schedule:run en PROD** | Verificar que el servidor de producción tenga `* * * * * cd /var/www/megaisp && php artisan schedule:run` activo como `www-data`. Si está caído: billing (`invoice:create-proformas`), notificaciones de cobranza (`billing:send-pending-notifications`), CobranzaBlaster, domiciliación y backups reales no corren. | ⚠️ Verificar | Alta |
| **Permisos /var/backups/mysql en PROD** | Ajustar a `750 www-data:www-data` (sin group-write) una vez el cron esté activo. Actualmente en dev: `770` para pruebas manuales. | ⚠️ Pendiente | Media |
| **Índice unique de `plan_bundles`** | Limpiar duplicados existentes en `plan_bundles` y re-habilitar el índice unique (`plan_bundles_bundle_plan_type_unique` sobre `bundle_id, plan_bundle_id, plan_bundle_type`) en la migración `2026_06_17_145346_add_unique_to_plan_bundles.php` — hoy **deshabilitado (comentado)** porque tronaba contra datos duplicados. La idempotencia de `0dadffad` evita nuevos duplicados pero **no limpia los viejos**; depurar antes de re-activar. | ⚠️ Pendiente | Media |
| **Retención de respaldos por versión** | `storage/backup_test/{version}/{version}.zip` (el respaldo previo a cada release, hoy primer paso del pipeline de deploy) **se acumula ~135 MB por versión sin límite**. Falta política de retención/limpieza (p.ej. conservar N últimas o purgar por antigüedad), análoga a la retención de 14 días de `backup_db:process`. | ⚠️ Pendiente | Media |
| **Bug Reintentar deploy (RESUELTO)** | `DeploymentController::retry` despachaba `DeployJob::dispatch($newLog)` con un solo argumento → `Too few arguments, at least 2 expected` (`DeployJob` exige `version` obligatoria). Corregido para despachar con `(log, version, title)` tomando la version/title de la release del deploy original. | ✅ Resuelto | — |
| **Bug git_commit dependiente del idioma (RESUELTO)** | El paso `git_commit` detectaba "nada que commitear" parseando el texto inglés `nothing to commit` del output, pero el git del server responde en **español** → un árbol limpio (commits ya hechos, solo falta pushear) se trataba como **fallo** (exit 1) y cortaba el deploy. Corregido: (1) `LC_ALL=C/LANG=C` en `buildEnv()` para salida de git en inglés en todo el pipeline; (2) detección previa independiente del idioma con `git diff --cached --quiet` (stage vacío → commit `skipped` como éxito, no fallo). | ✅ Resuelto | — |
| **Bug tag lightweight no llegaba a origin (RESUELTO)** | `git_tag` creaba un tag **lightweight** (`git tag {version}`) y `git push origin main --follow-tags` solo empuja tags **anotados** → el tag nunca llegaba a GitHub (commits sí). Corregido a `git tag -a {version} -m "Release {version}"`. El tag `V1.2.1` (creado antes del fix) se subió a mano con `git push origin V1.2.1`. | ✅ Resuelto | — |

### Deuda técnica frontend — antipatrón jQuery en componentes Vue

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **Auditar `$(document).on` global en componentes Vue** | Antipatrón: handlers jQuery delegados en `document` dentro de `onMounted` con **IDs de botón compartidos** entre componentes y **sin `.off()`** → se acumulan handlers stale al navegar la SPA y un clic dispara instancias muertas. Detectado en `shared/TextTemplate.vue` y `shared/ContractTemplate.vue` (corregido ahí, commits 669a40bf + b4ba27d2). **Falta auditar el resto del codebase** por el mismo patrón. | ⚠️ Pendiente | Media |
| **Limpiar duplicación benigna de `#generateContract`** | `$(document).on("click", "#generateContract")` en `CrmTemplate.vue` y `PlantillasClientes.vue` usa el mismo antipatrón, pero **solo re-abre el modal** (sin consecuencia: NO duplica contratos). Aplicar el mismo fix (namespace + `.off()`) **junto con** la auditoría del item anterior. | ⚠️ Pendiente | Baja |

### Estado singleton module-level que se filtra entre pantallas SPA

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **Ficha de cliente mostraba datos del cliente anterior (RESUELTO)** | Navegar de la ficha de un cliente a la de otro mostraba los datos del anterior hasta F5. Causa raíz: `hook/crudHook.js` exporta `dataForm`/`fields`/`fieldsJson`/`allFields` como **singletons module-level** compartidos por ~60 CRUDs; el bundle JS **no se recarga** en navegación SPA (spa-nav solo intercambia `#init-vue` + remonta), así que arrastraban el registro previo. Agravado porque `InformationClientCrud` cargaba datos **solo en onMounted** sin `watch(props.id)`. Fix acotado al módulo cliente (commits `7041b920`+`a04da21c`): **A** — cuerpo de carga extraído a `loadClient(id)`, ejecutado en `onMounted` **y** `watch(() => props.id)`, con **token anti-race compartido** (`clientLoadToken` en `info/comun_variable.js`) que impide que una carga lenta del cliente previo pise al actual (chequea antes de escribir `dataForm`) + try/catch en los awaits que antes abortaban la carga; **B** — `resetCrudForm()` (nuevo, aditivo en `crudHook`) llamado en `ClientCrud.onUnmounted` (raíz de la ficha, **no** en el tab: `q-tab-panels` desmonta el panel inactivo). Seguro para los ~60 CRUDs: el reset corre entre vistas y cada uno repuebla `dataForm` en su propio `onMounted`. | ✅ Resuelto | — |
| **Mejora futura (NO hecha): matar la clase entera** | Opción C (reset global de estado compartido en `spa-nav` al navegar) y Opción D (refactor de los singletons de `crudHook` a un composable con estado por-instancia) eliminarían de raíz toda la familia "singleton module-level que se filtra entre pantallas". Se dejaron fuera del fix del cliente por blast radius (~60 componentes). | ⚠️ Pendiente | Media |

### FASE PAGOS — Referencia MEG visible en la ficha (Paso 1)

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **Referencia MEG en la ficha de cliente** | La referencia de pago `MEG-{id8}-{mod97}` (`client_payment_references`) ahora se muestra en el **header** de la ficha (junto a "Saldo"), solo lectura, con botón Copiar (fallback `execCommand` para http sin TLS). Backend: `getClientWithBalance` devuelve `payment_reference` vía `PaymentReferenceService::ensureFor` (idempotente, auto-crea si falta → nunca vacía). Frontend: cargada en `loadClient` (limpia al inicio, asigna tras el guard del token anti-race → se actualiza al cambiar de cliente, no se pega). Sin permiso nuevo (hereda el gating de la ficha, `client_edit_client`). Commits `871dbac1` (backend) + `a8d2bd92` (frontend). | ✅ Resuelto | — |
| **Paso 2 — Pantalla "Registrar pago reportado" (mostrador)** | Captura interna que digitaliza el flujo de Diana/Ariana: buscar cliente (nombre o referencia MEG), capturar monto/fecha/clave de rastreo/titular/banco/método (default Transferencia Bancaria id=2)/cuenta receptora + foto del comprobante. Al guardar: (a) **APLICA el pago reusando `PaymentApplicationService::applyPayment`** (mismo `payments`+observers+`PaymentClientJob` → abona saldo y sale en historial IGUAL que cualquier pago; verificado: cliente 6810 −2692→−2554.23 = +137.77 con su `transactions` credit, idempotente); (b) **registra `reported_payment`** (tabla nueva, aditiva) con comprobante + `conciliation_status='pendiente_verificar'` ligado al `payment_id`. `applyPayment` extendido con overrides opcionales retrocompatibles (method_id/add_by/date/comment) — webhook SPEI intacto. **R1**: `ReconciliationService::raise()` solo ante discrepancia real (clave duplicada). Permiso `payments_capture_manage` → super-administrator + DESARROLLADOR + **Mostrador** (Diana user 3, Ariana user 4122). Ruta `/finanzas/captura-pago`. Commits `8be82028`+`0bde23be`+`5b961e1e`. **UI verificado/rechazado de Tere = Paso 3.** ⚠️ El abono de saldo lo aplica `PaymentClientJob` (async, `QUEUE_CONNECTION=database`) → en DEV requiere worker; en PROD el worker lo procesa. | ✅ Resuelto (pend. validación visual) | — |
| **Paso 2b — Campos dinámicos por método en el modal "Crear Gasto" (ficha del cliente)** | La pantalla de RESPALDO manual que el equipo usa a diario (`resources/js/.../client/billing/payment/ClientCrudPayment.vue`, título "Crear Gasto"). **Bug base resuelto:** el campo `payment_method_id` era `select-component` → `SelectComponent.vue` que secuestra el `<select>` con **Choices.js** (`convertToSelect2`); al cambiar de método no reaccionaba (doble fuente de opciones Vue↔Choices, `watch(val)` emitía el ref, sin `watch(props.modelValue)`). **Fix local, sin tocar el `SelectComponent` compartido (~60 CRUDs, queda como deuda):** se oculta el campo del loop dinámico (`include=false`, pero sigue viajando en el POST) y se pinta un `<select>` controlado nativo. **Campos dinámicos DECLARATIVOS** (`PAYMENT_METHOD_FIELDS`, reusable por la IA): Efectivo (1, sin extra), Transferencia (2: clave SPEI/titular/banco+comprobante), Oxxo (5: referencia/folio+comprobante, monto SIN comisión), Pago a técnico (7: selector técnico `getTecnicos` roles TECNICO/_INSTALADOR/_PLANTA + foto opcional). **OpenPay (8)/Tarjeta OpenPay (9) fuera** (solo webhook). Se muestran/ocultan por método y se limpian al cambiar. Llaves de datos inyectadas en `fieldsJson` (`include:false`) para que `Form.uploadFile` las serialice sin existir en `field_modules`; el comprobante reusa la llave `file`. **Persistencia:** NO se re-apunta a `applyPayment` (se conserva `ClientPaymentController::store`→`clientCreatePayment`+`ClientPaymentMetadata`+proforma; el saldo lo abona el MISMO `PaymentObserver→PaymentClientJob` que el Paso 2). Se añade `reported_payment` ligado al `payment_id` con `conciliation_status='pendiente_verificar'` (try/catch: un fallo NO revierte el pago). Migración aditiva: `reported_payments += referencia_oxxo, tecnico_id (FK users)`. `clientCreatePayment` amplía `except()` para no meter llaves de método en `payments` (`$guarded=[]`). Commits `7e0074c5`(A)+`8728d302`(B)+`4a6065fe`(C). **Verificado en DEV** (cliente 17, worker corrido): (c) 4 métodos abonan saldo exacto (0→88.5) con su `transactions` credit e historial idéntico; (d) cada uno crea su `reported_payment` con sus campos (clave/titular/banco, referencia_oxxo, tecnico_id) en `pendiente_verificar`; (e) metadata/proforma intactos y sin fuga de llaves a `payments`. **Pendiente: smoke visual (a)(b)** — que el método cambie y los campos aparezcan/desaparezcan en el navegador. | ✅ Resuelto (pend. smoke visual) | — |
| **Paso 2c — Método "Tarjeta crédito/débito en oficina" (id=3) en los campos dinámicos** | 5º método del selector local + config declarativa `PAYMENT_METHOD_FIELDS[3]`: número de autorización (texto), últimos 4 dígitos (tipo nuevo `digits4`), comprobante/voucher (reusa `file`). **PCI (defensa en profundidad):** el campo de tarjeta es SOLO últimos 4 — nunca el PAN. Front: `maxlength=4` + `onDigits4Input` (strip no-dígitos + `slice(0,4)`, input controlado con `:value`/`@input`). Back: en `store` se sanea `ultimos4_tarjeta = substr(preg_replace('/\D/','', …), -4)` y la columna es `string(4)` → aunque llegue un PAN completo jamás se guarda >4 dígitos. Migración aditiva `reported_payments += numero_autorizacion, ultimos4_tarjeta`. Mismo flujo que 2b (extiende `store`, NO `applyPayment`; `except()` ampliado). Commits `ad23b5f5`(front)+`ff62611f`(back). **Verificado en DEV** (cliente 17): enviado PAN de 16 dígitos → guardado `1234`; método 3 crea su `reported_payment` (auth+ult4) en `pendiente_verificar`; balance abonado correcto (88.5→198.5 con la regresión de Transferencia); otros 4 métodos intactos, sin fuga a `payments`. **Pendiente: smoke visual (a)(b).** | ✅ Resuelto (pend. smoke visual) | — |
| **Paso 3 (pieza 1/4) — Usuario de sistema "MEGAISP" (trazabilidad)** | Crea la IDENTIDAD del bot para que sus acciones futuras (aplicar pagos) queden atribuidas a su nombre igual que Diana/Tere → en el historial se lee "aplicado por MEGAISP". NO construye la IA ni la aplicación de pagos aún. Migración aditiva `users += is_system` (boolean, default false) + migración idempotente (por email) del usuario `MEGAISP` (`megaisp@sistema.local`, id 4844 en DEV), `is_system=true`, `color` cian distintivo (`rgba(0,184,217,1)`) para el historial. Nombre limpio: `name='MEGAISP'` + apellidos vacíos → compuesto sin apellidos colgando. **Login IMPOSIBLE:** password inusable (bcrypt de secreto aleatorio) + `estado='inactivo'` (`LoginController::attemptLogin` exige `estado==='activo'`; `estado` es `enum('activo','bloqueado','inactivo')`, sin valor 'sistema'). `login_user` es NOT NULL → lleva valor `megaisp_sistema`, pero los dos candados anteriores bloquean (`Auth::attempt`=false). **SIN rol/permisos** (0 roles, 0 permisos): solo identidad. Resolver fiable: `User::SYSTEM_BOT_EMAIL` + `User::systemBot()`. Autor vía `payments.add_by`→`getClientNameWithFathersNamesAttribute`. Commits `6f73e544` (creación inicial como "Asistente IA") + `c796f373` (rename a MEGAISP + systemBot). Verificado en DEV (existe/is_system/color; no loguea; systemBot() resuelve; 0 permisos). | ✅ Resuelto | — |
| **Paso 3 (pieza 2/4) — Extracción de datos de comprobante SPEI por IA** | "Cerebro que lee" AISLADO: SOLO extrae datos de una imagen y los devuelve estructurados. NO aplica pagos, NO busca cliente, NO WhatsApp. Motor `PaymentReceiptExtractor` (`Payments/Services/Extraction/`) reusa `ClaudeApiClient` + patrón de visión de Talento (base64 + bloque `image`), modelo `env('CLAUDE_MODEL','claude-sonnet-4-6')`. **Extensible por perfiles**: `ReceiptProfileInterface` + `SpeiTransferProfile` (único ahora); agregar Oxxo/CEP = sumar un perfil, `documentType` es parámetro. Salida estándar: `fields{value,confidence:alta/media/baja}`, `unreadable[]`, `ok`, `error`, `raw`. **Prompt anti-invención estricto**: nunca inventa; campo no claro → `value:null`+`confidence:baja`; monto y clave_rastreo críticos → cualquier duda = baja. Parseo robusto (extrae JSON aun con fences ```json```; API caída/JSON malformado → `ok:false`+mensaje, jamás inventa). **Pantalla de prueba aislada** (Irving): blade standalone `addon-payments::extraccion-comprobante` en `/finanzas/extraccion-comprobante`, gateada por rol `super-administrator\|DESARROLLADOR` (middleware Spatie `role`), muestra campos+confianza+ilegibles+error+`raw`; NO aplica nada. Commits `b20c4764` (motor) + `add0833c` (pantalla). **Verificado en DEV con imágenes sintéticas**: (1) comprobante legible → 5 campos correctos, confianza `alta`; (2) imagen no-comprobante → todos `null`/`baja`, `unreadable` completo, **cero datos inventados**. Nota: `CLAUDE_API_KEY` está en `.env` de DEV; se limpió la caché de rutas para exponer la ruta nueva. | ✅ Resuelto | — |
| **Paso 3 — PENDIENTE (pieza de cableado): reapuntar la autoría de pagos automáticos a MEGAISP** | Hoy `PaymentApplicationService::resolveSystemUserId()` busca el rol `SUPER_ADMIN` (que NO existe; los reales son `super-administrator`/`Mostrador`/`DESARROLLADOR`) → cae al **fallback `user id 1` = "Admin"**, así que los pagos del webhook SPEI/OpenPay se ven como "Admin". En la pieza donde la IA aplique pagos: apuntar `add_by` a `User::systemBot()` y darle SOLO el permiso de aplicar pagos. NO se tocó en la pieza 1. | ⏳ Pendiente | Media |
| **Conciliación WhatsApp · F1 — Descarga del binario de imagen entrante** | Primer ladrillo de la conciliación de pagos por IA: bajar el archivo del comprobante que llega por WhatsApp (antes solo se guardaba el payload cifrado en `marketing_messages.metadata`). **3 ladrillos aditivos sobre la integración Marketing viva, sin tocar el flujo de ventas:** (1) `EvolutionApiService::getBase64FromMediaMessage(array $data)` → `POST /chat/getBase64FromMediaMessage/{instance}`. ⚠️ Evolution v2 exige el mensaje **COMPLETO** (`key`+`message`), no `key.id` solo (internamente lee `message.ephemeralMessage` → 400 sin él). + migración aditiva `marketing_messages += media_path, media_downloaded_at` (no toca `media_paths` json existente sin uso). (2) `DownloadWhatsAppMediaJob` aislado en cola `default`, **idempotente** (guarda si `media_downloaded_at` null), valida mime (`jpeg/png/webp/pdf`, reportado + sniff por firma) y tamaño (≤8MB), guarda en disco `local` privado `private/payments/whatsapp/comprobantes/` (patrón de comprobantes de mostrador). (3) enganche en `ProcessIncomingMessageJob` **paso 5b**, solo `content_type` image/document, `try/catch` best-effort — las ramas texto/IA/outbound quedan **byte-idénticas**. **Verificado:** contra Evolution vivo (msg real 771 → JPEG 53,989 bytes, magic `ffd8ff`); `Queue::fake`+rollback → TEXTO despacha `SendOutboundMessageJob` (bot responde) y **no** descarga, IMAGEN despacha `DownloadWhatsAppMediaJob`; idempotencia (2ª corrida no re-baja); worker `default` vivo; msg 771 restaurado. Commits `75f35727`+`9b82cac7`+`3b22cff8`. **+ compresión moderada de la copia archivada** (`f391067a`): `compressForArchive()` = raster→JPEG **q80, cap 1600px** (PDF tal cual; fallo GD→archiva original); **guarda anti-inflado** (WhatsApp ya comprime del lado servidor → si re-encodear no reduce, conserva el original; evita inflar imágenes ya optimizadas). **Una sola copia** comprimida (no dos niveles): la IA (`PaymentReceiptExtractor` real) lee el q80 **idéntico** al original — `clave_rastreo` (27 chars) + `monto` confianza `alta` en screenshot 1080px (163.9→85.6 KB) y foto 2400px con downscale a 1600 (313.8→88.6 KB); foto grande 442→77 KB (−82%). **Verificado end-to-end en dev** (2026-07-02): foto real (msg 792, BBVA) y PDF real (msg 794, Banamex 2 págs) → llegó→descargó (Evolution 201)→guardó (archivo escrito por `www-data`, JPEG/PDF válido). Requirió `chmod g+rwx` en `storage/app/private/payments` (la carpeta la creó `meganet` en pruebas → worker `www-data` no escribía; artefacto solo-dev, en prod la crea www-data). | ✅ **Resuelto** (DEV verificado img+PDF) | — |
| **Conciliación WhatsApp · F2 — Lectura del comprobante por IA (imagen Y PDF)** | Cablea el comprobante descargado (F1) al `PaymentReceiptExtractor`. **Disparo SOLO manual** (nada automático: no gastar IA en cada foto de cliente; automatizar cuando exista pre-filtro "parece comprobante"). **PDF por camino A** = Claude lee PDF **nativo** (bloque `document`), sin conversión ni `gs`/`pdftoppm` (probado: PDF 2 págs, encuentra el dato en cualquier página; los PDF de comprobante NO tienen capa de texto → se necesita visión igual). 3 sub-pasos: **(1)** `PaymentReceiptExtractor` bi-formato — `mediaBlock()` ramifica por mime (`application/pdf`→`document`, imagen→`image`); `ClaudeApiClient` **sin cambios** (passthrough). **(2)** tabla dedicada **`whatsapp_payment_extractions`** (`message_id`+`conversation_id`, `fields`/`unreadable` json, `ok`/`error`/`model`/`raw`/`extracted_by`; append/auditoría — **no ensucia** `marketing_messages`) + modelo. **(3)** pantalla **`/finanzas/whatsapp-comprobantes`** (gate rol `super-administrator\|DESARROLLADOR`): lista comprobantes F1 + preview (img / iframe PDF) + botón **"Extraer con IA"** → campos+confianza+ilegibles+raw; `media()`/`extract()` acotados a `private/payments/whatsapp/comprobantes/` + `findOrFail` inbound con `media_path`. **ALCANCE ESTRICTO: solo lee/extrae/muestra — NO aplica pago, NO identifica cliente, NO responde por WhatsApp** (F3-F4); no toca el flujo de ventas. Verificado end-to-end (auth super-admin): 792(img)=BBVA $350, 794(pdf 2 págs)=Banamex $549, ambos clave/monto/fecha `alta`; fila persistida (mime/campos/model/by); `media` 200. Commits `1417f07b`+`ff699cf7`+`dde77d1e`. **+ campo `concepto`** (`3267f8f1`+`3a219c76`+`bf6f3b0b`): el nombre del cliente a veces está en el **concepto/referencia** que escribe el pagador, no en el titular de la cuenta. `SpeiTransferProfile` ahora extrae **6 campos** (titular y concepto **por separado**, prompt anti-copia); el prompt avisa que el concepto puede traer la ref **MEG-XXXXXXXX-XX** y pide copiarla tal cual (clave para F3); columna dedicada indexada `whatsapp_payment_extractions.concepto` (F3 buscará ahí); etiquetas legibles en la pantalla. Verificado: 792→`concepto="LUCILA FRANCISCO FLO"`, 794→`concepto="jose francisco gomez reyna"` (ambos `alta`, antes salían ilegibles en titular). | ✅ **Resuelto** (DEV, pend. validación visual en navegador) | — |
| **Conciliación WhatsApp · F4 — Aplicar el pago identificado como MEGAISP** | Aplica el pago de una sesión F3 resuelta. **REUSA `applyPayment`** (Paso 2): abona saldo idéntico, `add_by=MEGAISP` (user 4844), método 2 (Transferencia), `external_id=clave`. **Dos puertas:** exact/MEG → candidato a **auto** (solo si el freno maestro `config('payments.auto_apply_enabled')` está ON, default **false**); proposed (nombre/calle) y **multi-servicio** → **confirma Tere** (Fase 6). **3 CANDADOS anti-duplicado** (dinero): (1) `clave_rastreo` única (`payments.number`/`reported_payments.clave_rastreo`); (2) **claim atómico** `UPDATE applied_at WHERE null` (gana uno; libera si falla); (3) sesión única por mensaje (F3). **Piezas:** F4.1 MEGAISP recibe **solo** `payments_capture_manage`; F4.2 `reported_payments += identified_by_user_id/confirmed_by_user_id/identification_session_id`; F4.3 `PaymentFromSessionService::apply()`; F4.4 `ApplyIdentifiedPaymentJob` (bifurca; freno se evalúa **después** de proposed/multi → proposed siempre va a Tere) + `enqueueForTere` (reusa `reconciliation_tickets`) + comando `payments:apply-identified`; F4.5 `applyConfirmed(sessionId, tereUserId)` para F6 (no lo frena el flag); F4.6 verificado con dinero **real en cliente 17 + transacción rollback** (saldo intacto): auto→`add_by`/`identified_by`=MEGAISP, `confirmed_by`=null; idempotencia `already_applied`; `duplicate_clave`; confirmado→`confirmed_by`=Tere. Commits `5e92cd11`+`617e8131`+`e1340dd1`+`d4a4457a`+`61a248dd`. **Notificación WhatsApp sigue OFF** (`wa_autorespond=false`): F4 aplica pero no avisa al cliente. **Activar:** `PAYMENTS_AUTO_APPLY_ENABLED=true` cuando Irving decida. **⚠️ DEUDA separada (NO tocar en F4, punto F):** `PaymentApplicationService::resolveSystemUserId()` busca rol `SUPER_ADMIN` inexistente → pagos del **webhook SPEI/OpenPay** caen a **Admin id 1** en vez de MEGAISP; arreglar aparte (solo atribución `add_by`, ya funciona). | ✅ **Resuelto** (DEV, freno maestro OFF; pend. activación + validación live de Irving) | — |
| **Conciliación WhatsApp · F3 (F3.1–F3.4) — Identificación conversacional del cliente** | Identifica al dueño de un comprobante conversando; **carril SEPARADO del bot de ventas** (`AiAgentService` intacto). Decisiones: detección híbrida (imagen/PDF=probable, texto "ya pagué"+ya-cliente=intención); sesión **12h configurable** (`reconciliation_session_hours`), expira+responde tarde→escala; confirmación humana por nombre **reusa** cola de conciliación existente (`reported_payments`/`reconciliation_tickets`); **2 reintentos→`assign_to_human` (Tere)**; educar MEG tras identificar por nombre. **Contrato para F4:** `whatsapp_identification_sessions.certainty` = **`exact`** (MEG→auto-aplicable) \| **`proposed`** (nombre→confirma humano). **Piezas:** F3.1 tabla+modelo (state `detecting→awaiting_name→awaiting_service→resolved\|escalated`, method/certainty, attempts, expires_at, `resolved_client_id`=puente al `clients` real, is_simulation); F3.2 `MegReferenceResolver` (regex MEG→`client_payment_references`, tolera padding/espacios; la existencia en tabla es la verificación→no inventa) + `SubscriberSearchService` (nombre sobre `client_main_information` activos, normaliza sin acentos/ruido, tolera truncamiento del banco por prefijo `FLO~FLORES`, `classify()` single\|multiple\|none por match completo, adjunta servicios bundle+custom+colonia para desambiguar; teléfono solo desempata); F3.3 `IdentificationFsm` (transiciones puras, no envía); F3.4 **simulador** `/finanzas/conciliacion-sim` (gate `super-administrator\|DESARROLLADOR`, sesiones `is_simulation` que F4 ignora, **cero Evolution**, recordatorio acelerable + expiración simulable + cierre amable). Verificado con datos reales: MEG→cli17 exact; MARIA…CRUZ LOPEZ→single cli17; AXEL DALI MARTINEZ MENDOZA→homónimos `[4077,4369,6362]`→reply 2→cli4369 proposed; truncado LUCILA…FLO→cli2036; basura→escala. Commits `672cccaf`+`28710e55`+`0e3d76cb`+`f989174a`. **Falta F3.5** (disparo desde pantalla F2 + routing aditivo guardado en `ProcessIncomingMessageJob` — único toque a ventas) **+ F3.6** (escalar a Tere reusa `AssignToHumanTool`) **+ F3.7** (doc contrato F4). | ⏳ **En progreso** (F3.1–F3.4 DEV, pend. validación de Irving en el simulador) | — |

### Seguridad del pipeline de release — staging amplio (RIESGO)

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **Pipeline de release hace `git add -A` amplio del working tree** | El paso `git_add` (`config/deployment.php`) ejecuta `git add -A` → barre **cualquier** archivo modificado/sin trackear del working tree al commit de release y lo empuja a `origin/main` (`git push origin main --follow-tags`). Evidencia real: V1.2.1 (`d472f1f5`) barrió `CLAUDE.md` y `public/js/app.js` que estaban sin commitear. El guard `executeSecretCheck` (`DeploymentService.php`) es **denylist** (solo `.env*`, `.pem`, `.key`, `credential`, `secret*.{json,yaml,yml,txt}`) → NO cubre `public/csv/`, backups `.sql/.gz`, `id_rsa`, `.p12/.crt`, `.token`, ni archivos arbitrarios. **Riesgo: un secreto o export de `public/csv/` sin commitear se empujaría a `origin/main`.** Migrar staging de denylist a **allowlist** (que el pipeline solo agregue los artefactos que él mismo construye/versiona, nunca `git add -A`). | ✅ **Resuelto** (commits c959e021 + 26d85aa1): paso `git_staging_gate` (allowlist, defensa primaria) + `git_add` explícito de 4 rutas; denylist conservado como 2ª capa | **Alta** |
| **`mix-manifest.json` gitignored no se despliega → cache-busting stale** | `public/mix-manifest.json` está en `.gitignore:24` y no se trackea, pero la app usa `mix()` en runtime (`head.blade.php`, `vendor-scripts.blade.php`). El remoto (`git checkout`, sin npm) no lo regenera ni lo borra → usa un manifest **manual stale** → el `?id=` de cache-busting queda viejo → los navegadores sirven el **JS cacheado anterior** tras cada deploy. | ✅ **Resuelto** (commit `6e014b77`): el bundle compilado (`app.js`, chunks, `app.css`, `mix-manifest.json`) salió de git y ahora se genera **en el servidor** con `npm run prod` (paso `npm_build` del deploy remoto). El `$releaseArtifacts` quedó solo en `chart.js` + `images/vendor` (estáticos que cambian rara vez). | **Alta** |
| **`config:cache` stale enmascaraba `config/deployment.php` → `git_add` fallaba** | El deploy V1.7 falló en `git_add` con `public/mix-manifest.json is ignored`: el `bootstrap/cache/config.php` traía el allowlist **viejo** (de cuando mix-manifest estaba des-ignorado, commit `3513eb16`), pero el código fuente ya lo había sacado (`6e014b77`). El cache nunca se regeneró → el pipeline usó la config stale. | ✅ **Resuelto**: `DeploymentService::run()` ahora corre `refreshDeploymentConfig()` **antes** de leer `config('deployment.steps')` — `config:cache` en subproceso (env correcto) + recarga de la sección `deployment` en memoria del proceso actual. Se muestra como primer paso `config_cache` en el log. Protege la corrida actual, no solo la siguiente (un step shell normal correría demasiado tarde). | **Alta** |

### Deploy remoto (`remote:deploy`) — orden de pasos y dry-run de migraciones (2026-06-30)

**Orden actual del deploy en prod** (`RemoteDeployCommand`, consumidor; se dispara por webhook o por el botón "Buscar actualizaciones" → `UpdateController::apply`, que en prod corre en `sync` lanzando `nohup php artisan remote:deploy {logId} … > storage/logs/self-update-{id}.log`):

1. `backup_db` (crítico) — `backup_db:process`
2. `git_sync` (crítico) — `git checkout tags/{version}` (trae código + migraciones nuevas)
3. **`migrate_dryrun` (crítico)** — `php artisan deploy:dry-run-migrations` (timeout **900s**, alineado con `migrate`)
4. `npm_build` (crítico) — `npm ci && npm run prod`
5. `migrate` (**fatal-sin-rollback**, timeout **900s**) — `php artisan migrate --force` (subproceso, NO `Artisan::call`: el `--force` programático no salta el prompt de prod en contexto nohup → se cuelga)
6. `optimize` · 7. `queue_restart` · 8. `save_release`

**Reglas de orden que NO se deben romper:**
- `migrate` es el **punto de no retorno**: el rollback (`performRollback`) solo hace `git reset --hard` del **código**, la BD **no se restaura sola**. Por eso TODOS los pasos críticos abortables (`backup`/`git`/`dryrun`/`npm`) van **antes** de `migrate`. **No mover `npm_build` después de `migrate`** (un fallo de build dejaría esquema nuevo + código viejo).
- Un cambio al propio `remote:deploy` recién surte efecto en el deploy **siguiente** (PHP ya cargó el comando viejo en memoria antes del checkout). Las **migraciones** sí se aplican del checkout nuevo (corren por subproceso).

**`deploy:dry-run-migrations`** (`app/Console/Commands/Active/DryRunMigrationsCommand.php`): corre las migraciones **pendientes** contra una copia desechable `{db}_dryrun`. Esquema-only por defecto (mysqldump `--no-data` + copia de la tabla `migrations`, segundos) → caza fallos **estructurales**. Para fallos **dependientes de datos** (unique contra duplicados como el de `plan_bundles`, `data-too-long` al achicar, FK): `--with-data=tabla1,tabla2` copia solo esas tablas. Usa el **Migrator** directo (no el comando `migrate`) para no colgarse en el prompt de prod, y restaura la conexión por defecto en `finally`.
- **Modo de fallo:** si **falla una migración** → exit 1 → **aborta** el deploy (rollback limpio). Si **no se puede construir el sandbox** (privilegio/infra) → warning ruidoso + exit 0 → **NO bloquea** (cae al migrate real). Así un grant faltante nunca brickea todos los deploys.
- ⚠️ **PRE-REQUISITO de infra (correr una vez como root en dev Y en prod):** el usuario MySQL de la app necesita poder crear/borrar `{db}_dryrun`. Mientras no esté, el dry-run solo se **omite con warning** (no protege):
  ```sql
  GRANT ALL PRIVILEGES ON `megaisp_dryrun`.* TO 'megaisp_user'@'localhost'; FLUSH PRIVILEGES;
  ```
  (En prod ajustar usuario/host reales.) **No se puede hacer dry-run sobre la BD viva** con transacción: en MySQL los `ALTER`/`CREATE INDEX` hacen commit implícito.

**Bug `save_release` (RESUELTO 2026-06-30):** `releases.summary` era `VARCHAR(255)` y las notas de release generadas por IA lo desbordaban → `SQLSTATE[22001] 1406` en el paso final. Peor: `saveRelease()` corría **sin try/catch** pese a ser no-crítico → la excepción mataba el comando y dejaba el `DeploymentLog` en `running` para siempre (el UI solo hace polling → "no se ve nada"). Fix (commit `56ca3e20`): migración `summary → TEXT` (corre en el paso `migrate`, **antes** de `save_release`, así el deploy se auto-sana) + try/catch en el paso inline (un fallo ahí marca solo ese paso `failed` y el deploy igual cierra `success`).

**Fix `migrate` fallido cerraba `success` (RESUELTO 2026-07-01, commit `7d3cde25`):** `migrate` era `critical=false`, así que un fallo (típicamente **timeout**) marcaba el paso `failed` pero el `foreach` seguía y el cierre ponía `status='success'` **incondicional** → una migración que timeouteaba se reportaba como deploy exitoso. Además el timeout del `migrate` real (300s) era **mayor** que el del `migrate_dryrun` (180s) → una migración lenta pasaba el dry-run y timeouteaba el real. Fix (decisión de Irving — opción 1: FAILED + alerta, **SIN** revertir): (1) flag nuevo `'fail_deploy_no_rollback'=>true` en `migrate` → al fallar marca `$deployFailed` y el loop cierra en `status='failed'` con `error_message`, **SIN** `performRollback` (el esquema pudo modificarse a medias; un `git reset` sería peligroso — **NO se reintroduce** el rollback de migrate); (2) `migrate` 300s→**900s** y `migrate_dryrun` 180s→**900s** (alineados). **NO se tocó** la rama `critical` (backup/git_sync/dryrun/npm siguen con `performRollback`+`rolled_back` idéntico) ni los pasos decorativos (`optimize`/`queue_restart`/`save_release`, que siguen corriendo aunque migrate falle → deploy sigue `failed` solo por migrate). `runShell`/`runArtisan` pasaron a `protected` solo para habilitar testeo. Verificado contra la clase real (subclase inyectando exit-codes): (a) migrate OK→`success`; (b) migrate falla→`failed` sin rollback, código intacto; (c) `git_sync` critical falla→`rolled_back` con rollback (intacto). ⚠️ Como todo cambio a `remote:deploy`, surte efecto en el deploy **siguiente** (por tag).

### Portal Cliente — estado al 2026-06-15

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **OpenPay producción** | Completar certificación + cambiar llaves en `.env` + `OPENPAY_SANDBOX=false` | ⏳ Config | Alta |
| **Webhook OpenPay** | Activar URL en dashboard OpenPay al publicar subdominio/SSL | ⏳ Config | Alta |
| Portal: subdominio + SSL | Configurar `portal.meganet.mx` con nginx + certbot | ⏳ Pendiente | Alta |
| Portal: CFDI timbrado | Generar PDF/XML de facturas fiscales desde el portal | ⏳ Pendiente | Media |
| Portal: cobro/tarifas premium MegaFamilia | Planes de pago MegaFamilia vía OpenPay | ⏳ Pendiente | Media |
| Portal: funciones cliente MegaFamilia | Perfiles, dispositivos, stats dentro del portal | ⏳ Pendiente | Media |
| Portal: notificación pago por email | Enviar recibo al email del cliente tras pago OpenPay | ⏳ Pendiente | Media |
| Portal: Flotas para cliente | Scope por `fleet_vehicles.client_id` y tracking | ⏳ Pendiente | Baja |
| Admin: migración base64 → bcrypt | `users.password` de base64 a bcrypt (admin interno) | ⏳ Pendiente | Baja |

### Portal Cliente — implementado y cerrado

| Item | Commits |
|------|---------|
| ✅ Guard `cliente` + auto-registro (originalmente bcrypt/`portal_password`; el login fue reapuntado a `password` plano en Fase 2 — ver sección **Autenticación** arriba) | fc13436 |
| ✅ Facturas (lectura, solo propias, anti-IDOR 404) | fc13436 |
| ✅ Pagos + CLABE (lectura) | fc13436 |
| ✅ Tickets (lectura + crear + responder) | fc13436 |
| ✅ Consumo (fix dual-pattern Meganet{id}/Meganet__{id}) | fc13436 |
| ✅ MegaFamilia activable (hardened para 878 CMI sin users) | 5f2cf26 |
| ✅ Tests aislamiento (11 tests, DatabaseTransactions, sin migrate:fresh) | fc13436 |
| ✅ Editar perfil de contacto con audit trail (portal_profile_change_log) | 0329a1d |
| ✅ UX: estados vacíos en todas las vistas, responsive móvil, dark mode badges | c830944 |
| ✅ Tests auth (16 tests: login/registro/recuperar/rate-limit/anti-enum/guard) | 9340008 |
| ✅ OpenPay sandbox: SDK+config+OpenpayService+portal_payment_attempts | 8bade1d |
| ✅ OpenPay: modal tokenización en navegador (openpay.js, device_session_id) | dbdef17 |
| ✅ OpenPay: cargo síncrono + scope + idempotencia + write-back a payments | f167d90 |
| ✅ OpenPay: webhook de conciliación (listo, pendiente URL en dashboard) | e448008 |
| ✅ OpenPay: botón Pagar activo en facturas pendientes (sandbox) | 7f44adf |
| ✅ Tests OpenPay (6 tests: scope/idempotencia/completed/failed/pagada/auth) | 796513f |

### IMPORTANTE — Para pasar a producción (solo configuración, sin código nuevo)

1. Completar certificación de sitio en dashboard.openpay.mx (actualmente al ~35%)
2. Sustituir llaves sandbox por las de producción en `.env` (solo modificar `.env`)
3. Cambiar `OPENPAY_SANDBOX=false` en `.env`
4. Publicar `portal.meganet.mx` con nginx + certbot
5. Configurar webhook en dashboard OpenPay: `https://portal.meganet.mx/portal/openpay/webhook`

---

## FIXES APLICADOS (no revertir)

| Archivo | Fix |
|---|---|
| `resources/js/bootstrap.js` | X-CSRF-TOKEN header en axios |
| `resources/js/components/DevtoolsPanel.vue` | position relative + height calc(100vh - 70px) |
| `app/Modules/Core/Layout/views/sidebar.blade.php` | MegaFamilia + Desarrollador agregados |
| `app/Modules/Addons/DevTools/views/index.blade.php` | `master-without-nav` → `master` |

---

## NGINX

- `/etc/nginx/sites-enabled/megaisp.conf` → puerto 80, **activo**
- `/etc/nginx/sites-enabled/meganet.conf` → **deshabilitado**

---

## COMANDOS FRECUENTES

```bash
# Frontend
npm run dev          # build único
npm run watch        # rebuild en cambios
npm run watch-poll   # cuando inotify no funciona (Docker/WSL)
npm run prod         # producción (versioning + drop_console)

# Backend
php artisan migrate
php artisan test
php artisan schedule:run   # cron lo llama cada minuto en prod
```

**Caveat tests:** `TestCase.php` corre `migrate:fresh --seed` en cada test — usar DB separada (`APP_ENV=test`), nunca dev/prod.

---

## INTEGRACIONES EXTERNAS

- **MikroTik RouterOS** via `pear2/net_routeros` → `MikrotikService` (toggle: `ROUTER_LOCAL`, `CONECTION_MIKROTIK`)
- **FreeRADIUS** — segunda conexión DB opcional (`DB_RADIUS_*`)
- **OLT / SmartOLT** → `app/Services/OLTsService.php`
- **Asterisk AMI** para VoIP (`AMI_*` env)
- **Google Maps + Leaflet** (`MIX_VUE_APP_GOOGLEMAPS_KEY`)
- **WhatsApp Evolution API** (`WHATSAPP_API_*` env)
- **Claude API** → `IAChatController` (`CLAUDE_API_KEY`, `CLAUDE_MODEL`)

---

## CONVENCIONES

- Vocabulario de dominio en español: `cliente`, `pago`, `factura`, `vendedor`, `morosos`, `red`, `caja`, `colonia`
- Al agregar endpoint: registrar permiso correspondiente en tabla `permissions`
- No saltear la capa repositorio para queries no triviales
- `BaseModel` auto-llena `created_by`/`updated_by` — no setear manualmente
- Después de cambiar `webpack.mix.js` o agregar componente Vue: `npm run dev`

---

## SCHEDULING Y JOBS EN BACKGROUND

`app/Console/Kernel.php` hace dos cosas:
1. **Schedule dinámico desde DB** — lee `command_configs` via `CommandConfigRepository`
2. **Schedule hard-coded** — jobs críticos: `invoice:create-proformas` (03:00), `mikrotik:sync` (5m), `smartolt:sync-critical` (10m), `activitylog:archive` (02:00 diario), `backup_db:process` (02:00 diario)

Comandos en: `app/Console/Commands/Active/`, `Commands/Olts/`, `Commands/Scripts/` (los de Scripts son one-off, algunos destructivos — revisar antes de correr).

### Backup de base de datos (`backup_db:process`)
- **Ruta:** `/var/backups/mysql/megaisp-YmdHi.sql.gz` (mysqldump → gzip nativo vía Symfony\Process, socket `--host=localhost`)
- **Credenciales:** `config('database.connections.mysql.*')`, nunca `env()` directo
- **Validación:** `gzip -t` + tamaño > 0 tras cada dump; si falla → `Log::channel('backup')->error()` + salida con `$this->error()`
- **Retención:** 14 días; archivos `megaisp-*.sql.gz` con `filemtime` anterior al corte se eliminan automáticamente
- **Log propio:** `storage/logs/backup-db-YYYY-MM-DD.log` (canal `backup`, daily, 30 días)
- **Tamaño de referencia (2026-06-17):** ~140 MB comprimido

### ⚠️ Requisito de producción — cron de schedule:run
**En desarrollo NO hay cron activo** (se corre manualmente o se prueba con `php artisan schedule:run`).

**Al pasar a producción**, el administrador del servidor debe instalar el cron con privilegios correctos:
```
# Agregar a crontab de www-data (sudo crontab -u www-data -e):
* * * * * cd /var/www/megaisp && php artisan schedule:run >> /dev/null 2>&1
```

**Permisos de producción para /var/backups/mysql:**
```bash
sudo chown www-data:www-data /var/backups/mysql
sudo chmod 750 /var/backups/mysql   # solo www-data escribe; sin group-write
```
(En desarrollo se usa 770 para que el usuario `meganet` pueda correr pruebas manuales. En producción, el cron es el único escritor → 750.)

---

## GEO DATA

Tablas `states`, `municipalities`, `colonies` populadas desde dumps SQL en `config/state_municipalities_and_colonies/`. Al importar dump nuevo: la tabla `colonies` requiere `ALTER TABLE` en vez de `CREATE TABLE`, y remover `PRIMARY KEY (id)` explícito — ver `README_DOC.md`.

---

## MÓDULO MARKETING (`app/Modules/Addons/Marketing/` + `app/Models/Marketing/` + `app/Http/Controllers/Marketing/`)

### Estado al 2026-05-28
| Fase | Contenido | Estado |
|------|-----------|--------|
| 1 — Fundación BD | 18 tablas `marketing_*`, 18 modelos, seeders, 42 permisos, módulo id=129 | ✅ Commit 3f77c69 |
| 2 — Captura de leads | Webhook Meta Ads (HMAC), formulario embebible, LeadScoringService (Claude), LeadObserver | ✅ Commit 3f77c69 |
| 3 — Agente IA + WhatsApp | Evolution API v2, 5 tools, ConversationUI, 44 tests | ✅ Funcionando en prod (bot respondió clientes reales) |
| 4 — Motor de Video MVM | FFmpeg v5.1.9, 8 plantillas, Brand Kit UI, logo upload, overrides por video | ✅ Video generado, 22/22 tests |
| 4.5a — Integration Hub | 3 tablas `api_integrations_*`, 6 providers, Trait con fallback 3 niveles, Vue UI `/integraciones` | ✅ Operativo |
| 4.5b — Director Creativo IA | 6 nichos, CreativeDirectorService, KineticTextRenderer, BrollLibraryService | ⚠️ Código completo, bugs pendientes (ver abajo) |
| 5 — Publicador multicanal | FB + IG + WhatsApp Status + Email | ⏸️ Pendiente |
| 6 — Blaster cobranzas | TTS híbrido (fragmentos fijos pro + slots variables cacheados) | ⏸️ Pendiente |
| 7 — CRM + Analytics + Multi-tenant | Vue Kanban, atribución, company_id | ⏸️ Pendiente |

### Arquitectura híbrida (IMPORTANTE)
- Fases 1-2: `app/Models/Marketing/` + `app/Http/Controllers/Marketing/` (standard Laravel)
- Fases 3-4.5b: `app/Modules/Addons/Marketing/` (modular)
- **NO unificar por ahora** — ambas conviven sin problema

### WhatsApp / Evolution API
- Instance: `meganet-ventas`
- Número: `5215568175643`
- Webhook URL: `http://192.168.105.11/webhooks/marketing/evolution`
- Evolution corre en Docker, puerto 8080
- **Bug conocido**: al borrar y recrear la instance, la sesión queda en conflict (`device_removed`). Fix: DELETE `/instance/logout/` → DELETE `/instance/delete/` → POST `/instance/create` → escanear QR limpio
- **Bug conocido**: modal de Pareo QR en UI no renderiza imagen (solo parpadea). Workaround: `curl /instance/connect/meganet-ventas` → extraer base64 → copiar PNG a `public/qr-temp.png` → borrar después

### Motor de Video MVM — Bugs en Fase 4.5b (pendientes de fix)
1. **ENUM faltante**: `marketing_assets.type` no incluía `'broll'` → migración `2026_05_28_300001_add_broll_to_marketing_assets_type_enum.php` creada pero posiblemente colgada por lock. Verificar:
   ```sql
   SHOW COLUMNS FROM marketing_assets WHERE Field='type';
   -- Debe terminar con ,'broll')
   ```
2. **Sin B-roll descargado**: `BrollLibraryService` necesita Pexels API key en Integration Hub. Sin ella cae a fondo de color sólido. Pexels es gratis en `pexels.com/api`.
3. **Voz del nicho gamer**: cae a `nova` (femenina) en lugar de `echo`/`onyx`. Bug en mapeo `preferred_voice_id` del nicho.
4. **Teléfono mal pronunciado**: OpenAI TTS lee números corridos. Solución pendiente: preprocesamiento con pausas SSML o espacios entre dígitos.
5. **Bug validador Hub**: retorna `valid: false` pero el ternario en tinker lo invierte. El campo `last_validation_status` es el autoritativo.

### Integration Hub (`/integraciones`)
- Tabla `api_integrations`, `api_integration_logs`, `api_integration_usage`
- 6 providers configurados: `anthropic`, `anthropic-legacy` (inactivo), `openai`, `evolution`, `pexels`, `google-maps`
- Trait `UsesApiIntegration` aplicado a: `ClaudeApiClient`, `EvolutionApiService`, `OpenAiTtsDriver`
- Fallback order: Hub → `.env` → `marketing_settings`
- **NUNCA actualizar keys con `sed` inline** — usar `read -s` o editor interactivo

### Modelos Claude válidos (2026-05-28)
- `claude-opus-4-7` ✅ — usar este
- `claude-sonnet-4-6` ✅ — alternativa
- `claude-opus-4-8` ❌ — NO existe, CC lo inventó en un momento

### Procedimiento seguro para API keys
```bash
# SIEMPRE así, nunca con la key inline en el comando:
read -s -p "Pega tu key y presiona Enter: " NEWKEY
echo ""
# Validar contra el endpoint antes de guardar
# Luego guardar vía tinker usando env() o el valor ya en .env
unset NEWKEY
```

### Tests PHPUnit — WARNING
`TestCase.php` corre `migrate:fresh --seed` que destruye datos de producción.
Tests escritos pero NO ejecutar sin `APP_ENV=testing` + `DB_DATABASE=megaisp_test`.
```bash
mysql -e "CREATE DATABASE megaisp_test;"
cp .env .env.testing
sed -i 's/DB_DATABASE=megaisp$/DB_DATABASE=megaisp_test/' .env.testing
```

### Queue workers (supervisor)
```
/etc/supervisor/conf.d/megaisp-queue.conf       → default queue (bot WhatsApp)
/etc/supervisor/conf.d/megaisp-video-render.conf → video-render queue (FFmpeg jobs, timeout=660)
```
Sin el worker `video-render`, los renders quedan en `pending` para siempre.

### Archivos temporales a limpiar
```bash
rm -f /var/www/megaisp/public/qr-temp.png
rm -f /var/www/megaisp/public/qr-scan.php
rm -f /var/www/megaisp/public/test-*.mp4
```

### Blaster de cobranzas — diseño aprobado (Fase 6)
- Template con slots: `Estimado {B}, su saldo vencido... liquide antes del {C}... número cliente {D}`
- Fragmentos fijos → TTS pro OpenAI (una vez por template, cacheado)
- Slots variables (nombre, fecha, número cliente) → TTS pro con cache por valor único
- Costo estimado: ~$21 USD/mes para 10,000 llamadas/día con cache agresivo
- Integración Asterisk AMI (ya parcialmente presente en repo)

---

## MÓDULO COBRANZA BLASTER (`app/Modules/Addons/CobranzaBlaster/`)

### Estado al 2026-05-29
| Componente | Estado |
|------------|--------|
| Asterisk 20.19.0 | ✅ `/usr/sbin/asterisk`, servicio systemd activo |
| AMI puerto 5038 | ✅ `127.0.0.1:5038`, usuario `megaisp` |
| 4 tablas BD | ✅ `cobranza_campanas`, `cobranza_llamadas`, `cobranza_llamada_eventos`, `voip_configuracion` |
| AmiConnectionService | ✅ `Services/AmiConnectionService.php` |
| CobranzaTtsService | ✅ OpenAI TTS → WAV 8kHz mono para Asterisk |
| BlastCampanaJob | ✅ queue: `cobranza`, lotes de 50 llamadas |
| ProcessCallResultJob | ✅ procesa eventos AMI → actualiza estados |
| CobranzaCampanaService | ✅ carga morosos, activa/pausa campañas |
| Queue workers | ✅ supervisor: `megaisp-cobranza` x2 RUNNING |
| Cron | ✅ cada 5 min en `Kernel.php` — `cobranza:blast-activas` |
| UI campañas | ✅ `/cobranza/campanas` — KPIs + tabla + modal nueva campaña |
| UI VoIP config | ✅ `/cobranza/voip` — form SIP + test conexión |
| Sidebar | ✅ permisos `cobranza.view` (511), `cobranza.configure` (512), `cobranza.manage` (513) |

### Configs en servidor (fuera del repo)
- `/etc/asterisk/manager.conf` — AMI config real (secret: ver `.env` → `AMI_SECRET`)
- `/etc/asterisk/sip.conf` — troncal Servnet (completar desde `/cobranza/voip`)
- `/etc/asterisk/extensions.conf` — dialplan blaster
- `/etc/sudoers.d/asterisk-www-data` — permisos www-data para sip reload (pendiente configurar)

### Variables .env requeridas
```
AMI_HOST=127.0.0.1
AMI_PORT=5038
AMI_USERNAME=megaisp
AMI_SECRET=<ver .env del servidor>
AMI_CONTEXT=cobranza-blaster
BLASTER_HORA_INICIO=09:00
BLASTER_HORA_FIN=20:00
BLASTER_MAX_INTENTOS=3
BLASTER_MINUTOS_ENTRE_INTENTOS=180
```

### Pendientes CRÍTICOS para producción
1. **Credenciales Servnet** — ingresar en `/cobranza/voip` → "Guardar y aplicar" → verificar `sip show peers` = OK
2. **Webhook AMI** — Asterisk → Laravel para eventos ANSWER/BUSY/NOANSWER/HANGUP
   - Sin esto el blaster origina llamadas pero nunca actualiza estados
   - Endpoint ya existe: `POST /webhooks/cobranza/ami-event` → `CobranzaWebhookController`
   - Falta: configurar `manager.conf` para HTTP POST, o listener AMI en socket persistente
3. **sudoers www-data** — `asterisk -rx sip reload` y `sip show peers` para que VoipConfiguracionController funcione
4. **Prueba llamada real** — crear campaña con 1 cliente, verificar que Asterisk origina y audio llega

### Pendientes MEDIOS
5. **TTS fragmentos fijos cacheados** — pre-generar fragmentos estáticos + cachear slots variables por valor único
6. **Dashboard embajador** — vista pública `/mi-red` para que el cliente vea sus referidos y comisiones

### Flujo completo cuando esté 100% operativo
```
Cron 5min → CobranzaCampanaService::cargarMorosos()
  → BlastCampanaJob → AmiConnectionService::originate()
  → Asterisk llama al cliente vía Servnet
  → Cliente contesta → escucha TTS OpenAI
  → Presiona 1 → transferencia a agente
  → Asterisk POST → /webhooks/cobranza/ami-event
  → ProcessCallResultJob actualiza estado
  → Si max_intentos sin pago → SuspendServiceJob (delay 24h)
```

### Módulos pendientes del sistema (próximas sesiones)
| Módulo | Estado | Próximo paso |
|--------|--------|--------------|
| MegaFamilia | ✅ completo servidor | Importar BD prod → hashear passwords → Hash::check() |
| Embajadores | ✅ completo | Verificar árbol visual en browser con datos demo |
| Marketing Fase 5 | ✅ commiteado | Configurar credenciales Meta OAuth en Integration Hub |
| CobranzaBlaster | ⚠️ falta webhook AMI + Servnet | Ver pendientes arriba |
| Marketing Fase 6 | ⏸️ pendiente | Blaster TTS híbrido (ver diseño arriba) |
| Marketing Fase 7 | ⏸️ pendiente | CRM Kanban + atribución + multi-tenant |
| Flotas (item #60/#61) | ✅ Fase 1 + Fase 2.1/2.2 (tracking GPS con MockDriver) | Validar visualmente tracking; Fase 2.3 = TCP listener + hardware real |

---

## MÓDULO FLOTAS (items #60 Fase 1 · #61 Fase 2 GPS · #62 Fase 3 Geocercas) — `app/Modules/Addons/Flotas/`

Gestión de flota vehicular. **Uso interno Meganet + producto SaaS vendible a clientes ISP.**
Módulo modular estándar (`module.json` id=200, slug `addon-flotas`, activo).

### Estado — Fase 1 ✅ 100% COMPLETA (backend + UI + UX de eliminación segura)
| Capa | Contenido | Estado |
|------|-----------|--------|
| BD | 8 tablas `fleet_*` (vehicles, providers, assignments, maintenances, maintenance_files, documents, fuel_log, photos) | ✅ migración `2026_06_01_180000_create_fleet_tables.php` |
| Modelos | 8 modelos con accessors (display_name, status doc, days_until_expiration, cost_per_liter, is_active) | ✅ |
| Controllers | Vehicle, Maintenance, Document, Provider, FuelLog, Photo, **Assignment (nuevo)** | ✅ |
| Permisos | `fleet.view/manage/assign/maintenance.manage/documents.manage/fuel.manage/providers.manage` | ✅ |
| UI Vue 3 | 3 pantallas Bootstrap 5 + bi-icons (NO Quasar — consistente con la pantalla form ya existente) | ✅ |

### Bitácora
- **Sesión UI (2026-06-01)** — UI completa. Backend ya existía de sesión previa (8 tablas, modelos, controllers, `FleetVehicleForm.vue`). En esta sesión:
  - **Backend wiring:** `FleetAssignmentController` (historial + alta que cierra la asignación previa con `until`), endpoint buscable `GET /api/vehiculos/data/operadores`, ruta web `/flotas/nuevo` (declarada ANTES de `/{id}`) + `views/flotas/create.blade.php`.
  - **Pantalla 1** `FleetVehicleForm.vue` (ya existía) — verificada y conectada; `loadUsers()` ahora usa `/api/vehiculos/data/operadores`.
  - **Pantalla 2** `FleetVehicleShow.vue` — header + 4 tarjetas de salud + 8 pestañas (Info con edición inline + GPS, Asignación con timeline + cambio, Mantenimientos con form inline/multi-select/drag&drop/proveedor nuevo, Documentos con semáforo, Combustible con km/L calculado, Tracking GPS, Historial agregado, Fotos 2x2 + zoom) + barra fija inferior. Status de documentos y km/L se calculan **lado cliente** (el `show()` del vehículo no añade esos accessors).
  - **Pantalla 3** `FleetDashboard.vue` — 5 métricas, "Requieren atención", lista de vehículos con filtros/orden, mapa placeholder, gastos del año por categoría (barras). Trae mantenimientos+combustible+documentos completos para calcular categorías y variación mes vs mes anterior. Exporta CSV cliente-side.
  - Registrados en `app.js`: `fleet-dashboard`, `fleet-vehicle-form`, `fleet-vehicle-show`. `npm run dev` ✅ compila.
- **Sesión modal eliminar (2026-06-02)** — Implementado modal de confirmación al eliminar vehículo en `FleetVehicleShow.vue` (antes era un `window.confirm` nativo). Flujo UX seguro:
  - Botón "Eliminar" (barra fija inferior, rojo) y opción del dropdown ahora abren un **modal Bootstrap 5** (`showDeleteModal`) — NO hacen DELETE directo. `confirmDelete()` solo abre el modal.
  - El modal muestra marca/modelo/año + placas y **contadores reales** de lo que se marcará como eliminado (mantenimientos, documentos, asignaciones, combustible, fotos), tomados de los datos ya cargados en la ficha (`vehicle.maintenances/.documents/.fuelLog/.photos` + ref `assignments`) vía computed `deleteCounts` — **sin endpoint `/counts` extra**.
  - Botones: "Cancelar" (cierra) y "Sí, eliminar" (`executeDelete()`). Al confirmar: `DELETE /api/vehiculos/{id}` → toast verde "Vehículo eliminado correctamente." + redirige a `/flotas` (800ms). Si error: toast rojo con mensaje del backend y el modal **sigue abierto** para reintentar (`deleting` se resetea solo en error).
  - z-index: modal 9999 / backdrop 9998 (sobre barra fija 1000, debajo del toast 10001 para que el toast de error sea visible). Endpoint real es `/api/vehiculos/{id}` (el DELETE end-to-end ya estaba verificado HTTP 200 en sesión previa). `npm run dev` ✅ compila.

### Navegación
- `/flotas` → FleetDashboard · `/flotas/nuevo` → FleetVehicleForm · `/flotas/{id}` → FleetVehicleShow · `/flotas/{id}?tab=mantenimientos` → pestaña directa

### Archivos relevantes (.vue)
- `resources/js/components/module/flotas/FleetVehicleForm.vue`
- `resources/js/components/module/flotas/FleetVehicleShow.vue`
- `resources/js/components/module/flotas/FleetDashboard.vue`

### Notas / deuda Fase 2
- **Fotos:** se guardan en disco `local` (privado) sin ruta pública para servirlas → la galería usa `/storage/{path}` con fallback `@error`. Falta endpoint/симлink de servido en Fase 2.
- **Multi-tenancy permisos:** los addons (MegaFamilia/Embajadores/WarRoom/Flotas) NO tienen entradas en `config/route_permission.php`; funcionan por bypass admin/DESARROLLADOR + `authorize()` en controller. Si se vende a un cliente ISP no-admin, agregar patrones `flotas/*` a ese config.
- **GPS / mapa / "en movimiento":** placeholders Fase 1. Tracking en vivo = Fase 2.
- **OCR documentos:** placeholder "Detección automática con IA (Fase 7)" visible, sin implementar.
- Menú `module.json` apunta a `/flotas/mantenimientos|documentos|proveedores` que aún caen en `/{id}` (404) — vistas dedicadas pendientes (fuera de alcance Fase 1).

### Fase 2 — Tracking GPS (item #61)

**Bitácora — Sesión 2026-06-01 (Sub-fases 2.1 y 2.2 completadas):**
BD + abstracción de drivers + MockDriver + UI de tracking funcional con datos simulados. NO requiere hardware aún; cuando llegue un GPS Ruptela físico solo se añade su driver (implementa `GpsDriverInterface`) sin tocar el resto.

**Estado:** ✅ 2.1 + 2.2 + **2.3a** listos. Sub-fase **2.3a** = RuptelaDriver + TCP listener + simulador + systemd + UI wizard, **validado end-to-end con simulador (sin hardware)**. Falta solo el paso operativo: que Irving apunte el GPS físico (ver `deploy/README-gps-real.md`). Pendiente futuro: drivers Concox/GT06 reales y WebSocket en vivo (2.3b+).

### Sub-fase 2.3a — RuptelaDriver + TCP listener (item #61) ✅ COMPLETA (validada con simulador)

**Bitácora — 2026-06-02:**
- **Protocolo Ruptela:** implementación **mínima viable** (doc oficial requiere registro), command 0x01 (records). `RuptelaDriver` (`Services/Gps/Drivers/RuptelaDriver.php`) implementa `GpsDriverInterface` (parse/name/supports) + `buildAck()` + `extractImei()` + `calculateCrc16()`. Frame: `[len:2][IMEI:8][cmd:1][recordsLeft:1][recordsCount:1][records][crc:2]`. ⚠️ **Supuestos a verificar con GPS real** (marcados `⚠️VERIFY` en el código y en README-gps-real.md): CRC16 asumido CCITT 0x1021 (parseo TOLERANTE: no descarta por CRC, solo loguea); IMEI 8 bytes big-endian uint64; layout de record/IO; divisores altitud/ángulo; command extendido 0x44/0x68 NO implementado. Coords en CDMX para tests (regla 5).
- **DeviceFactory** (`Services/Gps/DeviceFactory.php`): detecta marca por handshake → driver. Añadir Concox/GT06 = añadirlos al arreglo.
- **TCP listener** `flotas:gps-listen {--host=0.0.0.0} {--port=5027} {--read-timeout=60}` (`Console/GpsListenCommand.php`): `stream_socket_server` nativo (sin ReactPHP/Ratchet — regla 3). Por conexión: handshake→DeviceFactory→parse→resuelve `fleet_devices` por IMEI→`FleetPositionService::saveBatch` (que dispara detección 3.2 + notif 3.3, **intactas**)→ACK. IMEI desconocido → crea device `status=unregistered` sin guardar posiciones. Device sin vehicle_id → ACK pero no persiste. Primera conexión con posiciones → `status=active`. Log en `storage/logs/gps-listener.log` con **IMEI e IP enmascarados** (regla 4).
- **Simulador** `flotas:simulate-ruptela {imei} {--host} {--port} {--count} {--lat} {--lng}` (`Console/SimulateRuptelaCommand.php`): construye frames binarios válidos (mismo CRC que el driver) y los envía por TCP al listener. **Coexiste con `flotas:simulate-gps`** (que escribe directo en BD vía MockDriver) — son distintos a propósito, NO se tocó simulate-gps.
- **Migración** `2026_06_02_170000_extend_fleet_devices_status_enum.php`: añade `unregistered` y `pending_first_connection` al enum `fleet_devices.status`.
- **systemd + docs** (NO instalado, regla 6): `deploy/megaisp-gps-listener.service`, `deploy/README-gps-listener.md` (activación), `deploy/README-gps-real.md` (guía operativa para Irving: .env `GPS_LISTENER_PUBLIC_IP`, abrir puerto 5027, Ruptela Configurator, validación).
- **UI wizard** (PASO F): el `activateDevice` de `FleetGpsController` ahora marca los dispositivos físicos (no-mock) como `pending_first_connection` y devuelve `listener {ip,port,protocol}` (ip desde `env(GPS_LISTENER_PUBLIC_IP)`). `FleetVehicleShow.vue` muestra un **modal de instrucciones** tras activar (apuntar GPS a IP:5027 TCP). El wizard de marca/IMEI/SIM ya existía de 2.2.
- Comandos registrados en `ModuleServiceProvider`. `npm run dev` ✅.

**Validación (sin hardware):**
- Round-trip parse aislado: build frame → parse → IMEI/coords(CDMX)/CRC/2 records ✅, ACK=`00026401`.
- **TCP end-to-end** (listener bg en 127.0.0.1:5027 + simulador): Test A (IMEI desconocido) → device `unregistered`, 0 posiciones, ACK ✅. Test B (IMEI vinculado a vehículo 1) → 3 posiciones guardadas, `status=active`, detección de geocercas corrió tras `saveBatch` sin error, ACK ✅. Logs con IMEI/IP enmascarados ✅. Datos de prueba limpiados; listener detenido; puerto 5027 cerrado.

**Próximo paso:** cuando Irving decida, apuntar el Ruptela físico siguiendo `deploy/README-gps-real.md` (activar systemd + abrir puerto + configurar GPS). Al primer ping real: **verificar los supuestos `⚠️VERIFY`** (sobre todo CRC16 e IMEI) y endurecer el parseo si difieren. Sin tocar más código del pipeline (saveBatch/3.2/3.3 ya funcionan).

**2.1 — BD + Abstracción:**
- Migración `2026_06_01_190000_create_fleet_gps_tables.php`: `fleet_devices`, `fleet_positions` (índice compuesto `vehicle_id+recorded_at`), `fleet_device_events`. Soft deletes en las 3.
- Modelos: `FleetDevice` (BaseModel), `FleetPosition` y `FleetDeviceEvent` (Model plano — sin LogsActivity por alto volumen). Relaciones GPS añadidas a `FleetVehicle` (`device`, `positions`, `lastPosition`).
- Abstracción: `Services/Gps/GpsDriverInterface.php` (parse/name/supports), `Services/Gps/Position.php` (DTO), `Services/Gps/Drivers/MockDriver.php` (genera trayectos realistas: paradas 10%, speed 0-80, rumbo a la deriva, 0-100 m/ping desde CDMX).
- Comando `php artisan flotas:simulate-gps {vehicle_id} --interval=30 --duration=3600` (registrado en `ModuleServiceProvider::boot`).
- `Services/FleetPositionService.php`: `saveBatch` (insert por chunks + actualiza `last_seen_at`/`total_pings`), `getLastPosition`, `getHistory`, `getCurrentPositions` (multi-tenant), `liveStatus` (moving/stopped/idle/offline por antigüedad del ping).
- Permisos `fleet.gps.view` / `fleet.gps.manage` en `module.json` + creados en BD y asignados a `super-administrator` + `DESARROLLADOR`.

**2.2 — UI:**
- `Controllers/FleetGpsController.php`: `status` (último + device + stats hoy con Haversine), `history`, `fleet` (mapa global), `activateDevice` (crea `fleet_device` + `has_gps=true`).
- Rutas en `routes.php`: web `/flotas/mapa` (declarada ANTES de `/{id}`), API `api/vehiculos/{id}/gps[/history|/device]` y `api/gps/flota`.
- `FleetVehicleShow.vue` — pestaña **Tracking GPS** reemplaza el placeholder: mapa Leaflet (OSM) 60% + panel 40% (Estado actual / Dispositivo / Estadísticas hoy / botones Centrar·GPX·Configurar), selector de rango (6h/24h/7d/personalizado), polyline azul, marker, polling 30s con pausa. Wizard de activación con dropdown de marca.
- `FleetMap.vue` (nuevo, registrado en `app.js` como `fleet-map`) — mapa global full-height + sidebar de vehículos + filtros (todos/en movimiento/alertas) + pins por estado (verde/amarillo/gris/rojo) + popup + polling 30s. Blade `views/flotas/mapa.blade.php`.
- **Leaflet vía npm** (`import L from "leaflet"`), NO CDN — consistente con `LeafletMap.vue` existente. Markers con `L.circleMarker` (evita el bug de iconos rotos de webpack). OSM tiles, sin API key.

**Datos de prueba:** `database/seeders/FleetGpsSeeder.php` → sembró 2880 pings (24h cada 30s) en el vehículo 1 (`nisan frontier`). Ejecutar: `php artisan db:seed --class=FleetGpsSeeder`.

**Verificado:** migración ✅, endpoints HTTP 200 (status/history/flota) con authorize del nuevo permiso, comando suma pings y actualiza `total_pings`, `npm run dev` compila ✅. **Pendiente: validación visual con Irving en browser** (pestaña tracking + `/flotas/mapa` + polling).

**Fix navegación (2026-06-01):** botón "Ver mapa" y pestaña "Tracking" visibles.
- `FleetDashboard.vue`: el botón "Ver mapa" del header era un placeholder (`openMap` → toast "disponible en Fase 2"); ahora es link real `:href="${baseUrl}/mapa"` (`btn-outline-primary`). Función `openMap` eliminada.
- `FleetVehicleShow.vue`: la pestaña "Tracking GPS" ya estaba en el array `tabs` y se renderiza en la barra; `goTab('gps')` fija `?tab=gps` en la URL. Sin cambios necesarios, solo verificado.

**Fix sidebar Flotas (2026-06-01):** el módulo no aparecía en el sidebar izquierdo.
- **Causa raíz:** el sidebar (`app/Modules/Core/Layout/views/sidebar.blade.php`) es **Blade estático**: cada módulo está hardcodeado con `@canany`/`@can`. El `getMenu()` del ModuleRegistry (que SÍ incluye Flotas) **no se itera en el blade** (`addonMenuItems` se ignora; solo se consume `sidebarSubmenu` para hijos `location:submenu` bajo finanzas). Flotas nunca se agregó a mano → no se renderizaba. (Prueba: MegaFamilia **no tiene** campo `sidebar` en su module.json y SÍ aparece, porque está hardcodeado; Flotas **sí lo tiene** y no aparecía → el campo `sidebar` del module.json es irrelevante para el render.)
- **Fix:** se agregó el bloque Flotas en `sidebar.blade.php` (~línea 602, entre Embajadores y War Room), guardado por `@canany(['fleet.view','fleet.gps.view'])`, con links a Dashboard `/flotas`, Vehículos `/flotas/vehiculos` y Mapa `/flotas/mapa` (solo rutas que funcionan; mantenimientos/documentos/proveedores siguen 404). Verificado renderizando el sidebar como admin: HTML incluye los 3 enlaces.
- ⚠️ **Nota técnica para futuros addons:** el sidebar NO es dinámico desde `module.json`. Aunque declares `sidebar`/`menu` en el manifest, **hay que agregar el bloque a mano en `sidebar.blade.php`** para que aparezca. Solo los hijos `location:submenu`+`parent:finanzas` se renderizan dinámicamente vía `SidebarComposer`/`getSubmenuItemsFor('finanzas')`.

### Fase 3 — Geocercas (item #62)

**Estado:** Sub-fase 3.1 ✅ COMPLETA (BD + CRUD + UI: lista, dibujo en mapa, edición, asignación de vehículos). **Falta lógica de detección** (próximas sub-fases, intencionalmente fuera de alcance):
- **3.2** — detección automática entrada/salida (punto-en-polígono) + generación de `fleet_device_events` (`geofence_enter`/`geofence_exit` ya existen en el enum de eventos GPS de la Fase 2).
- **3.3** — push notifications de las alertas.
- **3.4** — reglas con horarios (ventanas permitidas/prohibidas por geocerca).

**Bitácora — Sub-fase 3.1 (2026-06-02):**
- **BD:** migración `2026_06_02_130000_create_fleet_geofences_tables.php` → `fleet_geofences` (client_id nullable, name, description, type enum enter/exit/both, **polygon json** = array de `[lat,lng]`, color, active, created_by/updated_by, soft deletes) + pivot `fleet_geofence_vehicles` (UNIQUE `geofence_id+vehicle_id`, cascadeOnDelete).
- **Modelo:** `Models/FleetGeofence.php` (BaseModel + SoftDeletes) — casts `polygon=>array`, `active=>bool`; `vehicles()` belongsToMany, `creator()` belongsTo User, scopes `active()` y `forClient($clientId)` (null = flota interna Meganet), accessor `centroid` (promedio de vértices).
- **Permisos:** `fleet.geofences.view` / `fleet.geofences.manage` en `module.json` + **creados en BD y asignados a `super-administrator` + `DESARROLLADOR`** (lección aprendida).
- **Controller:** `Controllers/FleetGeofenceController.php` — index (filtros type/active/search/vehicle_id + stats total/active/assigned_vehicles), show (con vehículos), store, update, destroy (soft), assignVehicles (sync pivot). Validación: `polygon` array min:3, cada punto `size:2` numérico, name required. `sanitizeVehicleIds()` filtra por tenant antes de hacer sync. `clientId()` = null para admin/DESARROLLADOR.
- **Rutas** (`routes.php`): web `/flotas/geocercas`, `/geocercas/nueva`, `/geocercas/{id}/editar`, `/geocercas/{id}` — **TODAS declaradas ANTES de `/{id}`** (orden importa). API REST bajo `api/geocercas` (+ `POST {id}/vehiculos`).
- **UI Vue 3** (Bootstrap 5 + bi-icons + **Leaflet npm**, sin leaflet-draw):
  - `FleetGeofenceList.vue` (`fleet-geofence-list`) — 3 métricas, buscador, filtros tipo/activas/vehículo, lista con badges, empty state, modal eliminar.
  - `FleetGeofenceForm.vue` (`fleet-geofence-form`) — **dibujo manual de polígono** sin leaflet-draw: click agrega vértice (debounce 220ms para no duplicar en doble-clic), **doble-clic cierra**, `doubleClickZoom.disable()`, cursor crosshair, polígono `L.polygon` fillOpacity 0.3 con color elegido, vértices `L.circleMarker`, botón Limpiar/Redibujar, centroide debajo del mapa. Form: nombre, descripción, tipo (radios), color picker, toggle activa, multi-select de vehículos (dropdown checkboxes + chips). Detecta `/geocercas/{id}/editar` desde la URL para modo edición. Botones "Guardar borrador" (active=false) y "Guardar y activar" (active=true).
  - `FleetGeofenceShow.vue` (`fleet-geofence-show`) — mapa solo-lectura con el polígono + fitBounds, lista de vehículos con link a `/flotas/{id}`, botones Editar/Eliminar, **banner amarillo** "detección automática pendiente (Sub-fase 3.2)".
  - Registrados en `app.js`. Blades en `views/flotas/geocercas/{index,form,show}.blade.php`.
- **Sidebar:** link "Geocercas" agregado a mano en `sidebar.blade.php` (bajo Flotas, guard `@can('fleet.geofences.view')`, icono FA `fa-draw-polygon` para consistencia con los hermanos).
- **Seeder:** `database/seeders/FleetGeofenceSeeder.php` → 2 geocercas demo CDMX ("Oficina central Meganet" cuadrado ~200m tipo both, "Zona de cobertura sur" pentágono tipo exit), ambas asignadas al vehículo 1. Ejecutar: `php artisan db:seed --class=FleetGeofenceSeeder`.

**Verificado:** migración ✅, tablas creadas, permisos creados+asignados, `npm run dev` compila ✅, seeder ✅, rutas registradas en orden correcto, **controller probado end-to-end vía tinker** (index total=2/active=2/veh=2, show con vehículo asignado, store 201 con validación+pivot, destroy soft). **Pendiente: validación visual con Irving en browser** (lista, dibujar polígono y guardar, recargar en editar, link en sidebar).

**Archivos creados Sub-fase 3.1:**
- `migrations/2026_06_02_130000_create_fleet_geofences_tables.php`
- `Models/FleetGeofence.php`
- `Controllers/FleetGeofenceController.php`
- `views/flotas/geocercas/{index,form,show}.blade.php`
- `resources/js/components/module/flotas/FleetGeofence{List,Form,Show}.vue`
- `database/seeders/FleetGeofenceSeeder.php`

**Próximo paso:** Sub-fase 3.2 — servicio punto-en-polígono (ray casting) que en cada ping GPS evalúe si el vehículo entró/salió de sus geocercas asignadas y registre `fleet_device_events` con `event_type` `geofence_enter`/`geofence_exit`. Engancharlo en `FleetPositionService::saveBatch` o en un job aparte.

**Fixes Sub-fase 3.1 (2026-06-02, sesión bugs):**
- **Sidebar "Geocercas" no aparecía:** el bloque y el permiso (super-administrator) estaban OK; era la **vista Blade compilada en caché**. Fix: `php artisan view:clear && php artisan cache:clear` (Ctrl+F5 NO basta, recompila solo el cliente). Gotcha: tras editar `sidebar.blade.php` siempre limpiar vistas.
- **Spinner infinito al editar (y crear) geocerca:** en `FleetGeofenceForm.vue` el mapa se inicializaba en `onMounted` mientras `loadingInitial=true`, pero `<div ref="mapEl">` vive en el bloque `v-else` → no está en el DOM → `L.map(null)` lanzaba excepción → `loadingInitial` nunca pasaba a `false`. Fix mínimo: reordenar `onMounted` para cargar datos → `loadingInitial=false` → `nextTick()` → recién entonces `L.map()` (+ guard `if(!mapEl.value) return`). Lógica de dibujo/polígono intacta. ⚠️ **Gotcha Leaflet:** nunca inicializar el mapa antes de que su contenedor esté renderizado; si está detrás de un `v-if/v-else`, init después de bajar la bandera de loading. (`FleetGeofenceShow.vue` ya lo hacía bien: `load()` baja `loading` en su `finally` antes del `nextTick`+init.)

### Sub-fase 3.2 — Detección automática entrada/salida (item #62) ✅ COMPLETA

**Estado:** detección automática funcionando con MockDriver. Falta **3.3** (push notifications vía FCM) y **3.4** (reglas con horarios/días).

**Bitácora — Sub-fase 3.2 (2026-06-02):**
- ⚠️ **Corrección de pre-requisito:** la tabla `fleet_geofence_events` **NO existía** (en 3.1 solo creé `fleet_geofences` + `fleet_geofence_vehicles`). Se creó aquí: migración `2026_06_02_140000_create_fleet_geofence_events_table.php` → `fleet_geofence_events` (vehicle_id, geofence_id, **event_type enum 'enter'/'exit'**, position_id FK nullable, occurred_at, created_at; índices `vehicle_id+occurred_at` y `geofence_id`). NOTA: distinta de `fleet_device_events` (Fase 2, que usa `geofence_enter`/`geofence_exit` en su enum y no se usa aquí).
- **Algoritmo:** `Services/Geometry/PointInPolygon.php` — `contains([lat,lng], polygon)` ray casting estándar (even-odd), `boundingBox()`, `inBoundingBox()`. Coords SIEMPRE [lat,lng]. <3 puntos → false. Borde: comportamiento indefinido (irrelevante para pings). 7 tests en tinker ✅ (cuadrado dentro/fuera, polígono inválido, bbox, geocerca real).
- **Modelo:** `Models/FleetGeofenceEvent.php` (Model plano, `$timestamps=false`, solo created_at manual). Relaciones geofence/vehicle/position + scope forClient.
- **Servicio:** `Services/GeofenceDetectionService.php` — `process(FleetPosition)` (delega en processBatch) y `processBatch($positions)`. Motor **en memoria**: agrupa por vehículo, carga geocercas activas asignadas UNA vez (cache + bbox precalculado), recorre cronológicamente manteniendo estado dentro/fuera (sin query "anterior" por ping). Semilla del estado = posición previa al batch (`previousPosition`). Primera posición jamás registrada → fija estado, no emite evento. Filtra por tipo (enter/exit/both). Bulk insert de eventos. Pre-filtro bbox antes de ray casting.
- **Integración `FleetPositionService::saveBatch`:** insert de posiciones + update de device dentro de `DB::transaction`; **detección DESPUÉS del commit, en try/catch** (con `$beforeId = max(id)` para recuperar exactamente las filas insertadas). Decisión: la detección es efecto secundario reprocesable → un fallo NUNCA debe tumbar la ingestión de pings. Medido: 120 pings + detección ≈ 1.5s **incluyendo arranque de artisan** (la detección en sí muy por debajo de 2s). ⚠️ **Lección:** si con muchos pings saveBatch supera ~2s, mover la detección a un queue job (Sub-fase 3.3).
- **Endpoint:** `GET /api/vehiculos/{id}/geofence-events?limit=20` (`FleetGpsController::geofenceEvents`, authorize `fleet.geofences.view`, multi-tenant `forClient`). Devuelve type, geofence_name, color, **lat/lng** (para centrar mapa), occurred_at.
- **Comando:** `php artisan flotas:reprocess-geofences {vehicle_id} [--hours=24]` — toma posiciones del rango, **borra eventos previos del rango** (idempotente), reprocesa, reporta entradas/salidas. Registrado en `ModuleServiceProvider`.
- **UI:** `FleetVehicleShow.vue` pestaña Tracking GPS, debajo del mapa: card "Eventos de geocercas recientes" (últimos 20) con icono entrada (verde `bi-arrow-right-circle-fill`) / salida (naranja `bi-arrow-left-circle-fill`), texto "Entró a / Salió de [geocerca]", tiempo relativo, **click centra el mapa** en la posición del evento (`focusGeofenceEvent`). Empty state informativo. Cargado en `refreshGps` (Promise.all). `npm run dev` ✅.
- **Seeder:** `FleetGeofenceSeeder` ahora crea una 3ª geocerca "Corredor de prueba (track demo)" **computada de la mediana de las posiciones reales** del vehículo → garantiza que el track aleatorio del MockDriver la cruce y se generen eventos.

**Gotcha de validación:** las 2 geocercas demo originales (oficina/zona sur) quedaron donde el track aleatorio del MockDriver NO pasó → reprocess daba **0 eventos** (no es bug del algoritmo, es colocación de datos). Solución: geocerca "sobre el track" → reprocess generó 1 entrada + 1 salida correctas. **Verificado:** algoritmo 7/7, reprocess 2 eventos con position_id/timestamps correctos, endpoint 200 con lat/lng, saveBatch robusto sin romper ingestión, comando+ruta registrados. **Pendiente: validación visual con Irving** (`/flotas/1?tab=gps` → sección eventos + click centra mapa).

**Próximo paso:** Sub-fase 3.3 — push notifications (FCM) de los eventos de geocerca. Considerar mover la detección de `saveBatch` a un queue job si el volumen de pings crece.

### Sub-fase 3.3 — Notificaciones email + WhatsApp (item #62) ✅ COMPLETA (código)

**Estado:** notificaciones automáticas de eventos de geocerca por **email + WhatsApp** (Camino C — FCM/push queda para item #72 greenfield). Arquitectura dispatcher + driver: sumar canales futuros (push/sms) = implementar `NotificationChannelInterface` y añadirlo al mapa `CHANNELS`, nada más cambia.

**Bitácora — Sub-fase 3.3 (2026-06-02):**
- **BD:** migración `2026_06_02_160000_create_fleet_notification_tables.php` → `fleet_notification_preferences` (user_id, vehicle_id nullable=todos, geofence_id nullable=todas, event_types json, channels json, active, soft deletes) + `fleet_notification_log` (event_id, user_id, channel enum email/whatsapp/push/sms, destination, status enum queued/sent/failed/skipped, error_message, sent_at, created_at). Modelos `FleetNotificationPreference` (BaseModel+SoftDeletes) y `FleetNotificationLog` (plano).
- **Abstracción de canales** (`Services/Notifications/`): `NotificationChannelInterface` (name/destination/send) + `GeofenceEventPresenter` (datos normalizados) + `Drivers/EmailChannel` (Mailable `Mail/FleetGeofenceEventMail` + vista `views/emails/geofence_event.blade.php`) + `Drivers/WhatsappChannel` (**reusa** `App\Modules\Addons\Marketing\Services\EvolutionApiService::sendText` — regla 4, NO se duplicó).
- **Dispatcher** `FleetNotificationDispatcher::dispatch(event)`: resuelve prefs activas que matchean vehículo/geocerca/event_type, **multi-tenancy** (user.client_id == vehicle.client_id), dedup por user×canal, ejecuta cada canal aislado (un fallo no bloquea otros) y **loguea cada intento** (sent/failed/skipped + destino + error).
- **Job async** `Jobs/SendGeofenceNotificationsJob` (QUEUE_CONNECTION=database). Integrado en `GeofenceDetectionService::walk()`: tras insertar eventos, captura los IDs nuevos (`$beforeId = max(id)`) y encola el job en try/catch (un fallo al encolar no tumba la detección). NO se modificó la lógica de detección (regla 6).
- **Controller** `FleetNotificationController`: `myPreference`/`savePreference` (preferencia del usuario por vehículo — una fila por geocerca o una con geofence_id=null=todas; guard `fleet.view`), `log` (paginado, multi-tenant, guard `fleet.notifications.view`), `resend` (re-despacha evento de un log fallido). Rutas en `routes.php` (preference bajo `api/vehiculos/{id}`, log bajo `api/notificaciones-log`, web `/flotas/notificaciones-log` antes de `/{id}`).
- **Permiso** `fleet.notifications.view` en module.json + creado en BD + asignado a `super-administrator` + `DESARROLLADOR`.
- **Comando** `php artisan flotas:test-notification {vehicle_id} {geofence_id} {event_type}` — crea evento sintético y despacha SÍNCRONO (feedback inmediato). Registrado en `ModuleServiceProvider`.
- **UI:** `FleetVehicleShow.vue` pestaña GPS → botón "Configurar mis alertas" + modal (toggle recibir, geocercas todas/selección, tipos enter/exit, canales email/whatsapp con email/teléfono del usuario mostrados). Nuevo `FleetNotificationLog.vue` (`fleet-notification-log`) + blade `views/flotas/notificaciones.blade.php` + ruta + link en sidebar (`fa-bell`, guard `fleet.notifications.view`). `npm run dev` ✅.
- **Datos de prueba:** preferencia default Irving (user 1): vehicle/geofence NULL (todos), event_types [enter,exit], channels [email,whatsapp], active.

**Verificado:** migración ✅; permiso creado+asignado; `flotas:test-notification 1 4 enter` ejecutó el pipeline completo (preference match + multi-tenancy + dedup + ambos canales aislados + 2 rows en log con destino y error); controller `myPreference`/`savePreference` round-trip ✅; `log()` devuelve registros; rutas registradas; `npm run dev` compila ✅.

⚠️ **Entrega real bloqueada por 2 gaps de config del HOST (no son bugs de 3.3):**
1. **Email:** `MAIL_FROM_ADDRESS=null` en .env → "An email must have a From/Sender header" → falla TODO el correo del sistema, no solo Flotas. Prereq: setear `MAIL_FROM_ADDRESS` real.
2. **WhatsApp:** `evolution_api_url` y `evolution_instance_name` **vacíos** para company 1 (Marketing Settings) → Evolution 404. Prereq: configurar la instancia Evolution.
Además, el usuario admin (id 1) tiene email/teléfono placeholder (`admin@admin.com` / `+12345798910`) → para validar entrega real, usar un usuario con datos reales. El pipeline en sí (detección→job→dispatcher→canales→log) quedó probado end-to-end; sólo el transporte depende de esa config.

**Próximo paso:** Sub-fase 3.4 — reglas con horarios/días por geocerca (ventanas permitidas/prohibidas). Push/FCM como tercer canal = item #72 (greenfield), trivial de sumar al dispatcher cuando exista.

### Sub-fase 3.4 — Reglas con horarios/días (item #62) ✅ COMPLETA

**Estado:** reglas de alerta como **capa OPCIONAL (opt-in)** sobre 3.3. Comportamiento mixto (Opción 3 de Irving): **sin reglas activas → 3.3 intacto**; con reglas → al menos una debe coincidir para notificar, si ninguna coincide → silencio. **NO rompe 3.3** (validado).

**Bitácora — Sub-fase 3.4 (2026-06-02):**
- **BD:** migración `2026_06_02_180000_create_fleet_geofence_rules_table.php` → `fleet_geofence_rules` (name, description, user_id, client_id, **vehicle_ids/geofence_ids/event_types/days_of_week/channels JSON**, time_from/time_to nullable, active, soft deletes). Modelo `FleetGeofenceRule` (BaseModel+SoftDeletes, casts array/bool). Convención: arreglos vacíos = "todos"; `time_from > time_to` = ventana que cruza medianoche; `days_of_week` ISO 1-7 (1=lun), vacío = todos.
- **Evaluador:** `Services/Notifications/RuleEvaluator.php` — `evaluate(User, event)` → `{allowed, matched_rule_id, channels}`. Sin reglas → allowed=true, channels=null (3.3). Con reglas → primera que coincide (vehículo+geocerca+tipo+día+ventana horaria con manejo de medianoche) gana; ninguna → denied. **Timezone del servidor** (sin tz por usuario, fuera de alcance).
- **Integración dispatcher** (`FleetNotificationDispatcher`): dentro del `foreach($prefs)`, llama `RuleEvaluator::evaluate`. Si denied → registra `skipped` ("Filtrado por reglas del usuario (3.4)") por cada canal de la preferencia y continúa. Si allowed con channels de regla → **intersecta** con los canales de la preferencia. Sin reglas, el flujo 3.3 queda idéntico.
- **Controller:** `FleetRuleController` (index/store/update/destroy/toggle) — **multi-tenancy estricto**: cada usuario solo ve/gestiona SUS reglas (`where user_id = auth()->id()`). Rutas: web `/flotas/reglas` (antes de `/{id}`), API `api/reglas` + `{id}/toggle`. Permisos `fleet.rules.view`/`fleet.rules.manage` en module.json + BD + asignados a super-administrator + DESARROLLADOR.
- **Comando:** `php artisan flotas:test-rule {user_id} {vehicle_id} {geofence_id} {event_type} {--dispatch}` — crea evento sintético y muestra la decisión del evaluador (allow/deny + regla + canales); `--dispatch` además despacha (envía de verdad).
- **UI:** `FleetRuleList.vue` (`fleet-rule-list`) — lista + **modal crear/editar consolidado** (nombre, vehículos "todos"/selección, geocercas "todas"/selección, tipos enter/exit, horario "cualquier hora"/ventana con 2 time pickers, días con botones LMMJVSD + helpers Todos/L-V/Fin de semana, canales email/whatsapp, toggle activa). Empty state explicativo. Blade `views/flotas/reglas.blade.php`, registrado en app.js, link en sidebar (`fa-filter`, guard `fleet.rules.view`). En `FleetVehicleShow.vue` pestaña GPS: **indicador** "N reglas activas" (link a /flotas/reglas) o "Sin reglas — recibes todas las alertas".
- `npm run dev` ✅, `php artisan view:clear` ejecutado (lección sidebar).

**Verificado (RuleEvaluator + dispatcher, sin envíos reales):** sin reglas→allowed (3.3 intacto) ✅; dentro de ventana 22:00-06:00 ✅; madrugada 02:00 (cruce medianoche) ✅; fuera de ventana→denied ✅; mismatch de tipo/vehículo→denied ✅; channels de regla devueltos ✅; **dispatcher con regla que deniega → ambos canales `skipped` sin enviar** ✅. Datos de prueba limpiados (0 reglas). Rutas/comando/permisos registrados ✅.

**NO se creó regla de prueba permanente** (regla H — para no confundir el flujo limpio de 3.3). La UI queda lista para que Irving cree reglas manualmente.

**Arquitectura:** `RuleEvaluator` es opt-in — usuarios sin reglas no se ven afectados. Sumar condiciones futuras (velocidad, etc.) = extender `ruleMatches()`.

**Próximo paso (Fase 3 cerrada salvo extras):** opcional 3.x = tz por usuario / condiciones de velocidad; o saltar a Fase 4 (Documentos avanzados / OCR). Push/FCM = item #72.

---

## App de campo Talento — ciclo de campo completo v1.6

**Stack:** React Native 0.74.5 · Laravel Sanctum (Bearer) · `/home/meganet/TalentoEquipo`
**APK actual:** talento-v1.6.apk (versionCode 106) · `http://192.168.105.11/downloads/talento-v1.6.apk`

### Funcionalidades implementadas (v1.6, 2026-06-05)

| Sub-paso | Descripción | Commit app | Commit backend |
|----------|-------------|-----------|----------------|
| 1 | completarOT con checklist real + gate dBm desde `talento_dbm_thresholds` | — | 2dffdc0 |
| 2 | Firma cliente como evidencia tipo es_firma (vía EvidenciaScreen) | 1db358b | — |
| 3 | POST /ots/{id}/incidencia + `talento_work_order_incidents` + status 'incidencia' | — | 1928802 |
| 4 | PUT /ots/{id}/nota + columna nota_tecnico | — | 1928802 |
| 5 | CierreScreen: checklist obligatorias, semáforo dBm, nota técnica | 1db358b | aaa850f |
| 6 | "No puedo completar" modal con 6 motivos → POST incidencia | 1db358b | — |
| 7 | Botón "Cómo llegar" → geo:/maps con coords/dirección | 1db358b | — |
| 8 | Tarjeta cliente: nombre, dirección, teléfono, plan, referencia | 1db358b | — |
| 9 | OrdenesScreen: historial paginado, filtros estado/fecha, read-only si cerrada | 1db358b | — |
| 10 | Logout en header + 401 → triggerLogout limpio, sin Alert debug | 10e227a/737be43 | — |
| 11 | Camera-only (VisionCamera, sin galería) — ya estaba correcto | — | — |
| 12 | APK v1.6/106, version_codes DB corregidos (1.4→104, 1.5→105, 1.6→106) | fd57d31 | — |
| Config ev. | Pantalla web admin `/talento/config/evidencias` — matriz 15×5 checkboxes | — | f853841/318c24c |

### Pendientes para próximas versiones

- **Pad de firma digital**: requiere `react-native-signature-canvas` (+ `react-native-webview`). Actualmente la firma se captura como foto (VisionCamera, type_id=9).
- **Mi Semana completa**: desglose por OT, gráfico semanal, histórico de compensación.
- **Escáner serial ONT/router**: QR/código de barras → autofill campo serial en evidencia.
- **Modo offline**: cache de OTs del día, outbox de evidencias, sincronización al reconectar.
- **Notificaciones push (FCM)**: alertar al técnico cuando se valida una OT o se asigna una nueva.
- **dbm_tier en evidencias**: backend debe incluir dbm_tier en la respuesta de otEvidencia para que CierreScreen lo muestre correctamente (actualmente usa dbmEv.dbm_tier).

### Notas de integración

- `evidencias_requeridas` viene en `GET /ots/{id}` → array `[{id, nombre, es_firma}]`
- `evidencias` incluye `type_id` y `tipo_nombre` (JOIN con talento_evidence_types)
- El semáforo dBm en CierreScreen lee `evidencias[].dbm_tier`; si el backend no lo incluye, el semáforo no aparece (no bloquea)
- `nota_tecnico` se guarda vía PUT /ots/{id}/nota (también incluido en POST /completar)

### Catálogo de tipos de evidencia (`talento_evidence_types`)

| id | Nombre | varias | req_just | lectura_dbm | es_firma |
|----|--------|--------|----------|-------------|---------|
| 1 | Fachada (número visible) | | | | |
| 2 | NAP + puerto | | | | |
| 3 | Roseta / conector óptico | | | | |
| 4 | Equipo instalado (ONT/router) | | | | |
| 5 | Serial (etiqueta) | | | | |
| 6 | Lectura dBm | | | ✓ | |
| 7 | Tendido / cableado | ✓ | | | |
| 8 | Speedtest | | | | |
| 9 | Firma cliente | | | | ✓ |
| 10 | Empalme / fusión | ✓ | | | |
| 11 | Foto antes | | | | |
| 12 | Foto después | | | | |
| 13 | Equipo retirado + serial | | | | |
| 14 | Punto desconectado / sellado | | | | |
| 15 | Otro | ✓ | ✓ | | |

### Tipos de OT y puntos (`talento_work_order_types`)

| id | Nombre | pts | billable | inicia_garantia | requires_validation |
|----|--------|-----|----------|-----------------|---------------------|
| 1 | Instalación nueva | **9** | ✓ | ✓ | ✓ |
| 2 | Soporte/reparación (garantía) | 3 | ✓ | | |
| 3 | Cambio de equipo | 0 | ✗ | | |
| 4 | Reubicación | 0 | ✗ | | |
| 5 | Baja / retiro | 0 | ✗ | | |

Los **puntos viven en `talento_work_order_types.points`** y se snapshottean en `talento_work_orders.points` al crear la OT. El ledger de compensación solo se escribe cuando un admin valida (status `validated`).

### Matriz de evidencias obligatorias (`talento_ot_type_evidence_requirements`)

| Tipo OT | Evidencias obligatorias (ids) | Notas |
|---------|-------------------------------|-------|
| Instalación nueva (1) | 1,2,3,4,5,6,8,9 | — |
| Soporte/reparación (2) | 11,12,6 | +5 si hubo cambio de equipo (condicional) |
| Cambio de equipo (3) | 13,4,5,6 | — |
| Reubicación (4) | 1,2,6,9 | — |
| Baja/retiro (5) | 13,14,4 | — |

### Umbrales dBm

| Rango | Categoría | Efecto |
|-------|-----------|--------|
| >= -8 | Sobrepotencia | **Bloquea cierre** |
| -9 a -18 | Aviso | Acepta, marca alerta |
| -19 a -23 | Excelente | Alimenta bono de salud de red |
| -24 a -26.99 | Cumplió | Alimenta bono de salud de red |
| <= -27 | Baja señal | **Bloquea cierre** (revisar) |

Umbrales en `talento_dbm_thresholds` (config propia, NO en tabla `settings`).

### Reglas anti-basura (se enforzan en Fase B, NO en Fase A)

1. Un tipo de evidencia **una sola vez** por OT, salvo `permite_varias=true`
2. **GPS obligatorio** (lat/lng) por cada evidencia subida
3. Tipo "Otro" (id=15) **exige** campo `justificacion` no vacío
4. No se puede completar si faltan evidencias **obligatorias** del tipo de OT
5. `potencia_dbm` es campo numérico separado con validación de umbrales (bloquea en sobrepotencia y baja señal)

### Recomendaciones priorizadas

**v1 (implementar ahora):**
- Catálogo de tipos de evidencia con flags (esta Fase A)
- Tipos de OT actualizados con puntos reales e `inicia_garantia`
- Matriz de requeridos por tipo de OT
- Umbrales dBm en tabla propia
- Validación de cierre en backend (Fase B)

**v1.1 (siguiente ciclo):**
- Firma digital del cliente (canvas en app)
- Notificación push al validar (FCM)
- Preview de evidencias en OT Detalle
- Modo offline con sincronización al reconectar

### Fases del módulo de evidencia

| Fase | Contenido | Estado |
|------|-----------|--------|
| A | Modelo de datos: `talento_evidence_types`, tipos OT, `talento_ot_type_evidence_requirements`, `talento_dbm_thresholds` | ✅ DONE (2026-06-05) |
| B | Validación de cierre: checklist obligatorias + gate dBm | ✅ DONE (2026-06-05) |
| C | UI evidencia: EvidenciaScreen (selector tipo, VisionCamera, watermark, GPS) + CierreScreen | ✅ DONE (2026-06-05) |
| D | Bono de salud de red: cálculo con umbrales, escritura en ledger al validar | ✅ DONE (Fase 4b) |
| E | Firma cliente: foto de firma vía VisionCamera (type_id=9). Pad digital pendiente (react-native-webview) | ⚠️ parcial |

---

## PORTAL TÉCNICO WEB (Talento) — Bloque 1 CERRADO

Versión web/PWA de la app de campo (reusa `/talento/api` + servicios Talento; guard web Medussa). Sub-pasos 1–3 DONE (commits **locales, NO pusheados**: `58cb8a5f` shell/permiso, Sub-paso 2 "Mi día", Sub-paso 3 OT+evidencia+firma). Sub-paso 4 ("Mis proyectos", planta externa) **pausado sin código** por hallazgos de seguridad (`ProjectActivityService::submitReport` auto-aprueba y no valida pertenencia → Mis proyectos quedaría SOLO LECTURA; requiere flujo `pending` + gate de pertenencia antes de exponer escritura).

### Cierre de pendientes de prueba (2026-07-02) — ✅ RESUELTOS
- **Tarea de prueba #1686** ("PRUEBA Portal Técnico — Cambio de equipo") **borrada** (hard-delete de `tasks` + su fila huérfana en `task_user`; 0 evidencias/firmas/activaciones asociadas).
- **Seeds de proyecto Sub-paso 4:** confirmado **inexistentes** — `talento_projects` y todas las `talento_project*` en 0 filas, sin seeder committeado (Sub-paso 4 nunca persistió datos). No-op.
- **Permiso temporal de Brandon** (`User#4429`, `login_user=brandon`): `talento.portal_tecnico` era permiso **directo** (dado solo para screenshots) → **revocado** (`revokePermissionTo` + `forgetCachedPermissions`). Sus otros 37 permisos directos intactos. Brandon (roles Vendedor/TECNICO) ya no tiene acceso al portal; el permiso queda solo en super-administrator + DESARROLLADOR.

**Detalle completo del bloque** (sub-pasos, deudas, criterios de aceptación): memoria `project-portal-tecnico-bloque1`.

---

## MOTOR DE COMPENSACIÓN TALENTO — Ventana de la semana de pago (arreglo 2026-07-02)

**Estado: ✅ ARREGLO COMPLETO (DEV, forward-only).** La ventana de pago se corrigió de `Sáb 00:00 → Vie 23:59` (sin corte horario) a **`Sáb 18:00 → Sáb 18:00` con corte inclusivo a las 18:00** (regla real de Irving: lo validado después de las 18:00 del sábado pasa a la semana siguiente).

- **Punto único de verdad:** `app/Modules/Addons/Talento/Support/PayWeek.php` (`boundsFor`, `current`, `lastCompleted`, `transitionStart`). Config en `config/talento.php` (`pay_week.cutover`, `cutoff_hour=18`, `cutoff_minute=0`). **Nadie más deriva la ventana** (los 7 sitios viejos con `startOfWeek(SATURDAY)`/`addDays(6)`/`endOfWeek` fueron repuntados).
- **Cutover = `2026-07-11 18:00`.** Antes del cutover rige la ventana **legacy** `Sáb 00:00 → Vie 23:59` idéntica al motor viejo (histórico ya pagado reproducible e INTACTO — no-regresión numérica verificada al centavo).
- **Semana de TRANSICIÓN de 8 días (Opción 3):** `[2026-07-04 00:00 → 2026-07-11 18:00]` se paga con el **método viejo** en un solo pago. La primera semana nueva arranca `2026-07-11 18:00:01 → 2026-07-18 18:00`.
- **Guard anti-recálculo** en `TalentoLiquidacionController::calcular`: rechaza (422 + mensaje en español) liquidar `period_start` anterior a `PayWeek::transitionStart()` (= cutover − 7d = `2026-07-04`). Guard + `UNIQUE(colaborador, period_start, period_end)` ⇒ imposible duplicar/solapar una semana ya pagada.
- **Filtro por datetime** (no por día): `calculate` y los servicios filtran `validated_at` contra `start_instant`/`end_instant`; `periodDays` derivado de la ventana (7 normal, 8 en la transición; antes `diffInDays+1` daba 8 en la nueva — bug de divisor evitado).
- **Commits:** `221e9a01`+`a18f092b` (helper+config+cutover), `da96436b` (Capa A — 3 servicios de dinero: Liquidation/HealthBonus/ProjectBonus), `90953344` (Capa B — display: avance/ProjectController/DashboardService/compensacionSemana), `73725b8d` (Pieza 1 — transición 8 días), `993ad153` (Pieza 2 — guard).

### ✅ ARRANQUE LIMPIO — el sistema de compensación NUNCA se usó en producción
**Auditoría de PROD (2026-07-02): 0 pagos reales registrados** — `talento_ledger_entries`, `talento_liquidations`, `talento_funds`, `talento_loans` **todas en 0 filas**. Los pagos históricos se hacían **por fuera del sistema**. → **No hay histórico que preservar ni semana de transición que pagar.**

- **La primera semana real de pago por el sistema es `2026-07-11 18:00 → 2026-07-18 18:00`.** No hay datos previos ni semana de transición a liquidar (la nota anterior sobre "pagar `[07-04→07-11]` con método viejo" **YA NO APLICA** — ese escenario no existe).
- **Único paso operativo real** — en PRODUCCIÓN, al desplegar, setear en el `.env`: **`TALENTO_PAYWEEK_CUTOVER=2026-07-11 18:00:00`**. *(El `config/talento.php` ya trae ese valor como **default seguro** si el env falta — verificado; igual conviene el env explícito en prod.)*

### 🧹 Deuda menor OPCIONAL (sin urgencia)
La maquinaria **legacy/transición** de `PayWeek` (modo `Sáb–Vie`, semana de 8 días, guard anti-recálculo) quedó en el código pero es **inalcanzable** (no hay datos pre-cutover que la activen). Funciona correcto como está; se puede **simplificar en el futuro** si se quiere (quitar el modo legacy y la transición, dejar solo `Sáb 18:00 → Sáb 18:00`), sin ninguna urgencia.

### 🐛 Deuda registrada aparte (NO de este trabajo)
`DashboardService::tecnicoPreview` y `::team` truenan por `with('level')` (relación inexistente en `TalentoColaborador`) — **bug pre-existente**, no introducido en este arreglo. Arreglar en sesión futura (ver memoria `talento-fase8-9`).
