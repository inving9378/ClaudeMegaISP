## 2026-10-02 (continuación) — "La app nunca termina de descargar"

### Pedido 1: cambiar la URL de descarga

David pidió usar `30.123.192.199:3032` o `https://dev.meganett.com.mx` (esta última, "mejor") en
vez de la IP LAN `192.168.105.11` — tanto en el QR como en la detección de actualización dentro
de la app.

Verificado que `dev.meganett.com.mx` resuelve públicamente a `38.123.192.199` (la IP pública real
del servidor) y sirve el mismo archivo exacto por HTTPS. Cambio aplicado **solo en datos**: el
`apk_url` en `talento_app_releases` — tanto `TalentoMobileEquipoController::downloadQr()` como
`TalentoMobileApiController::latestRelease()` ya leen ese campo dinámicamente, así que no hizo
falta tocar código para que el QR y el aviso de actualización usaran la nueva URL.

### Pedido 2: "revisa la app porque nunca termina de descargar"

Causa real encontrada: el instalable pesaba **140 MB**, pero traía las **4 arquitecturas nativas**
de Android empaquetadas (arm64-v8a, armeabi-v7a, x86, x86_64). `x86`/`x86_64` **solo existen en
emuladores de escritorio** — ningún teléfono real las usa jamás. Eran 67 de los 140 MB totales,
puro peso muerto para cualquiera instalando desde un celular real — exactamente el tipo de cosa
que hace que una descarga "nunca termine" en datos móviles o wifi lenta.

**Fix:** `android/app/build.gradle` → `ndk.abiFilters` restringido a `"arm64-v8a"` y
`"armeabi-v7a"` (cubren el 100% del hardware Android real, desde los más viejos de 32 bits hasta
hoy). Sigue siendo un solo APK, una sola URL — solo que sin las 2 arquitecturas de emulador que
nadie real necesita.

**Resultado:** 140,675,428 → 69,847,002 bytes (**-50%**). Verificado: el APK solo contiene
arm64-v8a/armeabi-v7a, el bundle tiene el contenido esperado, MD5 idéntico en build/servido/
descargado, descarga completa en menos de 1 segundo desde el propio servidor.

### Pedido 3: "cambia el nombre de la app a Meganet"

Ya estaba hecho desde una sesión anterior — el nombre que se ve (ícono en el teléfono, nombre en
Ajustes de Android) ya es "Meganet" en todos lados donde es visible. Lo único que todavía dice
"TalentoEquipo" es plomería interna invisible (el nombre del paquete npm, el identificador interno
que usa React Native para conectar la pantalla principal) — cambiar eso no se vería reflejado en
ningún lado para el usuario y sí tiene riesgo real de romper el arranque de la app si algo queda
desincronizado. No se tocó; si de verdad se quiere el renombrado interno completo (sin beneficio
visible), es un paso aparte a confirmar explícitamente.

### Commits

App: `af97c40` en `/home/meganet/TalentoEquipo` (rama `master`).

**APK:** `https://dev.meganett.com.mx/downloads/talento-v1.12.apk` — versión **1.12**
(versionCode 112), **67 MB** (antes 140 MB).

Memoria guardada: `https://dev.meganett.com.mx/downloads/...` es la URL correcta para publicar
futuras versiones (no la IP LAN).
