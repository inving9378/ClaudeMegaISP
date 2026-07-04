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
- Regla: **una sola tarea en progreso a la vez.** Ítems nuevos entran como `pending`;
  NO poner en `in_progress` sin cerrar el actual.
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
