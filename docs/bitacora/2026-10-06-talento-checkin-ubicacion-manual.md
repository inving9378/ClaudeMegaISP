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

## 2026-10-06 06:55 — Fusionado a main, migración corrida, APK v1.15 publicado

Irving dio luz verde a los dos pendientes de arriba: "Sí, fusiona y corre la migración, y arma
el APK para probarlo".

### ⚠️ Hallazgo real antes de fusionar — colisión del Circuito CC sobre este mismo checkout

Al revisar la rama antes de fusionarla apareció un commit de merge que yo NUNCA hice
(`fc831699`, mensaje "Integra servicios-por-modulo a main"), con dos padres: mi propio commit
(`7d768205`) y la rama ajena `servicios-por-modulo` (de OTRA sesión, con su propio worktree en
`/home/meganet/megaisp-wt-integrations`). El reflog de mi rama confirma el momento exacto:
`feature/talento-checkin-ubicacion-manual@{1}: merge servicios-por-modulo: Merge made by the
'ort' strategy` — a las 06:39:54, justo cuando yo estaba corriendo `./gradlew assembleDebug` en
este mismo directorio (`/var/www/megaisp`). `main` seguía intacto (NO contenía ese merge) — la
mutación quedó solo en mi rama, porque mi rama era lo que estaba checkeado aquí en ese instante.

Esto es el mismo patrón ya documentado en memoria (`feedback_trabajar_en_worktree_cc.md`): el
MergeRunner del Circuito hace sus `git checkout`/`git merge` **sobre este mismo checkout
compartido** (`/var/www/megaisp`), y si alguien más (yo, en este caso) tiene una rama propia
checkeada ahí mismo mientras el runner procesa OTRO item aprobado, el merge del runner puede
aterrizar en la rama equivocada en vez de en `main`. No causó pérdida de datos (la rama
`servicios-por-modulo` sigue intacta en su propio worktree, sin tocar), pero si yo hubiera
fusionado mi rama tal cual a `main` sin revisar, habría metido ese trabajo ajeno sin que nadie lo
aprobara por su propio camino.

**Corrección aplicada:** se armó una rama limpia (`talento-checkin-limpia`) desde `main`,
`cherry-pick` de mis 3 commits reales (sin el merge contaminado), verificado que el diff contra
`main` solo trae mis archivos, y esa rama limpia es la que se fusionó. La rama contaminada
(`feature/talento-checkin-ubicacion-manual`) quedó sin borrar — git se niega a borrarla con `-d`
por tener commits "sin fusionar" (los de `servicios-por-modulo`, que son de otra persona, no
míos) y no se usó `-D` a la fuerza. Queda ahí, inofensiva, hasta que Irving decida.

### Backend — fusionado y migrado

- Merge `8b1ae0ea` ("Integra talento-checkin-limpia a main") — limpio, sin la contaminación.
- `php artisan migrate --force`: migración `2026_10_06_070000_add_location_source_to_talento_attendances`
  corrió en **126ms**, columnas `check_in_location_source`/`check_out_location_source`
  confirmadas en la tabla real.

### App móvil — versión 1.15 publicada

- `versionCode` 114→115, `versionName` "1.14"→"1.15".
- Bundle regenerado (mismos stubs temporales de `react-native-reanimated`/`rn-fetch-blob`,
  borrados después) + `gradlew assembleRelease` → **BUILD SUCCESSFUL en 5m33s**, APK de 62.9 MB
  (más chico que el debug de verificación, 144 MB — normal, release minifica/usa Hermes).
- Verificado el bytecode Hermes compilado DENTRO del APK (no el bundle plano — el release usa
  Hermes, el debug de la verificación anterior no): `openstreetmap.org`, `geocodificar` y
  `UbicacionManual` SÍ aparecen como literales en el bytecode — confirma que mi código quedó
  realmente empaquetado (los textos con acentos no se pudieron grep-ear por cómo Hermes codifica
  su tabla de strings — no es señal de un problema, los literales ASCII sin acentos sí matchean).
- MD5 **idéntico en las 3 paradas**: build de Gradle → copia en `public/downloads/meganetV115.apk`
  → descarga real por HTTPS (`a3f5aab6bd2cbedceee7ca79f326fe1b`).
- Fila nueva en `talento_app_releases` (id=12, activa; la v1.14 desactivada) — confirmado también
  que `GET /talento/api/app/latest` ya anuncia la 1.15 (así que el aviso de actualización dentro
  de la app también la va a ofrecer, no solo la descarga directa).
- Commit `e6ddfa8` ("release: v1.15") en `TalentoEquipo` (`master`, sin remoto).

### Pendiente real (al cerrar el merge/migración/v1.15)

- Que Irving instale/actualice la app en su teléfono y pruebe de verdad el flujo (buscar
  dirección + mover el pin) — sigue siendo el único "no probado" de esta tarea, por la ausencia
  de teléfono/emulador en este servidor.
- Decidir qué hacer con la rama contaminada `feature/talento-checkin-ubicacion-manual` (borrarla
  o dejarla) y, aparte, si vale la pena blindar el MergeRunner del Circuito para que no vuelva a
  operar sobre un checkout con una rama ajena activa — fuera de alcance de esta tarea, señalado
  para que Irving decida si se convierte en un item del roadmap.

### El riesgo que había avisado se cumplió — v1.15 crasheaba al hacer check-in

Irving probó v1.15 en su teléfono real: **"cuando voy a hacer el check in se me cierra la app"**.
La causa exacta, confirmada (no supuesta): al revisar el `AndroidManifest.xml` *dentro* del APK
publicado, faltaba por completo la etiqueta de la llave de Google Maps para Android
(`com.google.android.geo.API_KEY`) — justo el riesgo honesto que había dejado anotado arriba antes
de publicar v1.15 ("el mapa podría salir en blanco... o algo peor"). Investigación externa
(búsqueda web) confirmó el comportamiento documentado: **sin esa llave, el `MapView` nativo lanza
una excepción apenas intenta montarse en pantalla, y se lleva a toda la app con él** — no es un
"mapa en blanco", es un crash duro. Coincide exacto con el síntoma reportado: el check-in solo
truena cuando el GPS no resuelve rápido y se abre el modal de ubicación manual (que es cuando el
`MapView` intenta aparecer por primera vez) — con GPS funcionando normal, el modal nunca se monta
y no hay crash.

**Decisión de Irving:** reusar, por ahora, la misma llave de Google Maps que ya usa el backend
para geocodificar (en vez de esperar a tramitar una llave dedicada y restringida al paquete de
la app) — para poder probar la función hoy mismo. Esa llave queda ahora físicamente dentro del
APK (en el manifiesto), visible para quien lo desarme — riesgo explícito que Irving aceptó
conscientemente; pendiente, si se quiere más adelante, separarla en una llave propia restringida
por paquete+huella digital en Google Cloud Console.

**Fix (v1.16, versionCode 116):**
- `android/maps.properties` nuevo (gitignored, mismo patrón ya establecido que
  `android/keystore.properties` — que de paso también se agregó al `.gitignore`, no estaba
  pese a no estar trackeado).
- `build.gradle`: `manifestPlaceholders = [MAPS_API_KEY: ...]` leído de ese archivo.
- `AndroidManifest.xml`: `<meta-data android:name="com.google.android.geo.API_KEY" .../>`.
- Verificado con `aapt dump xmltree` sobre el APK YA COMPILADO que la llave real quedó inyectada
  en el manifiesto (no un placeholder vacío).
- ⚠️ **Nota propia de higiene:** al verificar esto con `aapt`, el valor real de la llave quedó
  impreso en la salida de un comando (visible en esta sesión) — error de mi parte, debí redactarlo
  antes de imprimir. Riesgo bajo en este caso puntual (es una llave pensada para ir embebida en un
  APK distribuido — su protección real es la restricción por paquete+huella en Google Cloud, no
  mantenerla en secreto — y de hecho esta misma llave ya iba a quedar visible dentro del APK
  público de todas formas, que es justo lo que Irving ya había aceptado), pero queda anotado para
  no repetir el hábito con una llave que si sea secreta de verdad.
- Publicado como **v1.16** (reemplaza a v1.15, que quedó borrado del release de GitHub y del
  servidor — v1.15 nunca debe instalarse, crashea). MD5 verificado en las 3 paradas de nuevo
  (`46f24a701556379b4f46b4b9f2b0e39c`). Fila `talento_app_releases` id=13, marcada
  `is_mandatory=true` (la versión previa activa, v1.15, está rota).
- Commit `c85c994` ("release: v1.16") en `TalentoEquipo`.

**Pendiente real:** que Irving confirme en su teléfono que el check-in ya no crashea — sigue
siendo el único "no probado" posible desde este servidor sin dispositivo físico.

### Segundo bug real — el número de versión interno nunca se actualizó (v1.17)

Irving instaló v1.16 limpio (desinstalando primero) y la app seguía avisando "hay una
actualización disponible, la 1.16" — en bucle, ofreciendo actualizar a la versión que ya tenía
instalada. Causa confirmada: `src/config.js` define `APP_VERSION` como un **string suelto, a
mano, separado de `versionName` de `android/app/build.gradle`** — y se me olvidó actualizarlo al
publicar tanto v1.15 como v1.16 (se quedó en `'1.14'` las dos veces). `MiDiaScreen.js::checkUpdate()`
calcula su propio "versionCode" a partir de ESTE string (no lee nada real del APK instalado), lo
manda al servidor, y el servidor siempre contestaba "sí hay algo más nuevo" porque comparaba
contra 114 sin importar qué tan nueva fuera la build real instalada.

**Fix (v1.17, versionCode 117):** `APP_VERSION` corregido a `'1.17'` + comentario explícito de
advertencia (ya se olvidó 2 veces seguidas) + nota de que el candidato correcto a futuro es
`react-native-device-info` (lee la versión real del APK instalado, elimina la duplicación de
raíz) — no se instaló esa dependencia nueva en esta vuelta para no sumar más riesgo nativo
después del susto de la llave de Maps; queda anotado como mejora futura si se repite el olvido
una tercera vez.

- v1.16 (roto por este bug, aunque el fix de la llave de Maps SÍ era correcto ahí) borrado del
  release de GitHub y del servidor.
- Publicado v1.17, MD5 verificado en las 3 paradas (`acd1b0bcef797a4c98d6ae3af7224ce0`). Fila
  `talento_app_releases` id=14, `is_mandatory=true`. Commit `d95f40f`.

**Pendiente real:** que Irving confirme en su teléfono, con esta versión, que (a) ya NO avisa
actualización disponible al abrir la app recién instalada y (b) el check-in ya no crashea.
