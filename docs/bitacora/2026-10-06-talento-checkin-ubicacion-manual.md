## 2026-10-06 — Check-in/check-out: ubicación manual cuando el GPS no responde

Pedido de Irving: en la app de Talento, si el GPS no reconoce la ubicación al hacer check-in,
debe poder indicar a mano dónde está — guardando siempre latitud/longitud real, para poder
ubicarlo en un mapa. Extendido a mitad de la tarea ("ademas debe dejar hacer checkout") para que
el mismo mecanismo aplique también al check-out. Decisión tomada con Irving sobre el mecanismo:
combinar **buscar por texto una dirección** (geocodificada) **y** poder **ajustar el pin a mano
sobre un mapa real**.

### Hallazgo de arranque

El check-out de la app móvil **nunca guardaba ninguna ubicación** —
`TalentoMobileApiController::checkout()` no aceptaba `lat`/`lng` en absoluto, aunque las columnas
`check_out_lat`/`check_out_lng` ya existían en la tabla (las usa otro camino del sistema,
`AttendanceService`, que esta app no consume). No era solo "faltaba el fallback manual", faltaba
la ubicación del check-out por completo.

### Backend (`/var/www/megaisp`, rama `feature/talento-checkin-ubicacion-manual`, SIN mergear)

- Migración aditiva: `check_in_location_source`/`check_out_location_source` en
  `talento_attendances` (`gps`/`manual_address`/`manual_pin`).
- `GeocodingService` nuevo — primer consumidor real de la llave `google-maps` del Hub de
  Integraciones (hasta hoy huérfana, nadie la usaba para geocodificar de verdad; solo existía un
  ping de validación). Resuelve la llave con el mismo patrón de 3 niveles que ya usan
  `ClaudeApiClient`/`OpenAiTtsDriver` (`UsesApiIntegration::resolveApiKey`) — se agregó la entrada
  que faltaba en `config/services.php` + el mapa del trait.
- Endpoint nuevo `GET /talento/api/geocodificar?q=...`.
- `checkout()` ahora acepta y guarda `lat`/`lng` (antes no guardaba nada).
- Verificado: `php -l` en los 6 archivos, `php artisan migrate --pretend` confirma el SQL
  correcto. **La migración NO se corrió** (guardrail de migraciones #534 — rama sin ruta a main;
  se corre después del merge, mismo flujo que se usó hoy mismo con la migración de WhatsApp).

### App móvil (`/home/meganet/TalentoEquipo`, commit `3d3b63a` en `master`)

- Nueva dependencia nativa `react-native-maps` — pineada en `1.20.1` (NO la `^1.27.2` que usa
  `megafamilia-rn`: esa versión exige React ≥18.3.1 y este proyecto sigue en React 18.2.0/RN
  0.74.5; `1.20.1` es la última con peer deps compatibles).
- Mapa con tiles de **OpenStreetMap** (`UrlTile`, sin llave de Google) — verificado que
  `megafamilia-rn` tampoco tiene ninguna llave de Google Maps configurada en Android a pesar de
  usar `react-native-maps`, así que no se copió esa pieza a ciegas.
- Componente nuevo `UbicacionManualModal.js`: buscador de dirección + mapa con pin arrastrable,
  mismo patrón visual que `SignaturePad.js` (header con flecha de regreso + título).
- `AsistenciaPanel.js`: un solo punto (`resolverUbicacion()`) intenta GPS primero y cae al modal
  manual si falla (sin permiso o sin fix) — lo usan tanto `handleCheckin` como `handleCheckout`.

### Verificación real (sin dispositivo — no hay teléfono/emulador en este servidor)

- `npm run bundle:android` generó el bundle JS limpio (usando los mismos **stubs temporales** de
  `react-native-reanimated`/`rn-fetch-blob` que sesiones previas del 28-sep/1-oct ya documentaron
  como deuda conocida — una dependencia transitiva de `vision-camera-code-scanner`, nunca
  instalada, que el bundle de producción necesita para completar aunque esta sesión no la toque;
  stubs borrados después, nunca commiteados).
- `cd android && ./gradlew assembleDebug` → **BUILD SUCCESSFUL**, con `react-native-maps`
  compilado end-to-end en el árbol de tareas de Gradle (no solo "no truena", se confirmó el
  módulo nativo real linkeado).
- Se extrajo el `.bundle` de DENTRO del APK generado y se confirmó con grep que mi código nuevo
  está ahí ("Confirmar esta ubicación", "No detectamos tu GPS", la URL de tiles de
  OpenStreetMap) — mismo nivel de verificación "no confiar en que el proceso debió funcionar" que
  usaron las sesiones anteriores de esta app.
- El bundle committeado (`android/.../index.android.bundle`, que este repo versiona junto al
  código) se dejó **intacto en su versión de v1.14** — no se regeneró como parte de este commit,
  porque eso es parte del flujo de "cortar una versión nueva" (bump de versión + release), que no
  se hizo todavía.

### Riesgo honesto, sin poder probarlo en pantalla real

El mapa usa tiles de OpenStreetMap (sin llave), pero el lienzo nativo de abajo sigue siendo el
Google Maps SDK for Android de `react-native-maps` — si esa capa exige una llave de Google
declarada en el manifiesto solo para INICIALIZARSE (más allá de qué tiles pinte encima), el mapa
podría salir en blanco hasta que alguien lo abra en un teléfono real. La solución, si pasa eso,
es una llave de Maps dedicada y restringida al paquete Android de esta app — decisión que no se
tomó aquí a propósito (sería meter una credencial de Google dentro de un APK distribuido sin que
Irving lo decida).

### Pendiente

- Mergear la rama de backend a `main` + correr la migración real (igual que WhatsApp hoy).
- Cortar una versión nueva de la app (bump + bundle real + APK) para que alguien la pruebe en un
  teléfono de verdad — no se hizo todavía, a propósito, hasta tener luz verde.
