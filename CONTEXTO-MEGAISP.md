# CONTEXTO MEGAISP — Mapa técnico vivo

> **Para CC:** Lee este archivo COMPLETO al arrancar cada sesión, ANTES de
> investigar el código. Contiene el mapa que ya se levantó en sesiones previas.
> Si algo aquí contradice el código actual, el código gana — repórtalo para
> actualizar este doc. **Al cerrar cada sesión, actualiza la sección que tocaste**
> (ver "PROTOCOLO DE ACTUALIZACIÓN" al final).
>
> **Objetivo de este doc:** evitar que CC repita investigaciones read-only ya
> hechas. Lo que está aquí NO se vuelve a investigar salvo que se sospeche que
> cambió.

---

## 0. ENTORNOS (memorizar — nunca asumir)

| Entorno | Host LAN | Host pública | Ruta | Base de datos | APP_ENV |
|---|---|---|---|---|---|
| **DEV** | 192.168.105.11 | 38.123.192.199 | `/var/www/megaisp` | `megaisp` | local |
| **PROD** | 192.168.105.108 | 38.123.192.198 | `/var/www/ClaudeMegaISP` | `meganet_prod_claude` | local* |
| PROD legacy | 192.168.105.108 | .198 | `/var/www/MEGANET` | `meganet_prod` | local* |

- *La `.198` es la IP pública de PROD; la LAN es `.108`. El shell de prod arranca
  en `/var/www/MEGANET` (obsoleto) → **usar rutas absolutas**.
- `APP_ENV=local` en prod es un artefacto conocido; la BD es la que manda.
- **Los dos VMs NO se alcanzan entre sí** (firewall/VLAN). Para tocar prod hay que
  abrir sesión de CC DIRECTAMENTE en `.108`. Desde `.11` no se llega a prod.
- **IDs de usuario dev y prod NUNCA coinciden.** Resolver por `login_user`/`email`,
  jamás por un ID copiado de dev. (Ej.: id=8 en dev es Irving; en prod es un cliente.)

---

## 1. REGLAS DURAS (además del SKILL.md de convenciones)

- Git: `add` selectivo archivo por archivo. **NUNCA `-A` ni `.`** (hubo exposición
  de credenciales). Commit por sub-paso, mensajes en español.
- **NUNCA `migrate:fresh`** en ninguna BD no desechable. Solo migraciones aditivas.
- Passwords: `base64_encode`, NO bcrypt. Login por `login_user`, NO `email`.
- Blade: `@if(auth()->user()->can('x'))`, NUNCA `@can()`.
- Permisos Spatie son **aditivos**: `givePermissionTo`, NUNCA `syncRoles`/
  `syncPermissions` para AGREGAR (footgun: borra roles existentes). Nuevos permisos
  → `super-administrator` + `DESARROLLADOR` (todos), demás roles solo `.view`.
  Correr `php artisan permissions:sync-roles` tras registrar permisos nuevos.
- Caché: tras cambios en Blade/config → `view:clear && config:clear && route:clear`, y cerrar con
  `queue:restart`. **`config:cache` SÓLO detrás de su candado**: `php artisan config:auditar-env &&
  php artisan config:cache` — el comando devuelve exit 1 si queda una llamada a `env()` en runtime
  fuera de `config/` (con la config cacheada, Laravel no lee el `.env` y esas llamadas dan null).
  Esta línea decía "SIEMPRE cerrar con `config:cache`" y contradecía al código en el punto exacto
  del que depende que el circuito llame a Claude — ver CLAUDE.md, item #790.
- **Paso 0 read-only** antes de escribir. Diff → OK de Irving por sub-paso.
- Toda deuda/bug/decisión diferida → registrar en Hoja de Ruta INMEDIATAMENTE.
- Fechas legacy (`payment_date`, `document_date`) son VARCHAR `DD/MM/YYYY` →
  usar `COALESCE(STR_TO_DATE(col,'%d/%m/%Y'), ...)`, nunca comparar como string.
- Frontend: Vue 3 + Quasar UMD sobre Blade (excepción: Flotas usa Bootstrap 5).
  SPA vía `spa-nav.js` (fetch-then-swap); respetar `data-spa-skip` y la blacklist.
- No compilar APKs ni builds pesados en el servidor (disco cerca de capacidad).
- **La suite de pruebas corre SÓLO contra `megaisp_test`** (o `:memory:`). `Tests\TestCase::setUp()`
  hace `migrate:fresh --seed`, así que apuntarla a la base de la app la vacía — pasó el 22-ago y
  otra vez el 25-ago. Ya no es una convención: `tests/GuardBaseDePruebas.php` detiene la suite si la
  base no termina en `_test`, y `deploy/circuito/guard-bd-pruebas.sh` impide que una terminal
  arranque en un árbol no apto. Ver §10.

---

## 2. HOJA DE RUTA (dónde vive)

- **Es un MÓDULO en la BD**, tabla `roadmap_items`, alimenta la vista `/releases`
  (pestaña "Hoja de ruta"). **NO es un archivo en disco.** (CC intentó escribir en
  `storage/app/roadmap-memory/` y dio permiso denegado — ese path NO es la fuente.)
- Regla: **una sola tarea en progreso a la vez POR TERMINAL** (no es un límite
  global del sistema). El supervisor puede tener varias tareas listas en su
  escritorio y trabajar hasta 6 simultáneas (una por terminal), siempre que
  revise que no se pisen entre ellas (mismo archivo/módulo); si detecta ese
  riesgo, asigna esa secuencia a una sola terminal para evitar colisiones.
  Ítems nuevos entran como `pending`; una terminal NO toma un segundo ítem
  (`in_progress`) sin cerrar el que ya tiene asignado.
- Para registrar: insertar en `roadmap_items` con estado `pending` + prioridad.

---

## 3. SISTEMA DE PAGOS (mapa detallado — lo más re-investigado)

### 3.1 Los dos motores de aplicación de pago

| Motor | Entry point | Qué hace | paymentable |
|---|---|---|---|
| **Mostrador clásico** | `ClientPaymentController::store` → `Client::clientCreatePayment()` (ClientTrait) | `$this->payments()->create()`; además llama `InvoiceService::updateProformaInvoicePendingDespuesDeUnPago()` en el controller | `Client` |
| **Motor unificado** | `PaymentApplicationService::applyPayment()` | Antes: `matchPendingInvoice()`. Ahora (fix #191): fuerza `Client` | `Client` |

### 3.2 La cadena contable correcta (la que SÍ deja rastro)

```
payments()->create(paymentable=Client)
  → PaymentObserver::created  (mapa PAYMENTTABLE_TYPE: SOLO App\Models\Client y
                               App\Modules\Core\Clientes\Models\Client → PaymentClientJob)
  → PaymentClientJob::created:
      · updateClientBalance (+crédito)
      · addTransaction (asiento en ledger `transactions`)
      · ClientBillingService::billing → actionBilling → RectifyBalanceAndCreateTransaction
        (cobra servicios activos desde el saldo, debita) + setNewFechaCorteForClient
        (avanza corte) + $client->activarCliente() (reactiva si suspendido)
```

- **CLAVE:** `paymentable=ClientInvoice` NO está en el mapa del observer → un pago así
  NO dispara el job → NO abona balance, NO deja transacción = **pago desregistrado.**
  (Ése fue el bug del caso 7500.)

### 3.3 "La cuenta jala el saldo y cubre la deuda" — mecanismo confirmado

- El saldado de deuda operativa NO lo hace el pago directamente. Lo hace el ciclo de
  billing (`ClientBillingService::billing`) disparado en CADA pago por `PaymentClientJob`,
  + el cron diario de billing (Kernel ~03:00).
- Opera sobre balance → cobro de servicios → avance de corte. NO voltea la fila de
  proforma (`invoices.status`) a "paid" — eso solo lo hace el path de mostrador
  (`updateProforma`, que matchea por `payment_period`).
- Por eso una proforma puede quedar en `draft` aunque el periodo esté pagado
  operativamente: es cosmética del **sistema dual de facturas** (deuda técnica #159:
  `invoices` proforma vs `client_invoices` legacy). NO es deuda viva.

### 3.4 BUG #191 (matchPendingInvoice) — ARREGLADO en DEV

- **Causa:** `matchPendingInvoice` matcheaba factura con estado `LIKE 'Pagar%'` Y
  `total == monto` exacto, tomando la más antigua del pool `'Pagar (del saldo de la
  cuenta)'` (~40k facturas ya cubiertas por saldo, estado TERMINAL, NO deuda viva).
  Al matchear escribía `paymentable=ClientInvoice` → pago desregistrado.
- **Fix (opción A):** `applyPayment` fuerza `paymentable=Client` SIEMPRE. Los 3 callers
  quedan alineados al path que abona balance. `matchPendingInvoice` se dejó INTACTO
  pero DESCONECTADO (0 llamadas), con comentario del bug.
- **Validado en DEV:** los 3 callers dejan transacción de crédito + abonan balance +
  visibles en pestaña Pagos. Daño en PROD = 0 (verificado ambas BD, ambas firmas del bug).
- **Estado:** #191 done en dev. NO desplegado a prod (bloqueado por #126, ver §5).

### 3.5 Los 3 callers de applyPayment

| Caller | Archivo | add_by | Vía / notas |
|---|---|---|---|
| Conciliación WhatsApp | `PaymentFromSessionService::apply` | MEGAISP (4844) | comment "Pago por WhatsApp (conciliación IA…)", método Transferencia |
| SPEI/OpenPay webhook | `SpeiWebhookController` | ⚠️ cae en Admin (1) vía `resolveSystemUserId` (busca rol SUPER_ADMIN inexistente) — **debe ser MEGAISP; fix de 1 línea pendiente** | comment "Pago openpay (tx:…)"; inactivo en prod |
| Captura-pago mostrador | `ManualPaymentController` (`/finanzas/captura-pago`) | `auth()->id()` (staff real: Diana 3, Ariana 4122) | comment "Pago capturado en mostrador"; pantalla nueva Fase Pagos 2b |

- La vía NO tiene columna propia (`provider`/`channel`): se codifica en `comment` +
  `add_by` + `payment_method_id`. Deuda de forma opcional: persistir la vía en columna
  para filtrar sin LIKE sobre texto.
- Historial de pagos: `ClientPaymentDatatableHelper`. MEGAISP se pinta color cian.
  Filtra por `paymentable_id = clientId` (por eso un pago `ClientInvoice` era invisible).

### 3.6 Conciliación de pagos por WhatsApp con IA

- Flujo: cliente manda comprobante por WhatsApp → IA lo lee → identifica (MEG / ID /
  nombre+calle) → cae a la cola como **propuesto** → humano confirma → se aplica.
- **Identidad:** MEG es la clave fuerte. NO teléfono (un cliente manda el comprobante
  del vecino). Terminología genérica ("revisión humana"), no nombres de personas.
- **Cola:** `/finanzas/conciliacion-cola`. Permiso `conciliacion.manage` (id 725), a
  super-administrator + DESARROLLADOR. Pestañas: Propuestos / Escalados / Verificación /
  Historial. Icono de pago (no campana), polling 45s.
- **Config:** `/finanzas/conciliacion-config`, solo super-administrator. Persiste en
  tabla `conciliation_settings`. 4 flags: `wa_conciliation` (master), `wa_autorespond`,
  `auto_apply_enabled` ("mueve dinero"), `id_cliente_auto_apply` ("mueve dinero").
  En DEV: master+autorespond ON, auto_apply+id_cliente **OFF** → todo cae a la cola.
- **Respeto del flag:** los checks de auto-apply están gateados por
  `$automatic = ($confirmedBy === null)`. Confirm manual pasa usuario → salta checks
  (decisión humana). NO hay path automático que aplique sin checar el flag. ✅
- **Propuesto NO toca balance.** El payment ni existe hasta que se confirma
  (`applyConfirmed` → `apply` → `applyPayment`). El saldado de deuda pasa al confirmar,
  no al proponer.
- Plan completo del proyecto: `plan-conciliacion-whatsapp-ia.md`.

---

## 4. OTROS MÓDULOS (mapa rápido — ampliar cuando se trabajen)

- **Talento / Portal Colaborador:** `/talento/portal`, 5 bloques. Permiso
  `talento.portal_tecnico` / `portal.colaborador`. Tablas `talento_*`. OTs de campo
  viven en `tasks` (NO `talento_work_orders`, que está data-dead). Semana de pago:
  Sáb 18:00→Sáb 18:00, cutover 2026-07-11 18:00 (PayWeek helper, forward-only).
- **MegaFamilia:** control parental + app. Aislamiento por `BelongsToClientTenant`
  (fail-closed; NULL tenant = interno solo para Flotas). Portal con tabs anidados.
- **VoIP / CobranzaBlaster:** unificados vía `VoiceGateway` + `AmiClient` (AMI 5038,
  user megaisp). PJSIP Realtime (tablas `ps_*`, conexión `asterisk_rt`). Prod NO tiene
  Asterisk instalado (items 185/186 [INFRA]).
- **Servicios Contratables:** inyectado como bloque gated DESPUÉS del loop
  `ALL_CLIENT_SERVICE` (no dentro — expondría a SuspendService/PromotionService).
- **Portal SPEI nativo:** `CepValidatorService` (BanxicoCepDriver/ManualCepDriver),
  tablas `portal_pago_*`. Seam Medussa gated pendiente de revisión legal.
- **Inventario — `inventory_item_types.categoria`** (#572, 2026-08-08): clasificación de
  negocio, **varchar(20) nullable, NO enum** → agregar una categoría es datos + código, sin
  ALTER. Punto único de verdad = `InventoryItemType::CATEGORIAS`
  (`herramienta` · `material` · `equipo_cliente`). Es **distinta** de la columna legacy
  `type` (enum tool/material), que está mal poblada y la consume la lógica de inventario.
  El form y el listado de "Tipos de Artículo" son **DB-driven** (`field_modules` +
  `column_datatable_modules` sobre `modules.name='InventoryItemType'`): exponer un campo
  = agregar esas filas por migración, no tocar Blade. Consumidor final: "Mi material" del
  Portal de Colaborador (`public/talento-portal/app.js` espeja el mapa de etiquetas).
  ⚠️ Quedan **75 tipos sin clasificar A PROPÓSITO** (documentados en la migración
  `2026_08_08_150100`): 20 son **equipo de red** y sugieren una 4ª categoría, 3 son los
  dudosos de negocio de Irving (ELIMINADOR/POE/POWER) y 52 tienen nombre genérico o son
  erratas del catálogo. NO "resolverlos" adivinando.
- **Cómo consumir el MÓDULO IA para una extracción de una sola vez** (patrón nuevo, #580):
  `IAProveedor::where('activo',1)->where('soporta_imagenes',1)->first()` →
  `IAAdaptadorFactory::crear($p)` → `$adaptador->enviarMensaje([], $prompt, [['mime'=>…,
  'data'=>base64]], $system)`. **NO** se usa `IAProveedorService::enviarMensaje`: ése es el
  camino de CHAT (exige `IAConversacion`, persiste mensajes, inyecta el system prompt del
  proyecto y corre extracción de memoria). Así se cumple la convención de servicio único
  sin cliente HTTP ni key por módulo.
  ⚠️ **Solo `ClaudeAdaptador` manda `application/pdf` como bloque `document`**; los de
  OpenAI/Gemini empujan todo como imagen y el proveedor responde un error críptico → si un
  consumidor acepta PDF, tiene que cortar antes por `driver !== 'claude'`.
  ⚠️ `IAProveedorService::MIMES_VALIDOS` **no incluye PDF**, pero esa validación vive en el
  camino de chat, no en el adaptador: quien llame al adaptador directo valida por su cuenta.
- **Flotas — OCR de documentos** (#580, Fase 7, dev): `FleetDocumentOcrService` +
  `VehicleDocumentProfile`. `POST /flotas/api/documentos/ocr` **solo lee** (no crea nada) y
  deja fila en `fleet_document_ocr_runs` (append-only); `store` recibe `ocr_run_id` y copia
  el veredicto **desde la bitácora**, nunca desde el request. Columnas `ocr_*` de
  `fleet_documents` **fuera de `$fillable`** a propósito (`update()` hace mass assignment con
  `$request->except([...])`). El OCR jamás bloquea la subida: falla → se guarda igual con
  `ocr_needs_review=1`. El vencimiento confirmado lo levanta el cron ya existente
  `flotas:check-document-expirations` — no se tocó ese pipeline.

---

## 5. BLOQUEOS ABIERTOS DE PRODUCCIÓN (críticos)

- **#126 — Divergencia dev/prod sin commitear (portero del deploy):** Carlos parchó
  prod directo, NO en git:
  - `.env` prod: `QUEUE_CONNECTION=sync` (para que `PaymentClientJob` corra inmediato).
  - Usuario MySQL prod: privilegio `TRIGGER` (triggers de pago corren por detrás).
  - **RIESGO:** si se reinstala `.env` o se recrea el usuario MySQL, revive el bug de
    cobros — y el fix de conciliación DEPENDE de `QUEUE_CONNECTION=sync`. Item #176
    trackea documentar esto. **Antes de cualquier deploy: verificar que sigue vivo.**
- **Backup cron no corre en prod** (estuvo 12+ días sin correr al descubrirse).
- Al desplegar: correr `FinanceDatatableModulesSeeder`, setear `CLAUDE_MODEL` + API key
  en `.env` de prod, y `crm:purge-orphan-documents` (con `--dry-run` primero).

---

## 6. PERSONAS

- **Irving:** dueño/arquitecto. Decide en el chat, CC ejecuta. Valida por screenshot.
  No programador; muy visual. Prefiere tablas y realismo honesto.
- **Diana:** mostrador/recepción. Tiene `payments_capture_manage`, NO `conciliacion.manage`.
- **Tere:** contadora. Maneja la cola de conciliación (referida como "revisión humana").
- **Isaac:** técnico de campo (usa Scheduling a diario).
- **Carlos, Yasmani:** devs part-time. Carlos tiene SSH a prod y ha parchado prod sin
  commitear (origen del #126). Irving maneja la comunicación con el equipo él mismo.
- **Distinción clave:** MegaISP/Medussa base = el co-owner/programador conoce. Módulos
  addon nuevos (MegaFamilia, Cobranza, OLT, Flotas, Talento, conciliación) = trabajo de
  Irving + Claude; NO asumir que el co-owner los conoce cuando surge un bug.

---

## 7. WHATSAPP (dos mundos + unificación por fases)

### 7.1 Los DOS mundos (hoy separados)

| Mundo | Dónde vive la config | Instancia | Qué hace | Estado |
|---|---|---|---|---|
| **Marketing (VIVO)** | Integration Hub + `App\Models\Marketing\Setting` (`evolution_api_url`, `evolution_instance_name`, api_key del Hub/env) | `meganet-ventas` | Bot de ventas + conciliación de pagos por IA. Webhook Evolution → `/webhooks/marketing/evolution` | Producción, número real |
| **WhatsAppAgent (PANEL)** | `config/whatsapp.php` (env `WHATSAPP_API_URL`=8080, `WHATSAPP_API_KEY`, `WHATSAPP_DEFAULT_INSTANCE`) + tabla `whatsapp_instances` | fila(s) del panel | Panel para conectar/ver/administrar números por QR desde Medussa | Andamiaje completo; estaba vacío |

- **Ambos apuntan al MISMO servidor Evolution (:8080) y comparten la MISMA api_key** (64 chars; el Hub la resuelve igual que `WHATSAPP_API_KEY`). Lo que difería era el mecanismo de config (DB/Hub vs archivo/env) y la tabla.
- El `EvolutionApiService` del **addon** usa la config **global** `config('whatsapp.*')` + `$instance->instance_id` para status/QR (NO las columnas `api_url`/`api_key` de la fila, que son de coherencia/futuro).
- El `EvolutionApiService` de **Marketing** resuelve por Hub/Settings (`__construct(companyId=1)`).

### 7.2 Mapa de archivos (addon WhatsAppAgent)

- Controller líneas: `.../Controllers/WhatsAppInstanceController.php` (`panel/index/store/getQr/connectionStatus/update/destroy/disconnect` + Fase 3: `functionsCatalog/assignFunction/unassignFunction/reassignFunction`). ⚠️ `store()` llama `createInstance` en Evolution → NO usarlo para reflejar la instancia viva (duplicaría). `index()` eager-carga `functions`. `connectionStatus()` persiste `phone_number` (lazy, si vacío) leyendo `ownerJid`. **`disconnect()`** = logout REAL (`POST /instances/{id}/disconnect`).
- Controller funciones (Fase 3): `.../Controllers/WhatsAppFunctionController.php` (catálogo: `panel/index/store/update/toggleExclusive/destroy`, gate `whatsapp_manage_functions` vía `WhatsAppFunctionRequest`).
- Service líneas: `.../Services/EvolutionApiService.php` (`getQrCode/getConnectionStatus/createInstance/sendAndLog` + `getInstanceProfile` → número real `ownerJid` sin `@s.whatsapp.net` + **`disconnect()`** = `DELETE /instance/logout/{instance}`, Evolution **v2.3.7**, per-instance, no-op en fakeMode).
- **Capa de funciones (Fase 3):** `Services/WhatsAppFunctionService.php` (assign/unassign/reassign — reglas en backend, punto único), `Services/WhatsAppLineResolver.php` (`lineForFunction(slug)` — lectura pura, **sin consumidores** hasta Fase 4c). ⚠️ **REGLA RELAJADA (permanente):** una función SÍ puede quedar **sin línea** → `unassign` ya NO bloquea la última asignación; **se eliminaron** los observers backstop y `WhatsAppFunctionException`/`guardInstanceRemoval`. El aviso vive en la UI.
- Modelos/tablas: `Models/WhatsAppInstance.php` (+`functions()`/`functionAssignments()`) + `whatsapp_instances` (api_key cifrada; `webhook_secret` autogen; `phone_number` = número real). `Models/WhatsAppFunction.php` + `whatsapp_functions` (catálogo, `exclusive` bool, softDeletes). `Models/WhatsAppInstanceFunction.php` (pivote plano) + `whatsapp_instance_functions` (`UNIQUE(instance_id,function_id)`).
- UI: `WhatsAppInstanceManager.vue` (líneas: estado, número, QR, checks de funciones con modales mover/reasignar/bloqueo) + `WhatsAppFunctionManager.vue` (catálogo) — ambos registrados en `app.js`, tema claro/oscuro con tokens. Blades `views/{instances,funciones}.blade.php`.
- Rutas: `.../WhatsAppAgent/routes.php`, prefijo `whatsapp`. Catálogo gate `whatsapp_manage_functions`; asignación gate `whatsapp_manage_instances`.
- **Acceso al panel (Fase 4a):** módulo **"WhatsApp" expuesto en el sidebar** (bloque hardcodeado en `sidebar.blade.php`, patrón Portal de Pago, ícono `message-circle`), con 3 submenús: **Líneas** (`/whatsapp/instances`, gate `whatsapp_manage_instances`), **Funciones** (`/whatsapp/funciones`, gate `whatsapp_manage_functions` — **revelado, Fase 3 hecha**), **Conversaciones** (`/whatsapp`, gate `whatsapp_view_conversations`). Padre gateado por `canany` de los tres. `addon-whatsapp-agent` **se mantiene en `$sidebarSuppressed`** (evita duplicado del loop dinámico). El enlace de **`/configuracion` → Mensajería** sigue conviviendo (se limpia después).
- Consumidor automático a vigilar: `PaymentApplicationService::…sendAndLog($phone,$body, null,…)` (notificador SPEI) → `active()->default()->firstOrFail()`. Si una instancia se marca `default_instance=true`, ese path empieza a enviar por ella.

### 7.3 Decisión: UNIFICAR por fases — **Opción A (ABSORBER)**

Decisión tomada: el módulo "WhatsApp" se construye **sobre el addon WhatsAppAgent** (renombrar/reencuadrar de cara al usuario, exponerlo al sidebar con submenús, y en la última fase jalar el envío de Marketing hacia acá). Menor reescritura y menor riesgo para el envío vivo; el único punto sensible es el switch final del sender, aislable tras flag. (Se descartó "módulo nuevo" por más reescritura/riesgo.)

- **Fase 1 — HECHA (dev):** exponer el panel + **reflejar** `meganet-ventas`. Migración idempotente `2026_07_04_100000_seed_meganet_ventas_instance.php` (firstOrCreate por slug, datos reales de Marketing, api_key cifrada, **`default_instance=false` + `active=true`**, status open/close en vivo). Panel protege la fila de producción (badge "En uso — producción" + Eliminar atenuado + confirmación ⚠️). **Solo refleja; no cablea nada.** Commits `a273b746` (migración) + `5dea9ac5` (panel).
- **Fase 2 — HECHA (dev):** fix dark/light del `WhatsAppInstanceManager.vue` (hardcodes → tokens `dark-light-tokens.css`; conserva verde de marca + blanco del QR). Commit `9e79a8c7`.
- **Fase 4a — HECHA (dev):** menú único "WhatsApp" en el sidebar (ver §7.2). **Solo UI de navegación** — no toca BD ni sender. Commit selectivo de `sidebar.blade.php`.
- **Fase 3 — HECHA (dev):** capa de funciones por línea. Tablas `whatsapp_functions` (catálogo, `exclusive` default true, seed Ventas/Cobranza/Soporte/Atención sin asignar) + `whatsapp_instance_functions`. Reglas en backend (`WhatsAppFunctionService`): mover exclusiva, no duplicar. **REGLA RELAJADA (permanente, decisión de Irving):** una función **puede quedar sin línea** con AVISO (antes se bloqueaba) → observers backstop y excepción **eliminados**; UI: modal de confirmación al quitar la última línea + aviso no bloqueante al borrar línea dueña única + chip "⏸ Sin línea" en el catálogo. Permiso `whatsapp_manage_functions` (5 roles admin). UI: **Gestionar funciones** (`/whatsapp/funciones`) + checks por línea. **Número real** en la tarjeta (`phone_number` del `ownerJid`). **Botón Desconectar** (logout real en Evolution, `DELETE /instance/logout/{instance}`; doble blindaje en producción: modal + teclear el nombre de la instancia). `WhatsAppLineResolver` listo para Fase 4c (sin consumidores). **No toca el sender.** Commits `33f61641`→`c6bb8c62` (nota: 3 borrados quedaron en `b4db263d` por trabajo concurrente).
- **Fase 4c — SWITCH FINAL (pendiente, roadmap 197):** unificar el **sender** — que conciliación y el bot lean la instancia/credenciales desde `whatsapp_instances`/funciones (marcar `default_instance=true` y repuntar consumidores), **detrás de feature flag con fallback a Marketing**. Único punto que toca el envío vivo.

---

## 8. CIRCUITO CC — cómo trabaja HOY (mapa estructural, #507)

> Esto se re-investigó desde cero en la sesión del 2026-08-04 porque el modelo mental "el circuito
> corre por rondas cada 30 min" **es falso**. No volver a asumirlo.

### 8.1 Ejecución: CONTINUA, una vuelta POR ITEM

- El cron corre **`circuito:scheduler` cada minuto** (no cada 30). Cada corrida busca slots libres
  (`wt-1..wt-N`, flock por slot), toma items módulo-disjuntos y lanza `deploy/circuito/vuelta.sh` en
  el worktree del slot, **una vuelta por item**.
- Una terminal que termina **jala el siguiente sin esperar a nadie** (`circuito:claim-next`,
  serializado por `claim.lock`).
- **NO existe cron de rondas ni ventana de tiempo.** `config('circuito.interval_min')` está
  **DEPRECADO**: solo alimentaba la estimación "próxima vuelta" de la UI y hoy solo se usa si se
  apaga `circuito.continuo` (default true, que hace `proximaVueltaAt()` devolver null).
- **Exclusión mutua** (dos terminales nunca toman el mismo item): `flock` + `UPDATE ... WHERE
  estado_aprobacion IN (aprobado_*)` — gana quien afecta 1 fila. **Decisión de Irving: NO migrar a
  `SELECT ... FOR UPDATE SKIP LOCKED`**; el mecanismo actual ya cumple y es el punto más caliente.
- **Orden de la cola** = `scopeOrdenCola` (distinto de `ordered()`, que ordena la BANDEJA):
  urgente → por concluirse/reanudables (`branch` no null o `colision_pausada_por` no null) →
  prioridad → antigüedad.
- **Lease**: `roadmap_items.claimed_at` se sella al reclamar y lo **renueva el latido**
  (`circuito:vivo --watch` → `liveBeat` → `renovarLease`) con un UPDATE crudo que **no toca
  `updated_at`**. `circuito:reap-stuck` libera solo si **AMBAS** señales están frías (antes bastaba
  `updated_at` y mataba workers vivos que llevaban rato sin escribir en su item).

### 8.2 Decisión: Revisor → AUTOPILOT → bandeja de Irving

Cadena de triaje, de más automático a más humano:

1. **Revisor** (#338, `RevisorService`): clasifica y autoriza B técnicos (`aprobado_revisor`). Tiene
   una **denylist de frontera dura** (dinero/seguridad/permisos/prod/destructivo/negocio) que escala
   **sin gastar IA**.
2. **Autopilot** (#507, `AutopilotService`): corre **al escribirse cada brief**
   (`RevisorService::aplicarPreguntas` → `intentar()`, best-effort). Toma la opción `recomendada`
   solo si hay **dato explícito**: `confianza >= umbral` y (nivel A **o** `reversible === true`).
   Config en `config/circuito.php` → `autopilot.*` (`max_nivel` **C** desde 2026-08-04 por decisión
   de Irving —máxima autonomía—, `umbral_confianza` alta, `requiere_reversible` true,
   `ventana_gracia` 0).
   Escribe A→`aprobado_claude`, B→`aprobado_revisor`, y deja rastro en `log` con
   `decidido_por='autopilot'` + la política vigente al decidir.
   **Kill switch = el de siempre** (`circuito_pausado`): en pausa no decide nada.
3. **Bandeja de Irving** (`RoadmapItem::scopeBandeja`): todo lo demás.

**Regla de oro del autopilot:** ausencia, ambigüedad o error → **el item va a Irving, nunca se
ejecuta**. Por eso los briefs viejos (sin `confianza`/`reversible`) NO califican: hay que regenerarlos
con `circuito:rebrief-bandeja` (ver 8.4).

**`guard()` NO es del flujo interno.** Sus únicos consumidores son `RoadmapExternalController` y
`RoadmapMcpController` = la **vía externa** (token Cowork/MCP). Relajarlo para el autopilot sería
abrirle a un token externo la aprobación de B/C. No tocarlo.

### 8.2-bis Qué puede reclamar un worker (guard ÚNICO de despacho, 6e46d55a)

**`RoadmapItem::scopeElegibleParaPool()` es la única puerta.** La usan `ejecutablesParalelo()`
(scheduler + `claim-next`), `scopeAutoEjecutable()` y `circuito:destrabe`; `claimNextParalelo()`
repite las mismas condiciones en el `UPDATE` como candado atómico. Deja FUERA:

- Rótulos de frontera dura **`[BLOCKED-…]` / `[PARKED-…]`** (antes solo se excluía `[PARKED-PROD]`).
  **Desbloquear un item rotulado = QUITARLE el rótulo al título**, nunca re-aprobarlo con el rótulo
  puesto (aprobarlo así solo reabre el ciclo; el endpoint `decidir` lo avisa en la respuesta).
- **`esperando_merge_irving`** = estado TERMINAL de despacho: nivel C (o auto-merge OFF) cuya rama
  **tiene commits** ya no vuelve a `requiere_irving` desde `circuito:integrar` — se parquea con
  `excluir_pool_automatico=1`, sale de la bandeja y vive en **Integración** hasta que Irving mergea
  (`MergeRunner` → `completado` con `merge_commit`). Rama VACÍA = sí es decisión suya.
- **`excluir_pool_automatico`** (master switch: lo activan `bloqueado_por_bucle` y
  `requiere_sesion_supervisada`).
- **Anti-bucle**: 3 escalaciones seguidas a `requiere_irving` con la MISMA huella
  (`escalaciones_fingerprint` = rama + opción elegida + nivel + preguntas) → `bloqueado_por_bucle`
  + fuera del pool. Un cambio material reinicia el contador.
- **Tope de nivel del autopilot**: lo aprobado AUTOMÁTICAMENTE no puede superar
  `autopilot.max_nivel`; `aprobado_irving` (aprobación explícita) siempre pasa.

Re-aprobar un item parqueado desde la Torre responde **422** con la acción que sí lo mueve
(mergear / destrabar); `forzar=true` limpia el parqueo a propósito. El cierre manual de Irving
(`cerrar`/`cancelar`) se respeta siempre (bandera transitoria `cierreManualIrving`).

**Por qué existe esto:** sin el guard, un `[BLOCKED-NEGOCIO]` aprobado o un C que solo esperaba
merge seguía siendo reclamable → el worker lo tomaba, leía el rótulo, lo re-escalaba sin ejecutar y
volvía a la bandeja → se aprobaba otra vez. **#117 dio 13 vueltas idénticas; #99, dieciséis.** Cada
vuelta quema un slot de terminal y tokens para no hacer nada.

### 8.3 Contrato del brief (`roadmap_items.preguntas`, JSON)

```
[{ id:"q1", pregunta:"…", fase:null, requiere_irving:false,
   opciones:[{ texto:"…", recomendada:true, confianza:"alta|media|baja", reversible:true }],
   opcion_elegida:null }]
```

- `confianza`/`reversible` en **null = SIN DATO** (briefs viejos) → el autopilot no los toma.
- Los booleanos se leen con **`RoadmapItem::boolEstricto`**: la coerción de PHP falla hacia el lado
  peligroso (`(bool)"si"` y `!empty("false")` dan TRUE). Ante cualquier ambigüedad: false.
- `preguntasNormalizadas()` conserva el fallback `stripos('RECOMENDADA')` para los items legacy.
- ⚠️ **Los IDs de pregunta son POSICIONALES** (`q1`, `q2`…). `aplicarPreguntas` conserva las
  respuestas por ID, así que **re-briefear un item ya respondido pega la respuesta vieja a una
  pregunta nueva**, con una clave de opción que ya no existe. Por eso el backfill los salta.

### 8.4 Comandos útiles

| Comando | Para qué |
|---|---|
| `circuito:autopilot --dry` | **Auditar la política** sin escribir (ignora la pausa a propósito) |
| `circuito:rebrief-bandeja --solo-resumen` | Checkpoint: cuántos calificarían al autopilot, por nivel |
| `circuito:rebrief-bandeja --apply` | Backfill de briefs viejos (**exige kill switch activo**) |
| `circuito:proponer-opciones --todos --apply` | Brief para B/C de la bandeja sin brief |
| `circuito:thomas --diagnostico` | Estado del reparto: libres/ocupadas, cola, colisiones, ocio-con-cola |
| `circuito:thomas --dry` | Evalúa consultas colgadas sin escribir |
| `circuito:reap-stuck --minutes=N` | Libera reclamos huérfanos de workers muertos (**ojo**: los manda a `requiere_irving`) |
| `circuito:auditor` | **DRY-RUN** del Motor de Auditoría: qué items crearía y por qué (no escribe) |
| `circuito:auditor --dod` | Qué módulos están en su DoD de Fase 1 (sin gaps mecánicos) |
| `circuito:auditor --apply` | Genera trabajo en vivo (respeta umbral, intervalo y los dos kill-switches) |

### 8.4-ter MOTOR DE AUDITORÍA CONTINUA — el generador de trabajo (#559, 2026-08-08)

> El circuito sabía **repartir** (scheduler) y **juzgar** (Thomas/revisor/autopilot), pero no
> **generar**: con la cola vacía, las 6 terminales quedaban ociosas hasta que un humano escribiera
> items. Este motor cierra ese hueco. Manual completo: **`docs/motor-auditoria.md`**.

- **Dónde vive:** `AuditorService` + `circuito:auditor` + `config/circuito.auditor` +
  `Support/InventarioSemilla.php`. Enganchado DENTRO de `circuito:scheduler` (mismo motivo que
  Thomas: el scheduler es el único despachador), **antes** de calcular slots → lo que genera se
  reparte en esa misma vuelta.
- **Corre si:** motor encendido **Y** circuito sin pausar **Y** cola < `umbral_cola` (3) **Y** pasó
  `min_intervalo_minutos` (15). **Kill-switches:** `auditor.enabled` (propio, `--forzar` NO lo salta)
  y `circuito_pausado` (global). **Cap** duro: 10 items/ciclo.
- **La cola se mide con `ejecutablesParalelo()`**, la misma puerta del scheduler — NO contando
  `aprobado_irving`: al 2026-08-08 había 87 aprobados y **0 reclamables** (35 fuera del pool, 26
  esperando merge, 25 rotulados). Contar en bruto = creer que hay cola con la flota parada.
- **Round-robin entre módulos, no un módulo a la vez.** `modulo` es el footprint con el que el
  scheduler serializa: 10 items del mismo módulo ocupan UNA terminal y dejan 5 ociosas.
- **Seis detectores:** hueco ruteado (ruta activa + cuerpo vacío) · enlace de `module.json` que no
  resuelve · TODO/FIXME en comentario · andamiaje resource sin ruta (1 item por módulo) · items sin
  footprint · semilla del inventario (con `vigente` auto-verificable).
- **Clasificación:** frontera dura de Thomas (reusada, no duplicada) → producto; texto que pide
  decidir → producto; resto → mecánico nivel A. Producto = `requiere_irving` + `preguntas` con
  `requiere_irving: true`, jamás reclamable.
- **Dedup en 3 capas:** huella `roadmap_items.auditor_fingerprint` (abiertos Y cerrados) · título
  contra lo que crearon Irving/Cowork/terminales · re-chequeo pre-escritura. ⚠️ **El módulo es parte
  de la identidad**: sin ese guard, "GestionRed: eliminar 1 método de andamiaje" mataba a "Mapas:
  eliminar 93 métodos de andamiaje" (primera corrida creó 6 de 10).
- **Fase 2 (enganche listo):** `AuditorService::medirContraSpec()` devuelve `[]` a propósito. Cuando
  existan los spec items por módulo con su DoD, se llena ahí y no se toca nada más; `InventarioSemilla`
  se retira.

### 8.4-bis TORRE V2 — Thomas, la autoridad intermedia (2026-08-08)

> Antes, la ÚNICA salida de una terminal que dudaba era `requiere_irving`: no había nadie entre las
> seis terminales e Irving, así que cualquier titubeo lo despertaba. Thomas es ese eslabón.

- **`ThomasService`** + `config/circuito.php → thomas`. La política es **DETERMINISTA** (coincidencia
  de términos, sin llamada a IA): la terminal corre `circuito:consultar` y recibe respuesta **en el
  acto**. El contrato es el **exit code**: `0` procede, `1` detente. No espera turnos del loop.
- **Conjunto de escalamiento** (lo único que llega a Irving): **producción · borrar datos · gastar
  dinero · credenciales/seguridad**, más el spec contradictorio. Todo lo demás lo decide Thomas.
  Si ninguna opción propuesta es `reversible`, esa ausencia es la señal → escala.
- **Thomas NO reparte trabajo.** El reparto (slots, módulo-disjunto, reclamo atómico, lease) sigue
  siendo del `circuito:scheduler`, único despachador desde #432 B1. La vuelta de Thomas va
  **enganchada** al scheduler (que ya corre cada minuto), NO en un cron paralelo — uno aparte abriría
  una segunda carrera sobre los mismos items. Respeta el kill switch como el autopilot.
- **Prompt del ejecutor** (`deploy/circuito/prompt-item.txt`): la **regla de oro** va al frente —
  ante duda, opción recomendada → avanza → registra (`circuito:reportar --tipo=decision`); revisión
  POSTERIOR, no previa. La terminal **ya no puede escalar a Irving por su cuenta**.
  `deploy/circuito/prompt.txt` (modo backlog) conserva la política vieja pero está **inalcanzable**:
  el scheduler siempre pasa `CIRCUITO_ITEM`.
- **Kit de la terminal:** `circuito:consultar`, `circuito:reportar`, `circuito:sub-item`.
- Doc de la política: `docs/politica-thomas.md` (se anexa al manual que sirve la API externa).

### 8.4-ter API EXTERNA extendida — alta de items e historial (2026-08-08)

- **Alta:** `POST /{token}/item` y `GET /{token}/crear/{modulo}/{titulo_b64}/{spec_b64?}` (base64url,
  porque el fetcher de Cowork solo hace GET y descarta el query string — mismo motivo que `/setb64`).
  Punto único **`RoadmapIntakeService`**, compartido por la vía externa, las terminales (sub-items) y
  Thomas. **Candado: el item nace SIEMPRE `pendiente_revision`** — crear no aprueba. El nivel
  declarado se sella con su origen real, así el guard #260 sigue impidiendo el auto-aprobado externo.
- **Historial append-only:** tabla `roadmap_item_reports` + `RoadmapReportService`
  (`POST /{token}/item/{id}/reporte`, `GET /{token}/item/{id}/historial`). Antes cada terminal
  concatenaba a mano sobre `comentarios_claude` y con seis escribiendo se pisaban. Esa columna
  ahora es un **espejo legible acotado**, no la fuente.
- **`estado_cola`** es un accessor **DERIVADO, no una columna** (a propósito: los datos ya viven en
  `estado_aprobacion`/`worker_sid`/`branch`/`merge_commit`, y un espejo almacenado se
  desincronizaría entre scheduler, reaper, merge-runner y las seis terminales):
  `en_cola|asignado|en_progreso|en_verificacion|completado|esperando_irving|sin_triar`.
- Token `create_token` propio y rotable; **cae al `write_token`** si no se define (no rompe a Cowork).

### 8.4-quinquies CARRIL MECÁNICO y crear=ejecutar (#566, 2026-08-08)

- **Carril mecánico** (`ThomasService::clasificarMecanico`): auto-aprueba lo que **no tiene nada que
  decidir** (hueco ruteado, andamiaje muerto, ruta 404, clasificar footprint) sin exigir brief, que
  es lo que dejaba items obvios parados en la bandeja. **Cuatro puertas**: (1) fuera del conjunto de
  escalamiento —se reusa el MISMO `thomas.escalamiento` de las consultas, no una copia—, (2) sin
  negocio/producto, (3) **allowlist** de señales mecánicas (sin señal conocida → se queda con
  Irving), (4) nivel ≤ B (un C es decisión de diseño). Tope diario 25 + kill switch de siempre.
  Reusa `aprobado_claude`/`aprobado_revisor`: no inventa estado.
- `circuito:retriar-bandeja` pasa esa política sobre la bandeja y **agrupa por motivo** lo que se
  queda. **NO toca**: `bloqueado_por_bucle` (re-aprobar sin cambio material reabre el bucle),
  items con rama empezada, ni los que sólo esperan merge.
- **Crear = ejecutar** (`RoadmapController::store`): un item creado en la Torre nace
  `aprobado_irving` y entra directo a la cola, con footprint auto-asignado. **La vía externa
  (Cowork/auditor) sigue naciendo `pendiente_revision`** — el candado de la máquina no cambió.
  Excepción: si declara frontera dura, se para y avisa qué categoría lo detuvo.
- `circuito:disparo-check` dejó de ser NO-OP: es un **watcher** que adelanta una corrida del
  scheduler (~0.45 s). **No es un segundo despachador** — invoca al scheduler, que sigue siendo el
  único y tiene su propio flock. Corre como `meganet` (dueño de los worktrees).
- **Reaper rápido**: además del camino lento por timestamps, pregunta por el **flock del slot** (el
  kernel lo suelta aunque el proceso muera de golpe) → libera en minutos, no en 25.
  `RoadmapCircuitoService::slotLibre()`, fail-closed. Cron cada 2 min.

⚠️ **DOS LECCIONES DE FORMA que ya costaron dos veces** (aplican a TODO mapa de términos del
circuito: denylist del revisor, escalamiento de Thomas, clasificador, señales mecánicas):

1. **Palabra completa, no substring.** «Portal colaborador» caía en el módulo del circuito porque
   *cola* vive dentro de *colaborador* — igual que 'login'/'token' en el denylist del revisor (#338).
   El `\b` de PCRE **no sirve con acentos** (la í de «auditoría» rompe el borde): usar
   `(?<![\p{L}\p{N}])…(?![\p{L}\p{N}])` con `/u`.
2. **No distingue mención de negación.** Un spec que dice "esto NO es decisión de negocio" contiene
   *negocio* y se escala igual. Falla hacia el lado seguro, pero castiga los specs bien escritos.

### 8.4-quater ⚠️ EL FRENO QUE QUEDA — footprint desconocido serializa TODO

- Un item con `modulo` **"Sin clasificar"/null/vacío** tiene footprint DESCONOCIDO y por diseño
  (#432 B2) **corre SOLO: bloquea a las 6 terminales** mientras esté en vuelo. Hoy son **27 de 286**
  items activos.
- Peor: un **reclamo huérfano** (worker muerto que dejó el item en `en_progreso`) mantiene ese
  bloqueo hasta que `circuito:reap-stuck` lo libera, y el reaper exige **25 min con AMBAS señales
  frías**. Media hora con la flota entera parada por un item que ya no se está trabajando.
- Es el mayor freno de throughput que queda y es territorio del item **#526** (drift de `modulo`).

### 8.5 UI de la Torre (`/releases`)

6 pestañas en `ReleasesIndex.vue` (Panorama, Hoja de ruta, Terminales, Integración, Historial,
Reporte). El **Panorama** (`TorreControl.vue`) ya no habla de "vuelta": muestra terminales
trabajando/libres, el **banner del autopilot**, la bandeja **una pregunta a la vez** ("Pregunta X de
Y", Aprobar deshabilitado hasta responderlas todas) y un **sidebar interno** con bombitas por módulo
(`GET /api/roadmap/torre/decisiones/contadores`) — ese sidebar es de la pantalla, **no** el sidebar
global del sistema.

⚠️ **`modulo` es texto libre** y hay *drift* (item #526): ~12 de 20 grupos no mapean a
`module_sidebar_config`, y hay duplicados (`Auth` vs `Autenticación`). Además de las bombitas, eso
degrada el pre-filtro de no-colisión, que serializa por ese mismo campo.

---

### 8.6 FASE 2A — las tres reglas que dejaron de vivir en la memoria (2026-08-18)

> Las tres nacieron del mismo diagnóstico: **una regla que vive en un solo lugar (o en ninguno) se
> cae sin que nadie se entere.** Por eso cada una tiene su candado, y el candado es un test o un
> exit code, no un párrafo.

**(1) El predicado de despacho tiene UNA definición.** `RoadmapItem::sqlElegibleParaPool()`. La
aplican el scope (`scopeElegibleParaPool`, que filtra el SELECT del scheduler) y el candado atómico
del reclamo (`RoadmapCircuitoService::claimNextParalelo` → `guardReclamoAtomico`). Llegó a vivir en
**cinco** dialectos —scope, SQL crudo del reclamo, `preg_match` del título, copia a mano de
`SupervisorService` y la Vue— y cada vez que uno cambió, los otros se quedaron atrás. La del reclamo
es la CARA: decide qué toca un worker, así que una deriva ahí es una terminal trabajando sobre algo
que no debía.
- Candado sin BD: `tests/Unit/Modules/Addons/Roadmap/PoolGuardCoherenceTest.php` (compara el SQL y
  los bindings de los dos caminos + falla si el reclamo vuelve a enumerar banderas a mano).
- Candado sobre datos reales: `php artisan circuito:coherencia-pool` (READ-ONLY, exit 1 si divergen;
  al 2026-08-18: 283 items, 211 = 211). De paso reporta cuántos dependen del fallback del rótulo:
  **0** — cuando lleve una semana así, el `LIKE` sobre `title` se puede retirar.

**(2) Los frenos son ASIMÉTRICOS** (decisión de Irving, 2026-08-18):
- `origen_bloqueo = 'clasificador'` → **caduca solo** a los `circuito.retriage.clasificador_caduca_dias`
  (14) si nadie lo confirmó. Es un consejo automático; ya no frenaba nada desde 2A.3, así que vencerlo
  sólo lo calla.
- `origen_bloqueo = 'humano'` → **NUNCA caduca.** Es una decisión de Irving y el sistema no la revoca
  por antigüedad. Se **RESURFACEA**: `circuito:digest` §4 la lista cada `resurface_dias` (7) con item,
  fecha, días en pie, **aprobaciones mudas acumuladas** y lo que decía el rótulo. Los que más mudas
  acumulan van arriba: ahí Irving decidió una cosa y quiere otra. Los 33 vivos no son items
  bloqueados por error — son decisiones que olvidó haber tomado; caducarlas se las quitaría a la mala.
- La regla vive en **tres** sitios: la AUSENCIA de la clave en `config/circuito.retriage`, el
  fail-closed de `RetriageFrenosCommand::handle()` y `RetriageNoRevocaFrenoHumanoTest`.
- ⚠️ `frenoDesde()` marca los días como **aproximados** (`38+`) cuando el freno es anterior al rastro
  más viejo del item: los 33 legacy sólo se sellaron en columna el 2026-08-18, y usar esa fecha diría
  "0 días" para todos. Es una cota inferior honesta, no una fecha inventada.

**(3) El guard #456 tiene PRECEDENCIA escrita.** Ampliado a `aprobado_irving`
(`RoadmapItem::ESTADOS_SINCRONIZABLES_DESDE_KANBAN`), las dos direcciones quedan activas a la vez:
`status → estado_aprobacion` (Kanban legado) y `estado_aprobacion → status` (hook de `completado` +
parqueo de C-con-rama). **Gana `estado_aprobacion`** — es la máquina de estados real; `status` es el
espejo Kanban. El guard corta con `if ($item->isDirty('estado_aprobacion')) return;`. Sin ese corte,
los tres callers que escriben AMBOS campos (`decidir`, `integracionRechazo`,
`MergeRunner::markMerged`) quedaban a merced del **orden de registro de los hooks**, que es frágil e
invisible en el diff. Candado: `GuardKanbanPrecedenciaTest`.
- Los veredictos (`aprobado_claude`/`aprobado_revisor`, `requiere_irving`, `completado`, `cancelado`,
  `rechazado`) quedan FUERA del set: mover una tarjeta en un tablero no deshace un veredicto.

**(5) Escalar por JUICIO y escalar por NO HABER MODELO ya no se ven igual (#807).** Es el hallazgo
más incómodo de la fase y de una familia peor que las otras cuatro: aquí **no hay un lector
equivocado que corregir** — el lector es un humano viendo una historia coherente. Con la IA caída el
circuito no se cae: escala todo, el autopilot deja de calificar, y el tablero cuenta que está siendo
prudente. Nada contradice esa historia.
- `RevisorService::CAT_SIN_MODELO` (la llamada falló, el modelo nunca contestó) y `CAT_ILEGIBLE`
  (contestó, pero no salió veredicto usable). Antes las dos se guardaban como `duda`, idéntico a un
  juicio real. **La falla-segura NO cambió** — sólo dejó de ser anónima.
- `proponerOpciones`/`proponerPreguntas` devuelven `motivo`: `'sin_modelo'` vs `'vacio'`. Ése es el
  caso caro: sin `preguntas` el autopilot no califica NADA, y eso es indistinguible de "briefs viejos
  sin confianza/reversible", que es benigno y esperado. `auditarSinModelo()` deja rastro en
  `circuito_revisiones` (misma tabla que ya cuenta el digest; no se inventó una segunda bitácora).
- `circuito:digest` §2-bis separa `autoriza` / `escala por juicio` / `escala SIN MODELO`.
  **`escala:sin_modelo > 0` es lo único que no se puede confundir con prudencia.**
- Cada llamada real es su propia sonda: no hay canario aparte que mantener (que sería, otra vez, una
  segunda definición esperando a quedarse obsoleta).

**(6) Todo proceso programado late, y el digest delata al que no (#808).** Una regla implementada y
NO agendada es un **no-op invisible**: 2A.4 dejó el caducado del clasificador escrito, probado y
fail-closed… y sin su línea de cron no caduca nada.
- `config('circuito.procesos_programados')`: scheduler · re-triage · digest · priorizar-seguridad,
  cada uno con `max_horas` y **qué se pierde** si deja de correr (un "no ha corrido" sin consecuencia
  se ignora).
- **UN solo listener** de `CommandFinished` sella el latido: nadie instrumenta comando por comando, y
  un proceso nuevo sólo necesita su fila en la config.
- `exige_opciones`/`excluye_opciones`: un `--dry` no sella latido. Sin eso, correr el comando a mano
  desde una sesión enmascararía que el cron no existe — justo la mentira que el vigilante evita.
- `agendado()` mira el crontab de verdad y separa **NO AGENDADO** (falta la línea) de *agendado pero
  sin latir* (corre y falla). Piden cosas distintas.
- Es la **§0 del digest**, lo primero que se lee.

**(7) La racha del fallback se mide, no se recuerda (2A.6).** El digest sella el día que
`contarFallbackRotulo()` llegó a 0 (**2026-08-18**), reinicia si vuelve a subir, y sólo a los 7 días
seguidos avisa que ya es seguro retirar el `LIKE` sobre `title`. Una fecha anotada en un reporte es
justo lo que nadie vuelve a mirar.

**(4) `config:cache` volvió al checklist, detrás de un exit code.** `php artisan config:auditar-env
&& php artisan config:cache`. Ver CLAUDE.md (#790). **Hallazgo que conviene no olvidar:** con la
config cacheada el circuito NO se queda sin llave (el Hub `api_integrations` responde antes que el
`env()`), pero si esa fila se cae, la degradación es **silenciosa** — el revisor escala todo con
"confianza baja" y se ve *prudente*, no roto. Item **#807**.

---

### 8.7 FASE 2B — el generador construye su propio sustrato (2026-08-18)

> `AuditorService::medirContraSpec()` dejó de devolver `[]`. Manual: `docs/circuito/directiva-2b.md`;
> la medición que lo fundamenta: `docs/fase2b-paso0-inventario-modulejson.md`.

**Por qué no se escribió el detector semántico.** El Paso 0 midió que los `module.json` describen
**5.5 %** de la superficie (117 endpoints declarados / 2,117 rutas atribuibles a un módulo). Un
detector semántico perfecto sobre ese 5.5 % habría dado dos docenas de items y se habría vuelto a
secar. La pregunta no era cómo escribirlo, era **por qué el sistema no tiene con qué medirse**.

**El primer producto son huecos de DECLARACIÓN**, y tienen la propiedad que se buscaba desde el
principio: **cada `module.json` que se completa amplía la superficie que el detector puede medir en
la vuelta siguiente.** El generador se alimenta a sí mismo porque su primer trabajo es construir el
instrumento con el que va a medir después.

Cuatro detectores, todos por LOOKUP (cada hallazgo traza a un conteo, no a un juicio):

| tipo | regla | gaps (2026-08-18) |
|---|---|---:|
| `spec_declaracion_incompleta` | declara < `umbral_cobertura` (30 %) de sus rutas | 21 |
| `spec_modulo_sin_declarar` | rutas registradas y 0 `api_endpoints` | 15 |
| `spec_desalineada` | declarado ≠ registrado, **dirección desconocida** | 5 |
| `spec_permiso_inexistente` | permiso declarado ausente de `permissions` | **0** |

- ⚠️ **`spec_desalineada` NUNCA dice "falta construir X".** Detectar la discrepancia con certeza no es
  saber qué falta: confianza 1.0 en la discrepancia, **0 en el diagnóstico**. Medido: Flotas declaraba
  `/api/flotas/*` teniendo 65 rutas bajo `flotas/api/*`, y Planes URLs que nunca existieron — los dos
  son la DECLARACIÓN envejecida. Un detector que dijera "falta construir" fabricaría trabajo para
  reconstruir lo que ya existe con otro nombre, que es peor que ruido.
- `spec_permiso_inexistente` en **0 es lo sano, no un detector roto**: su valor es de guardia contra
  regresiones (111/111 permisos declarados existen).
- **Items acotados por tanda** (`cap_por_item` = 25) y la **huella de dedup incluye el tramo**
  (`api_endpoints#tN`) → la vuelta siguiente pide la tanda siguiente. Sin progreso la huella no
  cambia y no se re-crea (falla hacia el lado bueno). Pedirle a Mapas «declara 200 endpoints» no es
  una tarea, es un proyecto.
- **`screens` NO se detecta todavía** (`detectores.sin_screens = false`): sólo 9/43 módulos las
  declaran. Enriquecerlas es el trabajo que producen los items de arriba.

**Métrica de convergencia — `circuito:digest` §5: SUPERFICIE DECLARADA.** Hoy 5.5 %. Mientras suba,
el generador tiene trabajo. El denominador son **sólo las rutas atribuibles a un módulo**: las 133 de
controllers legacy fuera de `app/Modules` no pertenecen a ningún manifiesto y no pueden declararse
por esta vía — meterlas haría la métrica inalcanzable, y una métrica con techo imposible se deja de
mirar.

**Las DOS capas del spec** (no confundirlas, ambas sirven):
- `module.json` → **estructura** (endpoints, permisos, pantallas). Vive con el código. Es lo que se
  mide hoy.
- Item `[SPEC]` → **intención** (criterios de DoD en prosa que Irving escribe desde la Torre sin
  desplegar). **Pendiente, no descartado**; hoy hay 0 items `[SPEC]`.

⚠️ **Hueco de cobertura (item #809):** `circuito.auditor.carriles` lista 26 módulos, así que **14 de
los 41 gaps son invisibles para el motor**. Y dos entradas **no resuelven a ningún directorio** —
`Roadmap / Circuito CC` es el *footprint*, no el nombre del módulo (`app/Modules/Addons/Roadmap`), y
`Reportes` no existe: `rutaModulo()` devuelve null y `detectarGaps` sale por lo bajo **sin avisar**.
Consecuencia: **el módulo del propio circuito nunca se ha auditado.**

### 8.X LA VIGILIA DE THOMAS (2026-08-25) — la mitad que NO depende de la base

`ThomasService::tick()` decide (consultas colgadas + sellado de esfuerzo) y **cuelga del cron del
scheduler**: cuando el 24-ago se comentaron las nueve líneas del circuito, Thomas se quedó **un día
sin latir sin que ninguna pantalla lo dijera**. Por eso se separó su mitad de OBSERVACIÓN:

- **`circuito:thomas-vigilar`** — mide disco, RAM/swap, carga, **los nueve `laravel.log`**,
  procesos, registro de PIDs, freno y edad del snapshot. La base va **al final y en `try/catch`**:
  si no responde, `modo: minimo`, se dice con esas palabras y la vuelta se guarda igual.
- **Cron PROPIO**: `deploy/circuito/vigilia-wrap.sh`, cada minuto. **NO cuelga de `cron-wrap.sh`**
  a propósito: un barrido de `PAUSADO-` no puede dejar ciego al vigilante.
- **Estado en ARCHIVO**, ruta absoluta (misma razón que el centinela del freno #170):
  `storage/app/circuito/thomas/{latido,estado}.json`, escritura atómica. El latido se escribe
  **después** del estado: si la medición falla, el latido envejece y el hombre muerto se dispara.
- **Hombre muerto en la Torre**: compuerta `thomas` — rojo si nunca midió o si el latido pasa de
  180 s; **ámbar** en modo mínimo (vivo pero sin base).
- **REGISTRO DE PIDs** (`storage/app/circuito/thomas/pids/<sid>.json`, lo escribe `vuelta.sh` en
  **bash** con `trap EXIT`): identidad = **PID + `starttime`** (campo 22 de `/proc/<pid>/stat`),
  porque los PID se reciclan. Es el **prerrequisito de cualquier autoridad para matar**: lo que no
  está en el registro NO es del circuito. `ps | grep claude` incluye las sesiones interactivas de
  Irving — `pkill claude` es autoinmune. `RegistroPids` **no tiene método `matar()`**.
- **UMBRALES DE DISCO: una sola política**, `config/umbrales_disco.php` (80 avisa · 85 comprime ·
  90 trunca · 95 pausa). `config/torre_salud.php` **ya no los define**, los deriva. Antes decía
  85/93 y pintaba verde al 82 %.
- **Defecto de medición corregido**: había **nueve** `laravel.log` (uno por worktree, cada uno con
  su `storage/` real) y la sonda medía uno. `wt-2` acumuló **1.86 GB** invisible.

⚠️ **El `pkill claude` autoinmune YA PASÓ de verdad (item #215, 2026-08-25):** cortar una vuelta a
mano con `pkill -TERM -f 'claude -p Eres un EJECUTOR ON-BOX...'` se mató a sí mismo (el shell que
ejecuta el `pkill` trae el patrón LITERAL en su propia línea de comando → `pkill -f` lo encuentra
también a él) y además dejó huérfano el latido `circuito:vivo --watch` (su cmdline no matchea el
patrón). **Comando seguro:** `php artisan circuito:cortar-vuelta --sid=wt-K` (dry-run por default,
`--confirmar` para cortar de verdad) — usa el registro propio (PID+`starttime`) para matar por
**PGID**, nunca por patrón de cmdline, y verifica APARTE que no sobrevive nada del grupo. Detalle y
runbook completo: `docs/circuito/cortar-vuelta-runbook.md`.

Doc: `docs/circuito/thomas-vigilia-entrega-a.md` · inventario previo:
`docs/circuito/thomas-vigilante-paso0.md` · contrato del chat: `docs/circuito/thomas-chat-contrato.md`.

---

## 9. ACTUALIZACIONES DE INSTANCIA (modelo PULL) — #529

> Investigado y arreglado el 2026-08-06. Antes de tocar nada aquí, leer esta sección: el
> síntoma ("prod dice *Estás al día* con una versión nueva publicada") NO es del comparador.

### 9.1 La cadena, de punta a punta

```
Botón "Buscar actualizaciones"  (UpdateBanner.vue)
  → POST /api/updates/check → UpdateController::check()
  → GitHubUpdateService::refresh()/check()
  → GET https://api.github.com/repos/{GITHUB_REPO}/releases/latest
  → compara contra la versión INSTALADA (tabla local `releases`, la más reciente)
```

- **La fuente de verdad es la GitHub Releases API, NO la BD ni los tags de git.** La tabla
  `releases` local solo dice qué está instalado en esa instancia.
- `releases/latest` devuelve **solo objetos Release** (no-draft, no-prerelease). **Un tag de
  git NO es un Release**: se puede tener el tag en origin y el checker no verlo. Fue
  exactamente el bug de V1.26–V1.29.
- **Comparación** (`GitHubUpdateService`): por **tag** (`!==`, cubre varias releases el mismo
  día) + gate de fecha con Carbon **normalizado a `app.timezone`** (`gte` sobre `startOfDay`,
  evita "downgrades"). `releases.release_date` es `DATE` real. **La comparación está sana —
  no la "arregles" por fecha ni por número de versión.**

### 9.2 Publicador vs consumidoras (quién crea el Release)

| | DEV (.11) | PROD (.108) |
|---|---|---|
| Rol | **publicador** | consumidora |
| `GITHUB_UPDATES_ENABLED` | false (no consulta) | true (consulta y ofrece) |
| `DEPLOY_IS_PUBLISHER` | **true** | ausente ⇒ false |

- El paso `github_release` del pipeline lleva **`skip_if_not_production`**, y dev es
  `APP_ENV=local` ⇒ **se omite siempre en el publicador**. Es la política del item **#245**
  (que dev no dispare deploys reales) y **NO se reabre**.
- Por eso publicar es un acto **explícito**: `php artisan releases:publish-github {version}`
  (`--dry-run` muestra las notas sin llamar a la API). Guard duro por
  `config('deployment.publisher')` ⇒ **producción aborta aunque se corra allá**. Cada
  publicación queda en `deployment_logs`, atribuida al usuario de sistema MEGAISP.
- Las notas se arman en **`DeploymentService::buildReleaseBody()`** — punto **único**
  compartido por el pipeline y el comando. Orden: `ReleaseDescription` → `releases.summary` →
  `releases.description`. Tolera el caso en que el generador por IA dejó **JSON crudo** en la
  columna (pasó en V1.27) y rescata las notas en vez de publicar el JSON.
- **Publicar en orden ascendente** cuando son varias: `releases/latest` debe terminar en la
  más nueva.

### 9.3 Tres estados del checker (no confundir los dos últimos)

`GitHubUpdateService::check()` devuelve:

| Resultado | Significado | Banner |
|---|---|---|
| array con `tag` | hay actualización | ofrece actualizar |
| `null` | no hay actualización — **respuesta confiable** | "Estás al día" |
| array con `check_failed` | **no se pudo consultar** (red, token, 403 rate-limit) | "No se pudo verificar" |

- Antes, el fallo devolvía `null` ⇒ un token vencido se veía **idéntico** a "estás al día".
- `UpdateController::apply()` **aborta con 422** ante `check_failed` (sin ese corte
  dispararía un deploy con versión nula).
- El error se cachea solo `updates.error_cache_minutes` (2 min), no los 30 del resultado bueno.
- ⚠️ El resultado "sin actualización" (`null`) **no se cachea** → cada carga del dashboard de
  una consumidora consulta GitHub. Preexistente; vigilar rate-limit si crecen las instancias.

---

## 10. LOS DOS CANDADOS QUE DEJÓ EL INCIDENTE DEL 2026-08-25

> Detalle completo: `docs/bitacora-sesiones.md` (entrada del 25-ago 18:14) y
> `docs/circuito/reporte-noche-20260826.md`. Aquí sólo el mapa.

### 10.1 La base de pruebas — regla en tres capas, una sola definición

`phpunit.xml` declaraba `DB_DATABASE=megaisp` y `TestCase::setUp()` corre `migrate:fresh --seed`:
correr la suite vaciaba dev, y era la configuración por defecto del repo. **La regla ya estaba
escrita en CLAUDE.md** — y las seis terminales no leen prosa. Ahora es un exit code:

| capa | dónde | qué atrapa |
|---|---|---|
| PHP | `tests/GuardBaseDePruebas.php`, aplicado en `Tests\CreatesApplication` | cualquier invocación de phpunit **si el árbol tiene el candado**. `CreatesApplication` es el único punto por el que pasan las dos familias de tests del repo (los que extienden `Tests\TestCase` y los que usan `RefreshDatabase` sobre la TestCase de Illuminate), y corre antes de `setUpTraits()`. |
| bash | `deploy/circuito/guard-bd-pruebas.sh`, llamado desde `vuelta.sh` y `cron-wrap.sh` | el árbol que **NO** tiene el candado: una rama anterior a `04ec4395`. Los wrappers no se bifurcan (el cron los invoca por ruta absoluta), y `vuelta.sh` verifica el worktree antes de soltar al agente. |
| git | `main` mergeado en cada rama viva | que reanudar una rama vieja no devuelva el `phpunit.xml` malo. |

⚠️ **`megaisp_test` no se puede construir sólo con migraciones**: queda en **236 tablas de 502** (es
la deuda del catálogo atrapado en `migrations_old/`). La base protege, pero la suite todavía no corre
entera — item **#230**.

### 10.2 Un guardrail no puede depender del estado que protege

`MigrationGuardService` no podía leer la tabla `migrations` —la que el `migrate:fresh` acababa de
borrar— y en la rama del #171 fallaba CERRADO: por eso dev quedó en **0 tablas y no en 500**. El
freno no evitó el daño, impidió la reparación. La regla que quedó, y que aplica a **cualquier** freno
del sistema:

1. La decisión de **bloquear** se toma con git y archivos, nunca consultando la base que se protege.
2. Bloquear una acción destructiva **no es** bloquear la reparación: sin estado que leer
   (`estadoAplicadoLegible()` = false) se **permite** y se dice con esas palabras en el log.
3. La base sólo puede volver el guardrail **más estricto**, nunca ser el motivo de que falle.

Candado: `MigrationGuardBaseCaidaTest` ejecuta el guardrail **con la conexión caída**.

⚠️ Corolario para el chequeo `bd_integra` (item #228): ahí la regla va **al revés**. Es un chequeo
*sobre* la base, así que no poder medir es `critico`, nunca `ok` por ausencia de datos. Un chequeo
que no puede medir no reporta salud.

### 10.3 Recuperación: el binlog es la red que el dump no es

El dump más nuevo tenía 30 h de atraso; se recuperó **sin pérdida** reproduciendo
`binlog.000050` filtrado a `--database=megaisp` hasta el `Anonymous_GTID` anterior al `DROP`
(`--stop-position`). Funcionó porque el binlog arrancaba con `megaisp` en 0 tablas — el mismo estado
que dejó el incidente— así que contenía la vida entera de la base. `binlog_expire_logs_seconds` =
30 días: esa es la ventana real de recuperación de dev, y es mucho mejor que la del último dump.
`mysqlbinlog` necesita root o `REPLICATION_APPLIER`; el usuario de la app no lo tiene.

---

## 11. TERMINAL WEB DEL PANEL (ttyd + tmux) — por qué se perdían las sesiones

- La terminal del panel DevTools es `ttyd -p 7681 -W --interface 0.0.0.0 bash`
  (`/etc/systemd/system/ttyd.service`, usuario `meganet`), servida same-origin por el
  `location /ttyd/` de nginx (proxy de WebSocket, `proxy_read_timeout 86400s`).
- **Modo de fallo (resuelto 2026-08-26):** si el WebSocket se cae, ttyd manda SIGHUP al bash
  hijo y se lleva la sesión de Claude Code con él. **No es ningún timeout** (ttyd pinguea cada
  5 s por default; nginx no se recarga desde el 22-jun) y **casi nunca lo provoca el usuario**:
  el cliente de ttyd RECONECTA SOLO en 1-7 s y lanza un bash NUEVO, así que la terminal parece
  "recargarse sola" con prompt limpio. El corte viene del camino navegador↔nginx (red o
  suspensión del equipo, pestaña congelada en segundo plano); desde el servidor no se evita.
- **Fix:** bloque al final de `~/.bashrc` (fuera de git — es del usuario) que envuelve en tmux
  el bash que lanza ttyd: reengancha la primera sesión `web-*` sin cliente y, si no hay, crea
  `web-N` (dos pestañas no se espejean). Guarda: sólo si el padre es literalmente `ttyd`, lo
  que deja fuera los shells que abre Claude Code y los del cron. Sin `exec`: si tmux fallara,
  cae al bash normal. Config en `~/.tmux.conf`. Lleva un reintento de ~2 s para la carrera
  entre la reconexión y el reap del cliente muerto por tmux.
- ⚠️ **Editar `.bashrc` NO reenvuelve un shell ya corriendo**: toda terminal abierta antes del
  arreglo sigue desprotegida y muere igual en la siguiente desconexión.
- **Diagnóstico sin sudo — `journalctl -u ttyd` distingue los dos casos:** recarga de página =
  `HTTP /` + `/token` + `WS /ws`; caída del WS con reconexión automática = `WS closed` …
  `/token` + `WS /ws` **sin** `HTTP /`. Verificar protección: `echo $TMUX`, o que la cadena del
  proceso `claude` suba a `tmux: server` y no a `ttyd`.
- `/var/log/nginx/*` es `www-data:adm 640` → **`meganet` NO puede leerlo** (no gastar intentos).
  `journalctl -u ttyd` y `-u nginx` sí se leen sin sudo.
- `.bashrc` arranca toda terminal web en `/var/www/megaisp`; para reanudar conversaciones viejas
  del proyecto `-` (cwd `/`) hace falta `cd / && claude --resume <id>`.
- ✅ **`ANTHROPIC_API_KEY` retirada del `.bashrc`** (2026-08-26, decisión de Irving). El archivo es
  `644` —lo lee cualquier usuario del box, `www-data` incluido— y la variable la heredaba todo
  proceso hijo. Nada dependía de ella: el CLI autentica por **OAuth**
  (`~/.claude/.credentials.json`), `deploy/circuito/vuelta.sh` la hace `unset` a propósito, y la
  app usa las suyas del `.env`/Integration Hub (huellas distintas — no era la misma llave). Los
  respaldos `~/.bashrc.bak-*` quedaron redactados y en `600`. **Nunca estuvo en git.**
  ⚠️ Sigue viva en el entorno de los procesos ya corriendo hasta que reinicien, y quedó impresa en
  transcripts de sesiones (`600`): **rotarla en la consola de Anthropic** es lo único que la anula.

---

## PROTOCOLO DE ACTUALIZACIÓN (para no re-investigar nunca lo mismo)

**Al CERRAR cada sesión, CC debe actualizar este archivo** con lo que cambió:

1. Si descubriste un mapa nuevo (dónde vive un motor, qué hace un método) → agrégalo
   a la sección correspondiente. Ese conocimiento ya no se vuelve a investigar.
2. Si arreglaste un bug → registra causa raíz + fix + estado (dev/prod) en §3 o §4.
3. Si cambió un entorno, flag, ruta o convención → corrige §0/§1.
4. Si surgió un bloqueo de prod → §5.
5. Commit selectivo de este archivo con mensaje en español: `docs(contexto): <qué>`.

**Ubicación en el repo:** guardar en la raíz como `CONTEXTO-MEGAISP.md` (versionado en
git, junto al `CLAUDE.md`/SKILL.md de convenciones). Al arrancar sesión, CC lee AMBOS:
`CLAUDE.md` (reglas) + este `CONTEXTO-MEGAISP.md` (mapa).

**Qué NO poner aquí:** datos vivos que cambian (conteos de filas, estados momentáneos).
Eso se consulta en el momento. Aquí va solo lo estructural y estable.
