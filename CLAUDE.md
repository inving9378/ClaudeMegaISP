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
