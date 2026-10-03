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

## 2026-10-02 (continuación) — "revisa el modo oscuro que no tiene ningún botón"

David, sobre el modo oscuro entregado en v1.11: **no hay ningún botón** para activarlo/
desactivarlo, **no está en todas las vistas**, y tampoco está aplicado **en el menú de abajo**
(la barra de pestañas).

Causa real: el toggle de tema existía, pero solo vivía **escondido** dentro del menú "Opciones"
(⋮) de la pantalla Mi día — ninguna otra pantalla lo mostraba, y la barra de pestañas inferior
(`tabBarStyle`) nunca se conectó al tema (se quedaba blanca fija aunque el resto ya estuviera
oscuro).

**Fix:**
- `src/components/ThemeToggleButton.js` (nuevo) — botón sol/luna de un solo tap.
- Enganchado como `headerRight` **compartido** en `Tab.Navigator` (las 4 pestañas) y en
  `Stack.Navigator` (todas las pantallas internas: OTDetalle, Evidencia, Cierre,
  SeccionDetalle, FirmarDocumento, CursoDetalle, TomarExamen, CajaLectura, InspeccionCalidad,
  ProyectoDetalle, RouteDetalle) — con esto el botón aparece en **todas** las vistas con header
  sin tocar cada pantalla una por una. Mi día conserva su propio menú ⋮ al lado.
- `tabBarStyle`/`tabBarActiveTintColor`/`tabBarInactiveTintColor` del `Tab.Navigator` ahora leen
  del tema — la barra de pestañas de abajo ya cambia de clara a oscura con el resto de la app.
- Login y Configuración del servidor (se muestran ANTES de iniciar sesión, sin header de
  navegación) reciben su propio botón flotante en la esquina superior.
- Theming completo (mismo patrón ya usado en el resto de la app) aplicado a las pantallas que
  todavía usaban colores fijos: Órdenes, Detalle de OT, Evidencias, Cierre de OT, Mi semana,
  Login, Configuración del servidor y el marco de la Firma del cliente.
- A propósito, SIN cambiar: la cámara (al tomar una foto de evidencia, y el escáner de
  código/QR) se queda con su pantalla oscura fija en ambos temas — es el comportamiento normal
  de cualquier visor de cámara. La hoja donde el cliente firma se queda blanca (tinta sobre
  papel) — solo el marco alrededor sigue el tema.

**Verificado:** bundle reconstruido y confirmado dentro del APK final (strings nuevas presentes),
APK de 69.8MB con MD5 idéntico en 3 puntos (build, copia pública, descarga real desde
`dev.meganett.com.mx`), ambos endpoints de la app (QR de descarga y aviso de actualización)
confirmados apuntando a la nueva versión con un token real.

**Commit:** `102fd0b` en `/home/meganet/TalentoEquipo` (rama `master`).

**APK:** `https://dev.meganett.com.mx/downloads/meganetV113.apk` — versión **1.13**
(versionCode 113), 69.8 MB. El archivo anterior (`meganetV112.apk`) se borró tras confirmar que
el nuevo link funciona.

Pendiente: validación visual de David en el teléfono (que el botón se vea y funcione en todas
las pantallas, y que la barra de abajo también cambie de color).

## 2026-10-02 (continuación) — "desde la 1.11 es el problema, se puede demorar bastante tiempo"

David pidió verificar si el "se queda descargando y no hace nada más" es de la compilación de la
app o de otra cosa — y aclaró que viene pasando **desde la 1.11**, no solo desde el cambio de
nombre de archivo de hoy.

**Dato clave encontrado al revisar el historial de `talento_app_releases`:** la v1.11 es
**exactamente la primera versión publicada con la URL pública nueva**
(`https://dev.meganett.com.mx/...`) — las versiones 1.4 a 1.10 se publicaron todas con la IP LAN
en plano (`http://192.168.105.11/...`). El "desde la 1.11" de David coincide **exacto** con el
cambio de IP-LAN-HTTP a dominio-público-HTTPS, no con ninguna versión del código de la app en sí.

**Verificado del lado servidor (todo limpio, no es la compilación ni el nginx):**
- Headers de la descarga real vía `dev.meganett.com.mx`: `Content-Length` correcto,
  `Accept-Ranges: bytes`, sin `Transfer-Encoding: chunked` (gzip no toca `.apk` — `gzip_types` de
  nginx no lo incluye).
- Range requests (lo que usan los gestores de descarga de Android para reanudar/verificar) se
  honran correctamente tanto al inicio como al final exacto del archivo (`206 Partial Content`
  con el `Content-Range` correcto).
- Certificado TLS válido y con cadena completa (Let's Encrypt, verifica `return code: 0`).
- La IP pública `38.123.192.199` está **asignada directamente** a la interfaz de red de este
  mismo servidor (no hay router/NAT intermedio en el lado del servidor).
- Descarga real completa simulando velocidad lenta de datos móviles (300 KB/s, ~3m46s) por la
  URL pública real → terminó sin cortes, MD5 idéntico al original.

**Lo que esta batería de pruebas NO puede descartar (limitación estructural de probar desde el
propio servidor):** si el teléfono de prueba está conectado al **mismo WiFi de oficina** que este
servidor, al pedir `dev.meganett.com.mx` su tráfico tiene que salir hacia el router/gateway y
"regresar" hacia el servidor por su IP pública (esto se llama *hairpin NAT* o *NAT loopback*).
Muchos routers domésticos/de oficina manejan esto mal o de forma inconsistente para transferencias
grandes y sostenidas — aunque las peticiones chicas (como cargar una página) sí funcionen. Como mi
prueba corrió **en el propio servidor**, el sistema operativo reconoce que `38.123.192.199` es él
mismo y nunca llega a salir por el router — así que esta prueba, aunque pasó limpia, **no puede
probar ni descartar** un problema de hairpin NAT del router de oficina. Es la única pieza del
rompecabezas que no se puede verificar sin un segundo dispositivo físico en esa misma red.

**Encaja con el síntoma reportado:** arranca bien (la conexión inicial sí llega), avanza bastante
(por eso parece llegar a "100%"), pero la conexión nunca cierra limpio — exactamente lo que pasa
cuando el hairpin NAT de un router falla a medio camino en una transferencia larga.

**Prueba pedida a David (1 minuto, la única que falta y que solo él puede hacer):** intentar la
descarga con el teléfono en **datos móviles** (WiFi apagado), no en el WiFi de la oficina. Si así
sí termina, confirma que es el router de oficina (hairpin NAT) y no la app ni el servidor — la
solución sería configurar el router, o simplemente usar datos móviles / la URL LAN
(`http://192.168.105.11/downloads/...`) cuando se prueba desde la misma red del servidor.

Build/compilación: descartada como causa — el archivo servido es íntegro y el server responde
correctamente bajo todas las condiciones que se pueden probar desde aquí.
