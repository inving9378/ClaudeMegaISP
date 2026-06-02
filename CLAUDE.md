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

## MÓDULO FLOTAS (item #60) — `app/Modules/Addons/Flotas/`

Gestión de flota vehicular. **Uso interno Meganet + producto SaaS vendible a clientes ISP.**
Módulo modular estándar (`module.json` id=200, slug `addon-flotas`, activo).

### Estado — Fase 1 ✅ 100% TERMINADA (backend + UI)
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

**Estado:** ✅ BD + abstracción + MockDriver + UI listos. Falta **2.3** (TCP listener real + drivers Ruptela/Concox reales + eventos en tiempo real con WebSocket).

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
