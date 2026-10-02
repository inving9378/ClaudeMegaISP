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

## 2026-10-02 13:00 — Seguimiento: se queda en 100% eterno, posible caché del teléfono

David aclaró: el tamaño (140→67MB) no era el problema real — dijo que lo podía dejar en 140MB
igual. El síntoma concreto es otro: la descarga **llega al 100% y se queda ahí para siempre**, sin
terminar nunca. Sospecha caché del teléfono y pidió renombrar el archivo a `meganetVxxx`.

**Diagnóstico de servidor (sin encontrar nada anómalo):** `nginx.conf` tiene `sendfile on` +
`tcp_nopush on` (configuración estándar correcta para archivos estáticos grandes), sin
`limit_rate` ni timeouts raros. Una descarga completa de 67MB desde el propio servidor termina en
menos de 1 segundo con MD5 correcto — el servidor entrega el archivo completo y bien formado. Esto
apunta a que el problema vive del lado del teléfono/navegador (una entrada de caché/descarga
vieja asociada al nombre de archivo anterior, `talento-vX.XX.apk`, usado en cada publicación
previa), no en cómo se sirve el archivo.

**Aplicado:** se adoptó `meganetV{versionCode}.apk` como nombre de archivo permanente para cada
versión nueva (nunca repetido, imposible que choque con una caché vieja) — primera publicación
con este nombre: `meganetV112.apk`. `apk_url` en `talento_app_releases` actualizado a
`https://dev.meganett.com.mx/downloads/meganetV112.apk`; el archivo viejo con el nombre anterior
(`talento-v1.12.apk`) se borró para no dejar copias duplicadas. QR y detección de actualización
verificados con la nueva URL. La reducción de tamaño a 67MB se conservó (sin motivo para
revertirla, aunque no fuera la causa raíz).

Queda pendiente la validación real de David: si el nombre nuevo no resuelve el "se queda en 100%",
el problema es genuinamente de red/dispositivo (no de caché de nombre de archivo) y habría que
seguir investigando desde ese ángulo (por ejemplo, probar la descarga con datos móviles vs. wifi,
o con otro navegador).
