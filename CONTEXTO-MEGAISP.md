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
- Caché: tras cambios en Blade/config → `view:clear && config:clear && route:clear`,
  y SIEMPRE cerrar con warm-up `view:cache && config:cache`.
- **Paso 0 read-only** antes de escribir. Diff → OK de Irving por sub-paso.
- Toda deuda/bug/decisión diferida → registrar en Hoja de Ruta INMEDIATAMENTE.
- Fechas legacy (`payment_date`, `document_date`) son VARCHAR `DD/MM/YYYY` →
  usar `COALESCE(STR_TO_DATE(col,'%d/%m/%Y'), ...)`, nunca comparar como string.
- Frontend: Vue 3 + Quasar UMD sobre Blade (excepción: Flotas usa Bootstrap 5).
  SPA vía `spa-nav.js` (fetch-then-swap); respetar `data-spa-skip` y la blacklist.
- No compilar APKs ni builds pesados en el servidor (disco cerca de capacidad).

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
