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
2. **Schedule hard-coded** — jobs críticos: `invoice:create-proformas` (03:00), `mikrotik:sync` (5m), `smartolt:sync-critical` (10m), `activitylog:archive` (02:00 diario)

Comandos en: `app/Console/Commands/Active/`, `Commands/Olts/`, `Commands/Scripts/` (los de Scripts son one-off, algunos destructivos — revisar antes de correr).

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
