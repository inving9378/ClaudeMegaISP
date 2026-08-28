# Item #103 — Celular conductor como tracker GPS en APK (RESUELTO — ya implementado)

**Fecha de verificación:** 2026-08-28 · **Worker:** wt-5

## Premisa del item

> "Permitir que el conductor use su celular como dispositivo GPS cuando no hay hardware.
> Backend POST /conductor/posicion ya implementado (FleetPositionService). Falta UI en APK:
> botón activar tracking, solicitar permisos de ubicación, loop de envío cada N segundos."

## Hallazgo

Tanto el backend como la UI de APK que el item pedía **ya están construidos, mergeados y
funcionando** — no en este repo (`megaisp`) sino repartidos entre este repo (backend) y el
repo separado `/var/www/megafamilia-rn` (UI móvil, React Native). No requiere código nuevo.

### Backend — `POST /api/megafamilia/conductor/posicion`

Vive en `app/Modules/Addons/MegaFamilia/Controllers/ConductorApiController.php::reportarPosicion`
(NO en `Flotas/FleetGpsController` como la premisa asumía). Registrado en
`app/Modules/Addons/MegaFamilia/routes.php:329-338`, guard `role:conductor|role:super-administrator|role:DESARROLLADOR`,
auth Sanctum. Además de `reportarPosicion` expone `vehiculo`, `posicion-actual`,
`historial-posiciones`, `geocercas`, `eventos-geocerca`, `documentos`, `mantenimientos` — toda
la vista del conductor sobre su vehículo asignado.

`reportarPosicion`:
- Resuelve el vehículo del conductor por `FleetAssignment` (self-scoped por `auth()->id()`,
  sin IDOR — nunca recibe `vehicle_id` del cliente).
- Crea/reusa un `FleetDevice` `brand=phone` (enum `fleet_devices.brand` ya incluye `'phone'`
  desde la migración `2026_08_18_190000_add_phone_brand_to_fleet_devices.php`).
- **Anti-jump**: descarta la posición si hay un salto >1 km en <5s respecto a la última
  (protección que el endpoint gemelo que se intentó construir en `Flotas` NO tenía).
- Reusa `FleetPositionService::saveBatch` (Fase 2 GPS) sin tocar su lógica — la detección de
  geocercas (Sub-fase 3.2) y las notificaciones (3.3) corren igual que con hardware GPS real.

**Historia relevante ya en `git log` de este repo** (no hace falta repetirla, ya quedó
resuelta): el commit `6ff9bad1` (2026-08-18) agregó por error un endpoint DUPLICADO
`POST /flotas/api/conductor/posicion` en `Flotas/FleetGpsController` dando por buena la
premisa de que el backend no existía; el commit siguiente `a3d38742` (mismo día, 5 min
después) lo revirtió al encontrar con un grep más amplio que el endpoint real ya existía en
MegaFamilia — regla "servicios compartidos únicos" del CLAUDE.md. Se conservó la migración del
enum `'phone'` porque corregía un bug real de `STRICT_TRANS_TABLES`. El endpoint de
`FleetGpsController` **NO existe hoy** (confirmado: `grep -n "function " FleetGpsController.php`
no lista `conductorPosicion`) — es correcto, fue revertido a propósito.

### UI móvil — `megafamilia-rn` (React Native), rol `conductor`

Repo separado en `/var/www/megafamilia-rn` (fuera del worktree del circuito — no se tocó,
solo se leyó). Ruteo por rol ya resuelve `role === 'conductor'` →
`src/navigation/RootNavigator.tsx:50-52` → `ConductorNavigator` →
`src/screens/conductor/ConductorDashboard.tsx`, que monta 4 tabs, uno de ellos **"Mapa"** →
`src/screens/conductor/tabs/MapaTab.tsx`.

`MapaTab.tsx` implementa **exactamente** los 3 puntos que el item pedía:
1. **Botón activar tracking** — `TouchableOpacity` con texto "▶ Iniciar tracking" / "⏹ Detener"
   (líneas 84-92), toggle de estado `tracking`.
2. **Solicita permisos de ubicación** — `PermissionsAndroid.request(PERMISSIONS.ACCESS_FINE_LOCATION)`
   en Android antes de arrancar (línea 24); si se niega, no arranca.
3. **Loop de envío cada N segundos** — `setInterval(..., POLL_INTERVAL_MS)` con
   `POLL_INTERVAL_MS = 30000` (línea 8, comentario propio del código: *"mismo intervalo que
   Flutter"* — esta feature ya existía en la app Flutter predecesora de MegaFamilia y se portó
   tal cual al hacer el RN rewrite, ver nota de `item #75` en este mismo CLAUDE.md sobre esa
   migración). Cada tick llama `Geolocation.getCurrentPosition` → `reportarPosicion(lat, lng, speed)`.

`reportarPosicion` vive en `src/stores/useFlotasStore.ts:69-72` (Zustand store) y hace
`api.post('/conductor/posicion', { lat, lng, speed })` contra el backend de arriba. Indicador
visual de estado activo ("Reportando posición cada 30 segundos", punto verde) cuando
`tracking=true`.

## Estado del rol `conductor` en DEV

El rol `conductor` existe en BD (`id=19`) pero **0 usuarios** lo tienen asignado hoy — es
esperado: es rollout de negocio (asignar el rol a choferes reales), no parte de la capacidad
técnica que pedía el item. Fuera de alcance de #103 (el item pedía "UI + permisos", no
"dar de alta conductores").

## Por qué se cierra sin tocar código

- Backend: confirmado en este repo, `php -l` limpio, endpoint registrado y accesible, reusa
  `FleetPositionService::saveBatch` sin duplicar lógica (regla "servicios compartidos únicos").
- UI: confirmada en `megafamilia-rn`, wireada de rol → navegación → tab → acción, con permiso
  de ubicación real y loop de 30s — sin mocks, sin placeholders, sin `TODO`.
- Tocar `megafamilia-rn` habría violado el aislamiento (#334): es un checkout compartido sin
  worktree dedicado al circuito, igual que `megafamilia-rn` fue documentado como bloqueado en
  el item #639. Aquí no hace falta tocarlo — ya está hecho.

## Verificación (sin capturas — app móvil, no navegador)

- `php -l` de `ConductorApiController.php` y `FleetGpsController.php`: sin errores.
- `php artisan tinker`: rol `conductor` existe (id=19); ruta registrada
  (`role:conductor|super-administrator|DESARROLLADOR`).
- Lectura de código fuente de `megafamilia-rn` confirma la cadena completa
  rol→navegación→pantalla→permiso→loop→POST, sin eslabones faltantes.
- Pendiente (fuera de alcance de este item): validación visual de Irving con un usuario real
  rol `conductor` en un dispositivo/emulador Android, y decidir si se empieza a asignar el rol
  a choferes reales.

**Sin cambio de código en `megaisp`.** Solo este documento + nota en `CLAUDE.md`.
