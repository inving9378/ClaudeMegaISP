# CLAUDE.md

Guía de contexto para Claude Code en este repositorio. Leer antes de explorar.

---

## ⭐ REGLA PERMANENTE — Bitácora de sesiones (los cortes de internet borran reportes)

Al **cerrar cualquier tarea grande**, además del reporte en pantalla, **escribir el reporte en
un archivo PROPIO**:

```
docs/bitacora/<YYYY-MM-DD>-item-<id>.md
```

Un archivo por item. Dentro, el mismo formato de siempre: `## YYYY-MM-DD HH:MM — <título>`,
y **APPEND** si se vuelve a trabajar el mismo item el mismo día. Esto sobrevive a los cortes
de conexión, que es para lo que existe la regla.

**⚠️ CAMBIO 2026-09-10 — ya NO se escribe en `docs/bitacora-sesiones.md`.** Ese archivo era
uno solo, común a todas las ramas: con varias terminales trabajando en paralelo, *cada* rama
lo tocaba y *cada* merge conflictuaba ahí. Un archivo nuevo por item **no conflictúa nunca**,
porque ninguna otra rama lo tiene.

De paso queda mejor organizado: buscar qué pasó con un item es abrir su archivo, no recorrer
un log común de 4,200 líneas y 126 entradas.

**El histórico se conserva.** `docs/bitacora-sesiones.md` queda como está y no se migra ni se
borra: lo escrito hasta el 2026-09-10 sigue ahí y se sigue consultando. Solo se deja de
escribir en él.

Si el trabajo no tiene item del roadmap, usar un slug descriptivo en su lugar:
`docs/bitacora/2026-09-10-auditoria-asterisk.md`.

---

## ⭐ REGLA PERMANENTE — Ninguna escritura manual en producción sin bitácora PREVIA

Toda escritura manual en producción (backfill, import, `UPDATE`/`DELETE` masivo, cualquier
comando que modifique datos fuera del flujo normal de la app) **exige una entrada en
`docs/bitacora/` ANTES de ejecutarse** — no después: qué comando, contra qué base de datos, qué
respaldo se tomó antes, y quién lo corre.

**Por qué:** incidente real del 2026-10-11 — un `mapared:backfill` se corrió contra
`meganet_prod_claude` (producción) creando ~23,500 filas reales (`mapared_cables`/`hilos`/
`puertos`), con buen criterio técnico (dos respaldos tomados antes de escribir), pero sin
ninguna bitácora, commit ni registro de quién lo hizo. El dato en sí resultó de bajo riesgo (no
tocó dinero ni autenticación, y `mapared_layers` —la fuente— quedó intacta), pero durante horas
no hubo forma de distinguir "trabajo legítimo sin documentar" de "intrusión" — tuvo que
reconstruirse todo a posteriori por evidencia de archivos y timestamps. **A Irving nunca se le
había preguntado directamente** (un compañero de equipo había supuesto que fue él, por
eliminación, sin confirmación de primera mano) — ya se le preguntó y se reporta que lo dio por
suyo, aunque sigue sin existir un mensaje/commit directo de él que lo pruebe.

En el camino de la investigación (auth.log/nginx/binlog con acceso root) se encontraron además
**dos huecos reales de seguridad sin relación con el backfill**, ya corregidos: un vhost legacy
(`megaisp-8090`, apuntando a OTRA base de producción, `meganet_prod`) quedó expuesto a internet
~21 h, y MySQL escuchaba en todas las interfaces con un usuario que aceptaba conexión desde
cualquier host. Ninguno de los dos tuvo explotación real (confirmado con los logs completos), pero
ambos se cerraron el mismo día. Detalle completo en
`docs/bitacora/2026-10-11-mapared-backfill-prod.md`.

---

## 🔁 CIRCUITO DE MEJORA CONTINUA

Ciclo de mejora entre **Claude Code (local, esta red)** y **Claude Cowork (nube de Anthropic, externo)**:
1. Claude Code **audita** el sistema y **llena la Hoja de Ruta** (`roadmap_items`) con hallazgos +
   `prompt_para_claude` (plan por fases).
2. Claude Cowork **revisa** vía `GET/POST /api/roadmap-externo/{token}` (sin login, token en `.env`):
   ajusta `nivel_riesgo` y pone `estado_aprobacion`.
3. Claude Code **ejecuta solo lo aprobado**.

**Niveles de riesgo:** `A` seguro (aditivo/reversible, NO toca dinero/permisos/auth/producción) ·
`B` requiere confirmación de Irving · `C` decisión de diseño exclusiva de Irving.
Ante la duda, elegir el nivel **más restrictivo**.

**Reglas de ejecución:**
- **Modo automático SOLO** si `estado_aprobacion = aprobado_claude` **Y** `nivel_riesgo = A`.
- **Nivel B**: solo en sesión con Irving confirmando. **Nivel C**: jamás sin decisión de Irving.
- ⚠️ **ACTUALIZACIÓN 2026-08-04 (#507) — el AUTOPILOT modifica las dos reglas de arriba.** Decisión
  explícita de Irving (máxima autonomía): el autopilot puede decidir **hasta nivel C** por su cuenta,
  pero **solo** si el brief del Revisor trae, para la opción recomendada, `confianza: "alta"` **y**
  `reversible: true` **explícitos**, y ninguna pregunta marcada `requiere_irving`. Todo lo demás
  —irreversible, sin el dato, o rotulado `[BLOCKED-]`/`[PARKED-]`— **sigue yendo a su bandeja**.
  Configurable sin redeploy en `config/circuito.php` → `autopilot.*` (`max_nivel` = `C`);
  bajarlo a `B` o `A`, o `autopilot.enabled = false`, restaura el comportamiento anterior.
  El **kill switch** (`circuito_pausado`, botón de la Torre) sigue frenando todo. Ver `CONTEXTO-MEGAISP.md` §8.
- Al trabajar un item: `estado_aprobacion = en_progreso` al arrancar → `completado` al cerrar,
  **con el hash del commit referenciado en el item** (campo `log` o `comentarios_claude`).

**Infra:** módulo `Roadmap` (`app/Modules/Addons/Roadmap/`), tabla `roadmap_items` (campos del circuito
en migración `2026_07_08_210000`). Manual de criterios servido en el GET = `docs/manual-criterios-circuito.md`
+ este CLAUDE.md anexado vivo. Tokens en `.env` (`ROADMAP_EXTERNAL_READ_TOKEN`/`WRITE_TOKEN`), auditoría en
`storage/logs/roadmap-externo-*.log`.

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
  - Blade con `@can`/`@hasanyrole` — **NO es Vue ni API**
  - **Híbrido desde el item #44 → #68:** hay bloques hardcodeados (los módulos
    viejos, listados en `$sidebarHardcoded` dentro del blade) **y** un bloque
    **dinámico** (`sidebar.blade.php:195-254`) que renderiza bajo el título
    "Módulos" todo addon activo que declare `menu[]` en su `module.json`.
    `SidebarComposer` lo inyecta como `$addonMenuItems = ModuleRegistry::getMenu()`,
    filtrado por permiso, con sus `children`.
  - **Para agregar un addon NUEVO: declarar `menu[]` en `module.json` y ya.**
    NO se toca el Blade. (Editarlo a mano solo aplica a los módulos que siguen
    en `$sidebarHardcoded`; migrar uno = borrar su bloque y sacarlo de esa lista.)
  - Tarjeta en `/admin/administracion`: declarar `admin_cards[]` en `module.json`
    (también declarativo, lo consume `AdminPanelController::cards()`).

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

### JARVIS, el asistente del sistema — robotsito (`resources/js/components/ia/IaChatFloat.vue`)
- Backend: `app/Http/Controllers/IA/IAChatController.php` → toda la lógica en `app/Services/Asistente/`.
- **Conocimiento en archivos** (`resources/asistente/`): `system-prompt.md`, `guia/*.md`, `enlaces.json`,
  `limites.json` (por herramienta: permitida / confirmar / bloqueada), `memoria.json` (autocompactado).
- **Herramientas** en `app/Services/Asistente/Herramientas/`, cada una con el permiso de su pantalla;
  se registran en `RegistroHerramientas::CLASES`. Los cambios solo se aplican con el "sí" del usuario
  (validado por el servidor) y quedan en `activity_log` con `log_name='jarvis'`.
- **Altas** (`crear_cliente`, `crear_prospecto`) van por el MISMO FormRequest + `store()` de la
  pantalla (`Herramientas\Formulario`), no copian reglas. Lo que JARVIS no pudo hacer queda anotado
  como `activity_log` `log_name='jarvis'` + `description LIKE 'Pendiente:%'`: es la lista de qué
  capacidad agregarle después.
- **Entrenamiento:** `php artisan asistente:aprender [módulo...]` escribe `resources/asistente/guia/
  modulos/<módulo>.md` (+ índice `_temas.md`) con la IA del sistema (clave `asistente.entrenamiento`)
  desde `docs/modulos/*.md`, el menú real, lo que pide cada alta (calculado de las reglas, no
  adivinado) y el manual; solo regenera lo que cambió. **Al cambiar la documentación de un módulo,
  correrlo y commitear las guías.** Para corregir una guía: su doc o `resources/asistente/
  entrenamiento.json` (notas / guías del equipo), nunca editar el `.md` generado.
- **Pruebas:** `php artisan asistente:evaluar [id...] [--tema=]` corre `resources/asistente/pruebas.json`
  con la IA real dentro de una transacción que se deshace. Correrlo antes de mergear cambios a sus
  instrucciones, guías o herramientas.
- **Memoria** en BD: `asistente_conversaciones/mensajes/recuerdos/acciones`. IA: Integraciones →
  Módulos IA, claves `asistente.chat_flotante` (requiere herramientas) y `asistente.memoria`.
- Acceso: permiso `usar-ia-chat`.

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
- Flotas: panel **"Mi Flota" de solo lectura** ya scopeado por `fleet_vehicles.client_id` (item roadmap #152,
  commits `cd25a346`+`de850de2`+`0b557790`, ver detalle en `docs/portal-flotas-item-152-verificacion.md`).
  `client_id` nullable = flota interna Meganet (excluida por diseño vía `BelongsToClientTenant`, `allowNullTenant`
  desactivado para el cliente). Sigue **sin alta self-service** desde el marketplace (`MarketplaceController`
  gatea "MegaFlotas" como "en preparación" — activar requiere que un admin dé de alta vehículos/suscripción
  para ese cliente; abrir venta self-service es decisión de producto pendiente, fuera de alcance de #152).
- VoIP: GATEADO (en preparación)

**Cliente de prueba:**
- `clients.id` = 6810 (nombre y teléfono fuera del repo: es público)
- Contraseña de portal para validar en navegador: pedirla en sesión; nunca se escribe aquí (el repo es público)
  ⚠️ Rotar esta contraseña luego de validar — es temporal de prueba

---

## HOJA DE RUTA — Items pendientes

### Rectificación de permisos/roles — Fase 3a (2026-07-07)

**Flip aplicado en dev:** `CheckRoutePermission` ahora honra permisos de **ROL** (directos ∪ rol), no solo directos. Dos commits: `587d467f` (revoca `scheduling_view_calendar` del rol client — último permiso que abría ruta) + `708bcba0` (flip: `PermissionTrait::getPermissionForUserAuthenticated` cambia `$user->permissions` → `$user->getAllPermissions()`, conservando `->pluck('id','name')`). Bypass por nombre de rol (`CheckRoutePermission` línea 59 + short-circuit `isAdmin()` del trait) **intacto**. El flip afecta 3 consumidores del trait (middleware + `allViewHasPermission` + `hasPermissionToView`), todos aditivos. Propiedad: solo SUMA acceso (directos ⊆ directos∪rol) → ningún staff pierde acceso. Prueba de oro verificada en dev (usuario `prueba_flip` rol Vendedor, 0 directos → alcanza `/crm/listar` por rol). **Nota:** en dev el flip es hoy un no-op de *acceso* (todo staff ya carga sus permisos-de-ruta como directos = residuo Fase 3b); su valor es estructural + futuro.

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **Replicar flip en PROD** | Correr allá las 2 migraciones/commit de Fase 3a **DESPUÉS** de que Fase 2 + 2.5 estén replicadas en prod (honrar roles ANTES de limpiar los espejos contaminados sería peligroso: abriría rutas por rol a cuentas aún sucias). | ⚠️ Pendiente | **Alta** |
| **Fase 3a-bis — puerta de autorización propia del módulo OLT (RESUELTO — premisa incorrecta)** | Investigado (item #414): la premisa de que `olt_view/onu_add/onu_edit` tienen un verificador propio directos-only es **falsa**. Las rutas OLT pasan por el mismo middleware `check_route_permission` que el resto del sistema (ver `config/route_permission.php:450-500`, ~40 paths bajo `olt_view`/`onu_add`/`onu_edit`), consumen `PermissionTrait::getPermissionForUserAuthenticated()` — **el mismo trait ya flippeado en Fase 3a** (`getAllPermissions()`, commit `708bcba0`) — y por lo tanto ya heredaron el flip sin cambio adicional. La defensa en profundidad inline en `OLTsOnuController` (`auth()->user()->can('onu_add'\|'onu_edit')`, agregada en item #287) usa `Gate::before`→`hasPermissionTo()` de Spatie, que **siempre** evalúa `hasDirectPermission() \|\| hasPermissionViaRole()` — nunca fue directos-only, no requería flip. Verificado empíricamente en tinker (usuario con permiso SOLO por rol → `can()` = true). Detalle en `docs/permisos-olt-item-414-verificacion.md`. Sin cambio de código (nada que arreglar). | ✅ Resuelto | — |
| **Fase 3b — retirar copia rol→directo del `store` + huérfanos** | Quitar la copia de permisos rol→directo del alta de usuarios (`UserController::store` ~163-166) + migrar los 872 huérfanos de los 23 staff. Tras esto el flip deja de ser no-op (los directos redundantes desaparecen y el acceso pasa a resolverse por rol). | ⚠️ Pendiente | Media |

### Rectificación de permisos/roles — pendientes de Fase 2.5 (2026-07-06)

Fase 2.5 aplicada en dev (commits `179b288a` Grupo A + `af85a725` Grupo B): saneadas **395 cuentas espejo** contaminadas → 0 roles de staff y 0 permisos directos (incluidas 71 filas huérfanas). Detección final: **0 espejos contaminados**. Migraciones self-contained portables por selector (rol client + login∈CMI + is_seller=0 + patrón `^Meganet[0-9a-f]{8}$`), con rail por cuenta + pre-flight abort >450.

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **CRÍTICO — Replicar saneo de espejos en PROD** | Correr en prod las migraciones `2026_07_06_130000_sanea_espejos_grupo_a_*` y `2026_07_06_130100_barre_directos_espejos_grupo_b` (descubren objetivos por selector, portables). En prod la triple-admin y las mega-admin (super-administrator con cientos de directos) **podrían estar vivas** = exposición real bajo el login gate actual. Verificar antes con el query de detección de Fase 2.5 PASO 0. | ⚠️ Pendiente | **Alta** |
| **De dónde toman directos los espejos al crearse (Fase 3b)** | Neutralizar la fuente que copia permisos rol→directo al crear/editar espejos (copia del `store` línea 163-166 y/o import Splynx) para que nuevos espejos no reciban directos. Hoy inofensivo (el rol client limpio solo copiaría 8 redundantes), pero cerrar la fuente. | ⚠️ Pendiente | Media |
| **Filas huérfanas globales en model_has_permissions/role_has_permissions** | Los espejos tenían filas con `permission_id` de permisos borrados (limpiadas para los 395). Puede haber más huérfanas en otras cuentas/roles (cruft de módulos desinstalados). Barrido global opcional de higiene. | 📝 Nota | Baja |

### Rectificación de permisos/roles — pendientes de Fase 2 (2026-07-06)

Fase 2 aplicada en dev (commit `38c33feb`): rol `client` limpiado de 30→8 permisos (revocados 22 = CRM+seller+task; conservados calendar_filter_*/package/scheduling). Paso 1 (exclusión en reparto) ya estaba en `b9effb5f`. Pendientes (NO ejecutar):

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **Replicar revocación del rol client en PROD** | Correr la migración `2026_07_06_120000_revoke_internal_permissions_from_client_role` en prod (idempotente/portable). Antes, verificar con el query de Fase 2 PASO 0 la lista real de permisos del rol client en prod (por si difiere). | ⚠️ Pendiente | Alta |
| **Sanear 10 cuentas contaminadas (client + rol staff)** | En DEV quedan **10** cuentas espejo con rol client Y rol de staff (NO 0 — el incidente no se limpió). Crítica: `Meganet8f3d7255` = super-administrator+DESARROLLADOR+ADMINISTRADOR_COMPLETO. Query de detección en Fase 2 PASO 0 punto 3 (documentado para prod). Saneo = revocar el rol staff a esos *usuarios* (escritura sobre cuentas reales) → merece su propio gate; la lista de Administradores las OCULTA (filtro whereDoesntHave client) → hacerlo por tinker. NO es parte de la limpieza del rol. | ⚠️ Pendiente | **Alta** |
| **Borrar roles muertos (Fase 4)** | `Socio`, `PUBLICADOR`, `conductor` (0 usuarios) ya excluidos del reparto; borrarlos en Fase 4. | ⚠️ Pendiente | Media |

### Rectificación de permisos/roles — pendientes de Fase 1 (2026-07-06)

Guards de seguridad de `UserController` YA aplicados en dev (Fase 1, commits `463b99ed` guard cuenta-cliente, `2c2381fd` update aditivo sin `syncRoles`, `d5e4c353` UI override). Quedan registrados (NO ejecutar aún):

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **Verificar `conciliacion.manage` en PROD** | En dev el rol **Mostrador** SÍ porta `conciliacion.manage` (ruta gateada por `permission:` de Spatie, que honra roles) → Diana entra por rol, NO quedó inerte. Falta confirmar que la migración `grant_conciliacion_manage_to_mostrador` haya corrido en prod (allá pudo no ejecutarse). | ⚠️ Verificar | Media |
| **Limpiar ~14 asignaciones huérfanas del rol `client`** | `model_has_roles` del rol client referencia **1139** filas pero solo **1125** tienen `users` correspondiente → ~14 asignaciones apuntan a `model_id` sin fila en `users`. Depurar (read-only primero, validar en prod). | ⚠️ Pendiente | Baja |
| **Consolidación de los 17 roles (Fase 4)** | 3 roles muertos (0 usuarios): `Socio`, `PUBLICADOR`, `conductor`. Duplicado super-admin ES/EN (`super-administrator` 674 perms/6 users vs `Super Administrador` 445 perms/1 user). Varios roles de un solo usuario. Consolidar/limpiar en Fase 4. | ⚠️ Pendiente | Media |

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

### ✅ CHECKLIST PRE-DEPLOY A PROD (verificar ANTES de cada release)

> **V1.20 se CANCELÓ el 2026-07-03** (prod intacta: sin push a origin, sin tag, remote:deploy nunca disparado). Preparar bien el release antes de subir.

1. **Backfill `portal.colaborador` portable** — la migración `Talento/…/create_portal_colaborador_permission.php` ya resuelve por `TalentoColaborador` activo (sin ids). El `4818` hardcodeado se movió a resolución por `login_user` en `SyncColaboradoresCommand` (commit `5efe0887`). ⚠️ **DECISIÓN ABIERTA:** el backfill da el permiso a **todo colaborador activo sin filtrar rol** (en dev incluye a `4818` rol `client` puro). **Verificar en prod que `talento_colaboradores` activos sean SOLO staff real** antes de subir, o decidir guard por allowlist de roles staff (`whereHas`, nunca `whereDoesntHave('client')`; ojo colaboradores nuevos sin rol). Staff-cliente legítimo (p.ej. técnico suscriptor) SÍ debe recibirlo.
2. **`TALENTO_PAYWEEK_CUTOVER` (es PLATA)** — setear explícito en `.env` de PROD: `TALENTO_PAYWEEK_CUTOVER="2026-07-11 18:00:00"` (default seguro ya en `config/talento.php:27` si falta). PROD: 0 pagos reales → primera semana 07-11→07-18, sin histórico.
3. **Migraciones aditivas** — confirmar que todas sean `create table`/`add column` (con guard `hasColumn`)/`firstOrCreate`; ninguna destructiva en `up()` (drops solo en `down()`). El `migrate_dryrun` aborta el deploy si truena (necesita `GRANT ALL ON megaisp_dryrun.*`). **NUNCA `migrate:fresh`.**
4. **ids dev≠prod** — JAMÁS resolver una cuenta por id entre entornos; siempre por `login_user`/email/rol (Irving en prod = `id=13` login `Meganet`, NO id=8).
5. **Validar en DEV antes de subir** — captura visual de Irving: pestañas "Mi material" (ASSET_VER 14) + Fase A. **Pendiente.**
6. **`NO Payments/`** — V1.20 arrastraba todo el módulo Conciliación WhatsApp F1–F6; si no va a prod aún, separar ramas antes del deploy (freno maestro `payments.auto_apply_enabled` = OFF por default).
7. **`DOMICILIACION_COBRO_LIVE_ENABLED` (es PLATA)** — kill-switch de cobros REALES de `domiciliacion:cobrar`, independiente de `OPENPAY_SANDBOX` (`config/domiciliacion.php`). Default seguro `false` si falta el env. **NO poner `true` en PROD sin decisión explícita de Irving** — sube en `false` y actívalo aparte cuando él lo confirme. (Antes el gate de producción dependía de `OPENPAY_SANDBOX=false`, que es justo lo que pide este mismo checklist para OpenPay → bloqueaba el cobro real en silencio; item #225.)
10. **`FLOTAS_BILLING_ENABLED` (es PLATA)** — kill-switch del cobro de Flotas dentro de la factura (`config/flotas_billing.php`, `ClientRepository::calculateAmounts`). Default `false`; **solo Irving lo prende**. Modelo (item #622, Decisión 7): cada proforma del período P lleva (1) cargo por adelantado del bloque de las unidades al generarla y (2) ajuste del último mes CERRADO (el anterior al mes en que se genera; con la proforma del día 25 es P-2) = uso real por días − lo adelantado, solo si ese mes tuvo adelanto y una sola vez (eventos `billed`: adelanto `{periodo, unidades, monto_adelantado, invoice_id}` y ajuste `{ajuste_de, monto_ajuste, invoice_id}`). La promoción entra desde el día siguiente a su fin; las proformas retroactivas (`payment_method=retroactive`) no llevan Flotas. Bloques/precios desde `FleetPlans`. No toca facturas ya emitidas.
11. **`BILLING_NO_CARGAR_MES_BLOQUEADO` (es PLATA)** — interruptor (`config/billing.php` → `no_cargar_mes_bloqueado`, item #1090, de #573 q4) que deja de cargar el mes de servicio a un cliente recurrente **Bloqueado** (rama de cobro sin saldo de `ClientBillingService::billingServicesByClient`; el mes en que se bloquea sí se cobró). Default `false` si falta el env. **Solo Irving lo prende**; sube apagado. No toca facturas emitidas ni saldos existentes.
8. **`APP_DEBUG=false` + `DEBUGBAR_ENABLED=false` en PROD (fuga de info)** — en dev (.11) `APP_DEBUG=true` y la Debugbar están activos a propósito; en producción (.198) deben ir en `false` para no exponer stack traces/queries/env por HTTP. Acción manual de Irving en el `.env` de .198 (nunca tocar el `.env` de dev): setear ambos flags y luego `php artisan config:clear && php artisan route:clear && php artisan queue:restart` (**NUNCA `config:cache`** con debug activo por error). Item roadmap #156 — el circuito no lo ejecuta (frontera dura: no toca `.env` ni servicios de producción).
9. **`PAGOS_RECURRENTES_CRON_ENABLED` (es PLATA)** — kill-switch del schedule diario de `pagos:enviar-recurrentes` (recurrencia asistida del Portal de Pago; `config/pagos.php` → `recurrentes_cron_enabled`, gatea `app/Console/Kernel.php`), mismo patrón que `DOMICILIACION_COBRO_LIVE_ENABLED` de arriba. Default seguro `false` si falta el env (el cron ni se registra en `schedule:list`). El comando en sí NO cobra (solo genera ligas y las loguea, sin auto-débito), pero **decisión de Irving (item roadmap #163, q1): primero validar el `--dry-run` en dev unos días** (log dedicado `storage/logs/pagos-recurrentes.log`, canal `pagos_recurrentes`) **antes** de poner `true` en PROD. Resumen diario opcional por correo/WhatsApp vía `PAGOS_RECURRENTES_RESUMEN_EMAIL`/`PAGOS_RECURRENTES_RESUMEN_WHATSAPP` (vacíos por default = sin destinatario, el resumen queda solo en el log).
8. **`APP_DEBUG=false` + `DEBUGBAR_ENABLED=false` en PROD (fuga de info)** — en dev (.11) `APP_DEBUG=true` y la Debugbar están activos a propósito; en producción (.198) deben ir en `false` para no exponer stack traces/queries/env por HTTP. Acción manual de Irving en el `.env` de .198 (nunca tocar el `.env` de dev): setear ambos flags y luego `php artisan config:clear && php artisan route:clear && php artisan queue:restart` (**NUNCA `config:cache`** con debug activo por error). Item roadmap #156 — el circuito no lo ejecuta (frontera dura: no toca `.env` ni servicios de producción). Runbook paso a paso (con verificación y rollback) en `deploy/README-prod-debug-off-item-156.md`.

**Commits retenidos:** `deploy/release-retenidos.txt` lista los commits que no pueden salir a producción hasta validarlos. El pipeline de emisión y `releases:publish-github` se detienen si la versión trae alguno, también por cherry-pick; `--force` no lo salta. Se libera borrando su línea y commiteando.

### Infraestructura de producción — verificación pendiente

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **Cron schedule:run en PROD** | Verificar que el servidor de producción tenga `* * * * * cd /var/www/megaisp && php artisan schedule:run` activo como `www-data`. Si está caído: billing (`invoice:create-proformas`), notificaciones de cobranza (`billing:send-pending-notifications`), CobranzaBlaster, domiciliación y backups reales no corren. | ⚠️ Verificar | Alta |
| **Modo mantenimiento en deploy de prod (EL fix del susto)** | `RemoteDeployCommand` **NO** hace `php artisan down`/`up` → durante el `migrate` (ALTER largo) el usuario ve **500 crudo**, no una página de mantenimiento (esto fue lo que dejó el sitio caído ~15 min en V1.19→V1.21). Diseñar modo mantenimiento que **excluya el endpoint de progreso del `DeployProgressModal`** (`DeploymentLog`/polling) para que el modal siga vivo y el admin vea el avance mientras el resto del sitio muestra "en mantenimiento". | ⚠️ Pendiente | **Alta** |
| **Aplicar `--timeout=3600` del worker Supervisor en PROD** | Los commits `dde9e8c5` (.conf) **NO** reconfiguran el worker vivo. Acción de provisioning en prod: `sudo cp deploy/megaisp-deploy-worker.conf /etc/supervisor/conf.d/` → `sudo supervisorctl reread && update && restart megaisp-deploy`. (El timeout de la migración —`config('deployment.migrate_timeout')=2400`— ya viaja en el código y aplica solo al desplegar.) | ⚠️ Pendiente | **Alta** |
| **Decidir Sub-paso B (auto-pausa de writes de Marketing en deploy)** | **CONGELADO** hasta tener `total_row_versions` de `marketing_messages` en prod (query en `information_schema.innodb_tables`). Bloqueado además por privilegios: el deploy corre como `www-data`, el socket de Supervisor es `0700 root:root` → no hay control de Supervisor sin sudo interactivo. Si se retoma, la única vía sin privilegios es un flag-en-cache que los jobs de Marketing (cola `default`) consulten para auto-pausarse durante el `migrate`. | ⏸️ Congelado | Alta |
| **Permisos /var/backups/mysql en PROD** | Ajustar a `750 www-data:www-data` (sin group-write) una vez el cron esté activo. Actualmente en dev: `770` para pruebas manuales. | ⚠️ Pendiente | Media |
| **Índice unique de `plan_bundles` (RESUELTO en dev — item #147)** | Re-habilitado el índice unique (`plan_bundles_bundle_plan_type_unique` sobre `bundle_id, plan_bundle_id, plan_bundle_type`) en la migración `2026_06_17_145346_add_unique_to_plan_bundles.php` — el `up()`/`down()` estaban comentados en el archivo pero el índice **ya existía aplicado** en la BD de dev compartida (creado por el commit original `148f3dec` antes de comentarse; `migrate:status` lo muestra `Ran` batch 525) y `0` duplicados verificados (`plan_bundles` 107 filas). El archivo solo estaba desincronizado de lo ya aplicado. **PROD sigue sin verificar** (duplicados no depurados ahí) — pero queda protegido por el `migrate_dryrun` del pipeline de deploy, que usa **exactamente este caso** (unique contra duplicados de `plan_bundles`) como ejemplo documentado de fallo dependiente-de-datos que aborta el deploy sin dañar prod; si prod tiene duplicados, el dry-run lo atajará antes del `migrate` real. | ✅ Resuelto (dev) | — |
| **Retención de respaldos por versión (RESUELTO — item #146)** | `storage/backup_test/{version}/{version}.zip` se acumulaba ~135 MB/versión sin límite. Comando `php artisan backups:purge-test` (commit `701fd574`, keep-last-N por mtime, dry-run por defecto, solo toca `{V}/{V}.zip` con match exacto) ya existía pero no corría solo. Agregado a `Kernel.php`: `backups:purge-test --force` diario **02:20** (keep=7 por defecto), análogo a la retención de 14 días de `backup_db:process`. | ✅ Resuelto | — |
| **Bug Reintentar deploy (RESUELTO)** | `DeploymentController::retry` despachaba `DeployJob::dispatch($newLog)` con un solo argumento → `Too few arguments, at least 2 expected` (`DeployJob` exige `version` obligatoria). Corregido para despachar con `(log, version, title)` tomando la version/title de la release del deploy original. | ✅ Resuelto | — |
| **Bug git_commit dependiente del idioma (RESUELTO)** | El paso `git_commit` detectaba "nada que commitear" parseando el texto inglés `nothing to commit` del output, pero el git del server responde en **español** → un árbol limpio (commits ya hechos, solo falta pushear) se trataba como **fallo** (exit 1) y cortaba el deploy. Corregido: (1) `LC_ALL=C/LANG=C` en `buildEnv()` para salida de git en inglés en todo el pipeline; (2) detección previa independiente del idioma con `git diff --cached --quiet` (stage vacío → commit `skipped` como éxito, no fallo). | ✅ Resuelto | — |
| **Bug tag lightweight no llegaba a origin (RESUELTO)** | `git_tag` creaba un tag **lightweight** (`git tag {version}`) y `git push origin main --follow-tags` solo empuja tags **anotados** → el tag nunca llegaba a GitHub (commits sí). Corregido a `git tag -a {version} -m "Release {version}"`. El tag `V1.2.1` (creado antes del fix) se subió a mano con `git push origin V1.2.1`. | ✅ Resuelto | — |

### Deuda técnica frontend — antipatrón jQuery en componentes Vue

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **Sidebar "Notificaciones pendientes" → 404 (preexistente)** | El link del menú **Finanzas** `/finanzas/notificaciones-pendientes` (`module-sidebar/finanzas.blade.php`, gateado por `facturacion.notif.gestionar`) **no tiene ruta registrada** → 404. Bug previo a la conciliación WhatsApp; detectado al integrar la cola de Tere (Fase 6). Revisar/crear la pantalla o quitar el link. | ⚠️ Pendiente | Baja |
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
| **Paso 3 (pieza de cableado, RESUELTO — item #272) — reapuntar la autoría de pagos automáticos a MEGAISP** | `PaymentApplicationService::resolveSystemUserId()` y `PaymentFromSessionService::apply()` ya resuelven `User::systemBot()?->id ?? 1` (dejaron de buscar el rol `SUPER_ADMIN` inexistente) → los pagos automáticos (webhook SPEI/OpenPay/conciliación) se atribuyen a **MEGAISP**, `id 1` ("Admin") solo como fallback defensivo si `systemBot()` no existiera. Commit `ed9d4e8d` en `main`. Verificado end-to-end en item #9990641 (cadena de seguimiento #272→#9990629→#9990641), detalle en `docs/payments-resolvesystemuserid-seguimiento-9990629-verificacion.md`. | ✅ Resuelto | — |
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
4. `npm_build` (crítico) — `npm ci && npm run prod` bajo `timeout` (techo `DEPLOY_NPM_TIMEOUT`, default **1800s**; antes 600 fijo → la V1.59 falló en prod el 2026-10-14) con `NODE_OPTIONS=--max-old-space-size` (`DEPLOY_NPM_MAX_OLD_SPACE_MB`, default 4096). Omite `npm ci` si `package*.json` no cambió y `node_modules/.package-lock.json` existe (si así falla, reintenta con `npm ci`). Al vencer mata al grupo npm+node: sin eso un webpack huérfano publicaba el JS nuevo tras el rollback.
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

### Regla de publicación de versiones (item #9990678, épica #9990668)

> "si se hizo una versión se debe publicar" — regla textual de Irving.

**Por qué existe:** varias versiones (V1.34, V1.33, V1.16–V1.19) quedaron creadas en dev sin
publicarse — el release existía en la BD/UI del circuito pero nunca llegó a producción, así que
nadie fuera de dev podía verlo ni consumirlo. La épica **#9990668** ("Emisión atómica de
versiones: una versión existe si y sólo si está publicada") existe para cerrar ese hueco: una
versión "a medias" (emitida pero no publicada) deja de ser un estado válido del sistema.

**Qué implica desde la F3 de esa épica** (`ReleasePublisherService`, item #9990674 — emisión
atómica): emitir una versión deja de ser reversible en dos tiempos (crear el release primero,
publicarlo después como paso separado) — el acto de emitir publica al mundo en el mismo acto,
con compensación si algo falla a medio camino. Por eso el botón "+ Nueva versión" y la ruta
`POST /releases/store` se gatean con un permiso Spatie **separado**, `releases.emitir` (items
#9990678/#9991141), distinto de `release_view_release` (ver el historial de releases, sin
emitir nada) — quien puede emitir puede publicar, así que debe ser una decisión consciente, no
un efecto colateral de "guardar un borrador". El estado de esas piezas (F3/F7a) se sigue en sus
propios items de la Hoja de Ruta, no en esta nota.

### Portal Cliente — estado al 2026-06-15

| Item | Descripción | Estado | Prioridad |
|------|-------------|--------|-----------|
| **OpenPay producción** | Completar certificación + cambiar llaves en `.env` + `OPENPAY_SANDBOX=false` | ⏳ Config | Alta |
| **Webhook OpenPay** | Activar URL en dashboard OpenPay al publicar subdominio/SSL | ⏳ Config | Alta |
| Portal: subdominio + SSL | Configurar `portal.meganet.mx` con nginx + certbot. **Al publicar HTTPS, aplicar de una vez en el mismo corte**: `SESSION_SECURE_COOKIE=true` en el `.env` de PROD (.198) + warm-up (`config:clear && route:clear && queue:restart`, NUNCA `config:cache`) + probar login. Dejar `false`/vacío mientras siga por HTTP (si no, la cookie no viaja y rompe login). Acción manual de Irving en el box de prod — el circuito de dev no toca .198 (item roadmap #157). Receta copiar/pegar lista en `deploy/README-session-secure-cookie-prod.md`. | ⏳ Pendiente | Alta |
| Portal: CFDI timbrado | Generar PDF/XML de facturas fiscales desde el portal | ⏳ Pendiente | Media |
| Portal: cobro/tarifas premium MegaFamilia | Planes de pago MegaFamilia vía OpenPay | ⏳ Pendiente | Media |
| Portal: notificación pago por email | Enviar recibo al email del cliente tras pago OpenPay | ⏳ Pendiente | Media |
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
| ✅ MegaFamilia — funciones cliente: perfiles, dispositivos, bloqueos, horarios, geocercas, tareas/recompensas, solicitudes, estadísticas (`/portal/megafamilia`) | 78ae1e4e (merge circuito #150, 2026-07-12) |
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
| ✅ Flotas (item #152) — panel "Mi Flota" solo lectura, scope `fleet_vehicles.client_id` + tracking (última posición/estado en vivo) | cd25a346+de850de2+0b557790 |

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

### ⚠️ Guardrail de migraciones en dev (item #534, raíz de las migraciones fantasma)
`php artisan migrate` (cualquier flujo: manual, worker del circuito, `migrate:fresh`/`refresh`) exige en
dev que cada migración **pendiente** esté **commiteada** y en una rama con **ruta registrada a main**
(main mismo, o una rama `circuito/item-N-...` ligada a un item vivo del roadmap). Si no, aborta con
mensaje claro — así una migración escrita en un worktree ya no puede aplicarse contra la BD compartida
sin quedar rastreable a main, que era el mecanismo del incidente original (fantasmas: `add_reporte_dual`,
`add_decision_metadata`). Escape hatch para casos legítimos (rollback de emergencia/debug):
`php artisan migrate --force-uncommitted` (queda auditado en `storage/logs/migration-guard.log`).
Solo enforcea con `APP_ENV=local` fuera de tests (`MigrationGuardService::shouldEnforce`); no toca
`migrate:fresh --seed` de los tests ni el deploy de prod. Implementación:
`app/Services/MigrationGuardService.php` + `app/Console/Commands/GuardedMigrateCommand.php`, inyectado
sobre el `MigrateCommand` nativo en `AppServiceProvider::boot()`.

### ⚠️ `php artisan config:cache` — SÓLO detrás de `config:auditar-env` (item #790)
Esto **NO es folclore, y ya no es una regla de memoria**: `config:cache` rompe el sistema porque, al
cachear, Laravel **se salta `LoadEnvironmentVariables` al bootear** (`if ($app->configurationIsCached())
return;`) → el `.env` no se lee y **toda** llamada a `env()` fuera de `config/*.php` cae a su default
(o `null`) desde ese momento.

La auditoría de 2026-08-18 encontró credenciales vivas justo ahí — `CLAUDE_MODEL`/`CLAUDE_API_KEY` en
12 archivos que llamaban a la IA **directo**, sin pasar por el adaptador único de `app/Services/Core/`
(ver "SERVICIOS COMPARTIDOS ÚNICOS" abajo), `WHATSAPP_API_BASE`/`WHATSAPP_INSTANCE` del driver de
publicación de Marketing (**distinto** del `config/whatsapp.php` del gateway principal),
`AMI_SECRET`/`AMI_HOST` de CobranzaBlaster y `CONECTION_MIKROTIK` de MegaFamilia. **Esas ya están
migradas** (#792/#793). Lo que quede se pregunta, no se recuerda:

#### El warm-up de cierre (ya NO es una regla de memoria — la verifica un comando)

```bash
php artisan config:clear && php artisan route:clear && php artisan queue:restart
php artisan config:auditar-env && php artisan config:cache      # ← el && es el candado
```

`config:auditar-env` (item #790, `app/Console/Commands/Active/AuditarEnvRuntimeCommand.php`) lista
las llamadas **reales** a `env()` en runtime fuera de `config/` y devuelve **exit 1** si queda
alguna, así que **`config:cache` sólo corre cuando ya es seguro**. Encuentra las llamadas con el
tokenizador de PHP, no con grep: no cuenta comentarios, ni `getenv()`, ni el método privado
`$this->env()` de `MysqldumpEngine`, ni el literal de regex `'/\.env(\b|\.)/'`, ni el
`env(safe-area-inset-bottom)` que es **CSS** dentro de un blade — y sí mira dentro de `{{ }}`.

**Esto es lo que hace que la convención y el código dejen de contradecirse.** Antes la regla escrita
mandaba cachear y el código mandaba no cachear, justo en el punto del que depende que el circuito
pueda llamar a Claude; ahora el checklist se verifica solo y "hacer lo correcto" ya no puede tumbar
nada. **No escribas `config:cache` suelto**: siempre detrás del `&&`.

- `env()` **SÍ es correcto** dentro de `config/*.php` (ahí es el patrón estándar de Laravel) y en
  migraciones/seeders que corren a mano (no viven en el ciclo de request/worker cacheado). Por eso
  el auditor no escanea ni `config/` ni `database/`.
- **Migración por fases** (mover cada `env()` de runtime a una clave `config/` + refactorizar el
  llamador a `config('...')`): #792 **hecho** (12 archivos de IA: `CLAUDE_MODEL`/`CLAUDE_API_KEY` →
  `config('services.anthropic.*')`), #793 **hecho** (`WhatsAppStatusDriver` → `config/marketing.php`,
  AMI de CobranzaBlaster → `config/voip.php`, voz TTS → `config/cobranza.php`, `CONECTION_MIKROTIK`
  → `config/megafamilia.php`), **#794 pendiente** = lo que hoy siga listando el auditor. La lista no
  se re-audita a mano: se corre el comando.
- ⚠️ El caso que **no** es mecánico: `app/Services/Core/UsesApiIntegration.php` llama `env($envFallback)`
  con clave **dinámica**. Mover eso necesita un mapa proveedor→clave de config, no un reemplazo.
  Es el respaldo #2 de las API keys (el #1 es el Hub `api_integrations` en BD) — ver abajo.

#### Con `config:cache` puesto, ¿el circuito falla ruidoso o callado? — **callado** (medido 2026-08-18)

Y esa es la parte importante, porque es la misma forma de las aprobaciones mudas: el sistema no se
cae, se **degrada a algo que parece una decisión legítima**.

- **Hoy el circuito NO se queda sin llave** con `config:cache`: `ClaudeApiClient` resuelve la key por
  `UsesApiIntegration::resolveApiKey()`, cuyo orden es **Hub (`api_integrations`, BD) → `env()` →
  `marketing_settings`**. En dev el Hub tiene la fila `anthropic-default` activa y validada, así que
  responde antes de llegar al `env()`. El ejecutor on-box tampoco: `vuelta.sh` autentica el CLI
  `claude` por OAuth y de hecho **hace `unset CLAUDE_API_KEY`** a propósito. Y todo `config/circuito.php`
  usa `env()` **dentro de config/**, que es el patrón correcto y sobrevive al cache.
- **Pero si esa fila del Hub se desactiva o se rota**, la cadena se cae entera y **sin ruido**:
  `RevisorService::callModel()` atrapa el `Throwable` y devuelve `veredicto: escala, confianza: baja`
  (falla-segura por diseño) → **todo escala a `requiere_irving`** y el circuito se ve *prudente*, no
  roto. `proponerPreguntas()`/`proponerOpciones()` devuelven arrays vacíos → los briefs se quedan sin
  `confianza`/`reversible` → **el autopilot no califica nada**, que es indistinguible de "briefs
  viejos sin datos". `briefarC()` escribe "(No se pudo generar el brief automáticamente: …)" dentro
  de `comentarios_claude`. No hay banner, ni contador, ni alerta: la razón queda enterrada en el
  texto del item.
- **Conclusión operativa:** el `&&` de arriba quita el escenario del gatillo, pero la fragilidad de
  fondo —una credencial que falta se ve igual que un circuito prudente— sigue viva. Es la misma
  familia que las aprobaciones mudas y merece su propia señal en la Torre.

---

## INTEGRACIONES EXTERNAS

- **MikroTik RouterOS** via `pear2/net_routeros` → `MikrotikService` (toggle: `ROUTER_LOCAL`, `CONECTION_MIKROTIK`)
- **FreeRADIUS** — segunda conexión DB opcional (`DB_RADIUS_*`)
- **OLT / SmartOLT** → `app/Services/OLTsService.php`
- **Asterisk AMI** para VoIP (`AMI_*` env)
- **Google Maps + Leaflet** (`MIX_VUE_APP_GOOGLEMAPS_KEY`)
- **WhatsApp Evolution API** (`WHATSAPP_API_*` env)
- **Claude API** → `IAChatController` (`CLAUDE_API_KEY`, `CLAUDE_MODEL`)
- **Red en solo lectura** (`config/red.php`, `App\Support\Red\SoloLectura`): fuera de producción el candado se pone solo y MikroTik (`ClienteRouterOs`), SmartOLT (solo GET) y la OLT Huawei (`execWrite`) rechazan todo cambio con `RedSoloLecturaException`. `RED_ROUTERS_REALES_EN_DEV=true` deja a dev LEER los routers reales (sin él se desvía a `MIKROTIK_DEV_*`); `RED_SOLO_LECTURA=false` lo quita para probar escrituras en el laboratorio.

---

## CONVENCIONES

### 🔒 SECRETOS SOLO EN `.env` — placeholders en TODA la documentación
Las **bitácoras, runbooks, docs y configs de deploy versionados** llevan **solo placeholders** de secretos (`<READ_TOKEN>`, `<API_KEY>`, `<SECRET>`, …), **JAMÁS valores reales** (tokens, API keys, contraseñas, cadenas de conexión). Los valores viven **únicamente en `.env`** (gitignored). Antes de commitear cualquier doc, `git grep` el patrón del secreto para confirmar que no se cuela. Si un secreto se filtró a git: **rotarlo** (ya está comprometido) + redactar a placeholder + limpiar historial **antes** del primer push si aún no se subió. (Incidente 2026-07-08: los tokens del circuito quedaron en `docs/bitacora-sesiones.md` commiteado; se rotaron y redactaron. Ver `docs/circuito-seguridad-tokens.md`.)

### 🤖 JARVIS = el nombre de cara al usuario, y desde el item #650 también el del código

Decisión de Irving (2026-08-27, #648/#649). **Un solo asistente.** Todo lo que Irving VE se llama
**JARVIS**: el widget flotante, la pestaña, los avisos, la bandeja, los textos de la Torre y la
compuerta que antes decía «Vigilancia de Thomas».

**El código YA se renombró (item #650, 2026-08-28).** `ThomasService` → `JarvisService`,
`ThomasCommand` → `JarvisCommand`, `ThomasVigilarCommand` → `JarvisVigilarCommand`,
`ThomasVigilia` → `JarvisVigilia`; las claves `config('circuito.thomas.*')` →
`config('circuito.jarvis.*')`; la clave de compuerta `thomas` → `jarvis`; los comandos
`circuito:thomas`/`circuito:thomas-vigilar` → `circuito:jarvis`/`circuito:jarvis-vigilar`; los env
`CIRCUITO_THOMAS_*` → `CIRCUITO_JARVIS_*`; y los actores de log que se ESCRIBEN a partir de ahora
(`jarvis-mecanico`, `jarvis-ya-decidido`, antes `thomas-mecanico`/`thomas-ya-decidido`).

⚠️ **Lo histórico NO se migró, a propósito** (decisión explícita del propio item #650): las ~784
entradas ya persistidas en `roadmap_items.log`/`aprobado_por` con los valores viejos
(`thomas`/`thomas-mecanico`/`thomas-ya-decidido`) se quedan como texto congelado — igual que un log
de git no se reescribe. Ninguna lógica de negocio compara contra esos valores históricos (se
verificó antes de renombrar); `mecanicosHoy()` filtra por `whereDate(hoy)`, así que se autosana
al primer día posterior al cambio. Si alguna vez lees un `"por":"thomas-mecanico"` en un item
viejo, es correcto — es historia, no un bug.

Fuera del rename (a propósito, sin relación): `config/inversiones.php` (`'thomas'` ahí es
`THOMAS_TOKEN`, un bot de trading sin ninguna relación con el circuito) y las migraciones ya
corridas (su contenido histórico no se toca).

Regla práctica:
- **Cadena de texto que ve un humano → «JARVIS».**
- **Identificador (clase, config, clave de log, permiso, nombre de compuerta interno) → `jarvis`
  desde ahora hacia adelante; `thomas` solo sobrevive en filas históricas ya escritas.**
- Al leer código, `JarvisService` **es** JARVIS. No son dos cosas.

⚠️ **ACTUALIZACIÓN 2026-10-07 — el robotsito es JARVIS, el asistente del sistema, y NO tiene relación
con la Torre.** Decisión del equipo: el widget flotante (`IaChatFloat.vue`, el robot morado) se llama
JARVIS y es el ayudante del personal dentro del sistema (consulta clientes, pagos, tickets; prepara
cambios que el usuario confirma). Es independiente de `JarvisService`/la burbuja de la Torre (lo
de arriba). Antes estaba apagado (#649) porque contestaba solo desde lo registrado; ya no aplica.
En código vive como `Asistente` (`app/Services/Asistente/`) justamente para no confundirse con el
`JarvisService` de la Torre. Ver la sección "JARVIS, el asistente del sistema — robotsito".

### 🧩 SERVICIOS COMPARTIDOS ÚNICOS — PROHIBIDO DUPLICAR
Antes de construir **cualquier capacidad** en **cualquier módulo** (existente o futuro), verificar si ya existe en el sistema. Si existe, el módulo se **CONECTA** al servicio existente; **nunca** construye su propia versión. Servicios únicos designados:
1. **WhatsApp** → `WhatsAppAgent` es el **gateway único**. Los consumidores usan `WhatsAppGateway` + **eventos** (`WhatsAppMessageReceived`/`TextReceived`/`MediaReceived`); **nadie** monta webhook ni línea propia.
2. **Mapas** → un solo módulo provee mapas y credenciales; los demás **agregan sus funciones dentro del mapa compartido** (no su propio mapa/clave).
3. **IA** → los proveedores viven **solo** en el módulo IA (`ia_proveedores`, `/ia/configuracion`); cualquier uso de IA se conecta a los **adaptadores existentes**, sin clientes HTTP propios ni keys por módulo.

La lista **crece** al designar nuevos servicios únicos. **Ante la duda, inventario (grep cross-módulos) ANTES de escribir código nuevo.**

### 🌳 Convención OFICIAL de permisos jerárquicos por módulo (item #538, 2026-08-08)
Estándar para permisos `.view` granulares dentro de un módulo (pantalla → bloque/tarjeta → info puntual),
**ya en uso en Dashboard** (10 permisos reales en `config/route_permission.php` + consumidos en
`HomeController.php` + `Dashboard.vue`) y adoptado como convención para instrumentar módulos futuros:

```
{modulo}_view_dashboard        → pantalla/módulo completo (equivalente al "Ver módulo")
{modulo}_view_block_{seccion}  → bloque/sección dentro de la pantalla
{modulo}_view_card_{tarjeta}   → tarjeta individual
{modulo}_view_info_{x}         → info puntual
```

Guion bajo, sin puntos (difiere de otros permisos `modulo.accion` del sistema — decisión de Irving:
cero riesgo de renombrar permisos ya asignados a roles en BD por algo estético).

**Editor de roles (`administracion/rol` → `PermissionAssignmentModal.vue` +
`rol/helper/constants.js`):** cada permiso hijo se declara con `depend: "{permiso_padre}"` dentro del
array plano del módulo en `fieldsJson`, y el módulo agrupa sus anclas en `accordions[modulo]`
(`{title, filter}`). El modal ya **indenta visualmente** (clase `.perm-child`) cualquier permiso con
`depend` dentro de su accordion — el árbol se arma solo con declarar `depend`, sin tocar el template.
Los 10 permisos de Dashboard son el piloto: 1 padre (`dashboard_view_dashboard`) + 9 hijos, todos bajo
un único accordion "Ver Dashboard". **NO requiere crear permisos intermedios** ("sección" como nodo
propio) — dos niveles (padre → hijos planos indentados) bastan; no inventar un tercer nivel sin un
caso real que lo necesite.

**Denegación (alcance confirmado, sin cambio de código):** el 403 duro de
`CheckRoutePermission.php` para navegación directa por URL sin permiso **se mantiene intacto** — es
el único punto de denegación de rutas del sistema (~1750 líneas de `routes/web.php`), cambiarlo es
alto blast radius y no se justificó. La "denegación silenciosa" que pedía el item ya existe donde
importa: sidebar/tarjetas se **ocultan** (no error) vía `auth()->user()->can(...)` en Blade y `v-if`
en Vue — eso YA es el comportamiento correcto, no un gap.

### ⚠️ CRÍTICO — Los `user_id` (y otros PK) de DEV y PROD NO coinciden
DEV (`192.168.105.11`) y PROD (`192.168.105.198`) son **bases distintas que divergieron** (auto-increments independientes). **Un id de un entorno NO mapea al otro.**
- Ejemplo real: en **DEV** `users.id=8` = **Irving** (dueño); en **PROD** `users.id=8` = **Oscar Adrian, un CLIENTE** (rol `client`), e Irving en prod es **`id=13`** (`login_user=Meganet`).
- **Regla dura:** antes de tocar roles/permisos/datos de una cuenta **en prod**, resolver SIEMPRE la cuenta por **`login_user`/email**, JAMÁS por un `id` copiado de dev. Lo mismo aplica a cualquier PK sensible (módulos, clientes) entre entornos — ya documentado para `modules` más abajo.
- **Por qué:** por poco le damos `super-administrator` a un cliente de prod arrastrando el `id=8` de dev. Lo evitó **investigar (read-only) antes de tocar**. (Ver también la nota "IDs de módulo divergentes dev↔prod".)

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
2. **Webhook AMI (RESUELTO — via `cobranza:ami-listener`)** — Asterisk no hace HTTP POST por sí solo; el
   camino real es el daemon `php artisan cobranza:ami-listener` (`AmiEventListenerCommand`), que mantiene
   un socket abierto al AMI y despacha `ProcessCallResultJob`. El webhook HTTP `/webhooks/cobranza/ami-event`
   (`CobranzaWebhookController`) era código muerto duplicado — eliminado (item roadmap #275). Correr el
   listener bajo supervisor (`stubs/supervisor-ami-listener.conf`).
3. **sudoers www-data** — `asterisk -rx sip reload` y `sip show peers` para que VoipConfiguracionController funcione
4. **Prueba llamada real** — crear campaña con 1 cliente, verificar que Asterisk origina y audio llega

### Pendientes MEDIOS
5. **TTS fragmentos fijos cacheados** — pre-generar fragmentos estáticos + cachear slots variables por valor único
6. **Dashboard embajador** — vista pública `/mi-red` para que el cliente vea sus referidos y comisiones

### Item #37 — "Debug Issabel call blaster: CSV upload/download" (RESUELTO — premisa incorrecta)
Investigado: **"Issabel" (PBX de terceros, fork de Elastix) no tiene ninguna referencia en el repo**
(0 matches) y **`CobranzaBlaster` no usa CSV en absoluto** — `CobranzaCampanaService::activarCampana()`
carga los morosos directo de la BD (`clients`/`invoices`), sin paso de subir/descargar archivo, así
que el bug reportado ("download da No Data Found") no tiene superficie donde ocurrir aquí. La meta
del item (blaster SIP + transferencia a agente + voz IA) ya está resuelta por `CobranzaBlaster` con
un diseño distinto y sin ese punto de falla. El sistema Issabel original no tiene código en este
repositorio — fuera de alcance. Detalle en `docs/cobranza-blaster-issabel-item-37-verificacion.md`.

### Flujo completo cuando esté 100% operativo
```
Cron 5min → CobranzaCampanaService::cargarMorosos()
  → BlastCampanaJob → AmiConnectionService::originate()
  → Asterisk llama al cliente vía Servnet
  → Cliente contesta → escucha TTS OpenAI
  → Presiona 1 → transferencia a agente
  → cobranza:ami-listener (daemon, socket AMI) detecta el evento
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

## PORTAL DE COLABORADOR — estrategia de migración + Fase 0

Generalización del Portal Técnico a **Portal de Colaborador** (`/talento/portal`): hogar único para cualquier colaborador (técnico/vendedor/mostrador/embajador) en "modo colaborador" (solo SUS datos, fuera del panel admin). Gate: **`can:portal.colaborador`** (`routes.php:420`, ya aplicado 2026-07-03).

### ⚠️ REGLA DE PERMISOS (CLAVE) — son ACUMULATIVOS
Los permisos Spatie se **SUMAN**. Para AGREGAR usar SIEMPRE **`givePermissionTo`** (aditivo); **JAMÁS `syncRoles`/`syncPermissions`** (destructivos: reemplazan TODO). Así subir cualquier proyecto **no degrada a nadie** — una admin-colaboradora (Diana/Ariana/Tere) que recibe `portal.colaborador` conserva **todos** sus permisos admin.
- `TalentoColaboradorObserver` cumple (`givePermissionTo`/`revokePermissionTo`, nunca sync).
- ⚠️ **Footgun PENDIENTE de blindar:** `UserController::update` (~línea 278) hace `$user->syncRoles($roles)` **destructivo** desde el form de editar usuario, sin preservar roles de sistema → al guardar el form puede quitarle `super-administrator` a un dueño (pasó con Irving id=8 en dev). **NO está blindado todavía** (blindaje propuesto, no aplicado). **NO existe** ningún comando `talento:sync-user-roles` (mito descartado — el único punto destructivo es ese form).

### Estrategia: migración GRADUAL, sin forzar a nadie
El portal reemplaza al admin **fase por fase**. **POR AHORA todos entran al admin como hoy** — nadie cambia de flujo ni pierde su herramienta. Cada tipo de colaborador se "muda" (se le quita acceso admin + redirección al portal) **SOLO cuando el portal cubra TODO su trabajo** (ej: Isaac depende de Scheduling/Tareas → no se muda hasta que el portal tenga esa sección).
- **La redirección post-login automática está DIFERIDA** al paso final de cada tipo (se descartó aplicarla ahora). Diseño validado y guardado para ese momento: **allowlist** de roles de campo (`Vendedor, TECNICO, TECNICO_INSTALADOR, TECNICO_PLANTA, conductor` + `client` neutro) con **admin-primero** — cualquier rol de oficina → dashboard admin (falla-seguro). Verificado: Diana/Ariana/Tere → admin (conservan control); Brandon/Isaac → portal.

### Deuda de estilo
El portal tiene look propio (header verde "Talento Campo") distinto al admin (estilo Splynx). Unificar poco a poco conforme madure — **no urgente**.

### Fase 0 (fundación) — ✅ CERRADA (2026-07-03)
Permiso base `portal.colaborador` + auto-asignación **aditiva** (observer) + `Actor` (identidad inmutable por request) + sidebar componible (`Actor::sections()`) + **gate switch aplicado** (`talento.portal_tecnico` → `portal.colaborador`, routes.php:420) + **botón "Mi portal"** en el dropdown de usuario del topbar (gate `portal.colaborador`, `data-spa-skip`). Redirección auto **DIFERIDA** a la migración final por tipo de colaborador.
- **Validado en vivo con Brandon** (user 4429, colaborador Vendedor): entra al portal, se ve limpio (header verde "Talento Campo", sidebar Mi Talento / Cuenta, 4 sub-tabs de Mi dinero en cero — correcto, tablas vacías), Mi día / Mi dinero / Perfil funcionan.
- **Próximo: Fase A — Material.**

**Hallazgos de exploración como colaborador (Brandon) — para fases futuras:**
- (a) **Embajadores** muestra datos de EMPRESA (dashboard global de 38 embajadores/comisiones), no del colaborador → aislar por colaborador en **Fase E**.
- (b) **Portal de Pago / conciliación** accesible a colaborador (financiero interno) → **revisar aislamiento** (no debería verlo un colaborador de campo).
- (c) Rutas de **Vendedores rotas** (404): `/sellers/prospectos`, `/sellers/statistics`, `/message/reminder` → reconstruir en **Fase D**.
- (d) ✅ Aislamiento admin **OK confirmado**: `/dashboard`, `/cliente/listar`, `/message/inbox` frenan a un colaborador (403) — ese aislamiento ya funciona.

**Deuda menor OPCIONAL (no urgente):** afinar la visibilidad del botón "Mi portal" a colaboradores de campo reales — hoy también lo ven super-admins/DESARROLLADOR que heredan `portal.colaborador` del rol base (inofensivo: entran y ven solo "Cuenta").

### Fase A — Mi material (custodia de equipos) — ⏳ en validación visual
Sección "Mi material" en el portal (SOLO LECTURA, self-scoped por Actor). **No existe `talento_custodia_equipos`** — la custodia es `inventory_item_stocks` (stock polimórfico a `App\Models\User` por `user_id`); reusa `InventoryService` (Inventario).
- **A1** (commit `7abd68e2`): endpoint `GET /talento/portal/material` (gate `portal.colaborador`, user_id resuelto por `Actor::custodia()` → **IDOR cerrado**). Devuelve `en_custodia` / `pendientes_aceptar` / `historial`.
- **A2** (commit `cf7ecfa8`): `Actor::sections()` `material` `built=true` + panel UI: **resumen** (cuenta SOLO `en_custodia` = lo que tiene ahora, + valor de reposición de referencia si hay datos) + **agrupación estado→tipo** (`inventory_item_types.name`) en `q-expansion-item` colapsables. `valor_reposicion` = **INFORMATIVO** (costo de referencia), **NUNCA adeudo**. Solo front; endpoint A1 intacto. ASSET_VER=12.
- **Pendiente de cierre:** captura visual de Irving (Brandon) → recién ahí Fase A CERRADA.
- **Datos de prueba en DEV** (limpiar): `DELETE FROM inventory_item_stocks WHERE id IN (1567,1568);` (TALADRO/CASCO asignados a user 4429).

**Clasificación Herramienta/Material (aplicada — commit `dcb746fd`):** columna nueva `inventory_item_types.categoria` (`herramienta`|`material`|`null`), editable por admin, default `null`=sin clasificar (no se adivina). Backfill idempotente por nombre de los OBVIOS (**15 herramienta / 9 material**); los dudosos quedan `null`. UI "Mi material" agrupa **Categoría → Tipo** (grupo "Sin clasificar" visible como recordatorio). ASSET_VER 13. Brandon: 22 herram / 13 mat / 24 sin clasif.
- ⏳ **Pendiente: clasificar los DUDOSOS** (Irving define herramienta/material o 3ª categoría "equipo de cliente"): `ONT, MODEM, TELEFONOS DE CASA, ACOPLADOR, SPLITTER, CONECTOR, CONECTORES, CABLE, PILAS, PAPELERIA, FLYERS, CARRETE, TENSOR, TENSORES, HOJAS, ELIMINADOR, POWER`.

**Deudas menores registradas (no urgentes):**
- (b) `valor_reposicion` casi no poblado (**2 de 59** en la data real): para tener valor de referencia real en el material, hay que **poblarlo** en el inventario.
- (c) Vínculo **custodia→adeudo→nómina** real (hoy inexistente; el valor es solo informativo): **fase futura**, junto con el **material de campo consumible** (subsistema nuevo).

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

### 🎯 Punto único del conteo de unidades — `countBillableUnits()`
`LiquidationService::countBillableUnits($colaboradorId, $window)` es el **punto único de verdad** de "cuántas unidades cuentan para el pago": OTs (`talento_work_orders`) + órdenes de Talento en `tasks`, de campo o internas (desde 2026-10-26; cuenta lo que tenga puntos y sea cobrable) + puntos de proyecto externo (`ProjectActivityService`, Fase 5a). Lo comparten **`calculate()`** (liquidación) y **`breakdown()`** (desglose del Portal Técnico / `avance`) → **el portal muestra EXACTAMENTE lo que se paga**, no pueden divergir. **NO agregar cálculos de unidades paralelos** (antes `avance`/`breakdown` contaban WO-only y divergían del pago cuando había tasks/proyectos). Portal "Mi dinero": endpoints self-scoped `/talento/portal/dinero/{cuenta,desglose,fondo,prestamos}` (commit `eeb419f4`).

### 🐛 Deuda registrada aparte (RESUELTA — commit `c37feef2`, 2026-07-11)
`DashboardService::tecnicoPreview` y `::equipoPreview` (alias "`::team`") truenan por `with('level')` (relación inexistente en `TalentoColaborador`) — **ya corregido**: se agregó la relación `level()` + `level_id` a `$fillable`. Detalle en item #222 (roadmap) → `docs/talento-dashboardservice-level-item-222-verificacion.md`.

---

## Adjuntos del roadmap (#9991162 → #9991163/#9991164/#9991165, 2026-09-14) — maquetas y evidencia amarradas a items

Origen: #9990934 exigía `docs/maquetas/panorama-arbol.html`, nunca existió en el repo, la terminal
construyó a ciegas y el Árbol salió a medias. Ahora **Panorama → 📎 Adjuntos** (colapsado) sube
archivos (html, md, txt, csv, png, jpg, jpeg, webp, gif, pdf, xlsx, docx, json, zip; 25 MB; MIME
real con finfo; dedupe sha256) y los amarra a 1..N items. Bytes en el disco `roadmap_adjuntos`
(`/var/www/megaisp/storage/app/roadmap/adjuntos/<uuid>.<ext>`, **fuera de public/**, ruta absoluta
del checkout principal); se sirven solo por `GET /api/roadmap/adjuntos/{id}/descargar` con
`roadmap.adjuntos.view` (HTML/SVG **siempre** `attachment` + `application/octet-stream`; imagen/PDF
inline con `?inline=1`). Borrar = lógico + 30 días (`roadmap:adjuntos-purgar` diario).

**Regla para las terminales:** si el prompt empieza con `## ADJUNTOS DE ESTE ITEM (léelos antes de
empezar)`, esas rutas son el **contrato** del item — leerlas con Read/cat antes de decidir nada.
El bloque lo antepone `vuelta.sh` vía `circuito:adjuntos-prompt {id}` (no vive en el `prompt` de
BD). **Fail-closed:** si un adjunto registrado no está en disco, `claimNextParalelo()` no reclama
el item y `adjuntos-prompt` sale 2 → la vuelta **no arranca**; el item queda `requiere_irving` +
`motivo_espera=adjunto_faltante` (bandeja "Esperan un insumo") con las rutas en `motivo_bloqueo`.
Re-subir el mismo archivo revive el registro. La ficha (`/roadmap/item/N`) lista los adjuntos y la
fila del árbol muestra `📎 N`. Para versionar un adjunto duradero:
`php artisan roadmap:adjunto-promover {id} --destino=docs/maquetas` (copia + imprime el commit,
**no** commitea).

⚠️ Gotcha (item #9991170): con `CACHE_DRIVER=file` la caché de permisos Spatie es **por checkout**.
Tras una migración que crea permisos (corre desde el worktree), el principal responde **403** hasta
`php artisan permission:cache-reset` en `/var/www/megaisp`; el post-merge del runner no lo hace.

## Candado por worktree (#502, 2026-10-16) — una sesión por worktree

Dos sesiones de la cuenta compartida usaron el mismo worktree (`/var/www/megaisp-cc`) y una le
cambió la rama a la otra. Ahora un hook PreToolUse (`/var/www/megaisp/.claude/settings.json` →
`deploy/circuito/candado-worktree.sh`) le da a la primera sesión que edita o mueve el HEAD de un
worktree el candado de ese worktree; una segunda sesión viva recibe un bloqueo con instrucciones.
- **Trabaja en tu propio worktree:** `php artisan circuito:provision-worktree --sesion=<nombre>`
  (crea `/var/www/megaisp-<nombre>`). Fuera del candado: el checkout principal (ahí se mergea;
  verificar `git branch --show-current` = main) y los slots del circuito (`/home/meganet/circuito/*`).
- El candado vence solo si la sesión dueña terminó o lleva 3 h sin usar el worktree.
  Ver: `bash deploy/circuito/candado-worktree.sh estado`; soltar a mano: `… soltar <ruta>`.
  Bitácora de tomas/bloqueos: `storage/logs/candado-worktree.log` del principal.

## Persona en commits — de quién es cada sesión (2026-10-26)

La cuenta la usan varias personas y antes todo salía firmado "Irving MegaISP", así que los
resúmenes del día se mezclaban. Ahora un hook `SessionStart` (`deploy/persona/persona.sh`) le pone a
cada sesión la persona que está del otro lado, y git firma commits y merges con su nombre. La
persona se toma de `claude-como <persona>` (SSH) o del login de la terminal web.
- Si al arrancar sale **PERSONA SIN DECLARAR**: antes del primer commit o merge, preguntar quién es
  y correr `bash /var/www/megaisp/deploy/persona/persona.sh declarar <persona>`.
- **Resumen del día de una persona** = `persona.sh commits <persona> [fecha]` + `persona.sh sesiones
  <persona> [fecha]`; nunca todo el `git log` del día. El texto sigue en voz impersonal.
- Mapa de personas fuera del repo (`~/.config/megaisp/personas.tsv`); detalle en
  `deploy/persona/README.md`. Los slots del circuito siguen firmando como antes.

## Histórico fuera de este archivo (#1078, 2026-10-30)

Para que cada vuelta del circuito arranque con menos contexto, el detalle histórico se movió sin cambios a `docs/claude-md/`:

- `docs/claude-md/items-resueltos.md` — las 56 notas «Item #…» ya resueltas (la mayoría, el «bucle reap sobre paraguas ya descompuesto»: desde #1078 lo resuelve solo `circuito:cerrar-vuelta`).
- `docs/claude-md/modulo-flotas.md` — la bitácora completa del módulo Flotas (Fases 1 a 3.4).

Si una tarea toca alguno de esos temas, leer el archivo correspondiente antes de investigar.

