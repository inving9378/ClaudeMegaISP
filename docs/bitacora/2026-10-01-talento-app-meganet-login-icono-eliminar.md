## 2026-10-01 14:00 — App de Talento: fix de login, renombrada a Meganet, ícono nuevo, botón eliminar colaborador

### 1. Fix del error de login (pendiente desde el 30-sep)

David reportó el día anterior: "ya lo instalé y no está logueándose, da error al conectar a la BD". Sin respuesta sobre la URL exacta usada, se investigó directamente el flujo de conexión de la app.

**Causa raíz encontrada:** `ServerConfigScreen.normalizeUrl()` (TalentoEquipo, repo de la app móvil) asumía **siempre `https://`** cuando el usuario escribía la URL del servidor sin protocolo. Pero el servidor de dev se usa por **IP literal** (`192.168.105.11`), y esa IP no tiene un certificado TLS válido — el certificado real instalado en el servidor es para el dominio `dev.meganett.com.mx`, así que conectar por HTTPS a la IP falla por *mismatch* de certificado. Esto bloqueaba la app **en la primera pantalla** (Configuración del servidor) — nunca llegaba siquiera al login — con el mensaje "No se pudo conectar. Verifica la URL y tu red."

Confirmado con una prueba real de conexión: por HTTP, el health-check del backend responde 200 normalmente; por HTTPS a la misma IP, el handshake TLS se completa pero con un certificado que no corresponde a esa IP — un teléfono real (a diferencia de `curl -k`) rechaza esa conexión por defecto.

**Fix:** si el texto no trae protocolo, se detecta si es una IP literal (con o sin puerto) — en ese caso se asume `http://`; si es un dominio (ej. `talento.miempresa.com`), se sigue asumiendo `https://` como antes (preserva el comportamiento correcto para un futuro servidor real con TLS).

### 2. Nombre de la app → "Meganet"

David: *"cambiale el nombre por meganet que eso de talento esta mal"*. El nombre dinámico que ya se mostraba en pantalla (desde `company_information.company_name`, configurado como "Meganet Telecomunicaciones") ya estaba bien — lo que decía mal era el **nombre estático** de la app: el que aparece bajo el ícono en la pantalla de inicio del teléfono.

- `app.json` → `displayName`: "Talento Meganet" → **"Meganet"**.
- `android/app/src/main/res/values/strings.xml` → `app_name`: "Talento Equipo" → **"Meganet"**.
- Fallback de último recurso en `LoginScreen.js` y en el backend (`TalentoMobileApiController::appBranding()`), por si la llamada de branding falla: "Talento Equipo" → **"Meganet"**.

### 3. Ícono de la app

David: *"ponle un logo que se parezca o que tenga relación con el de la web"*. Se usó el logo oficial real de Meganet, ya configurado en el sistema (`company_information.url_logo` → `storage/logo_meganet/logo-meganet-oficial.png`) — el mismo que ya se muestra dinámicamente en la pantalla de login.

Como es un logo horizontal (wordmark "MegaNet mx" + tagline), se recortó solo la **marca** — el diamante multicolor (rojo/verde/amarillo/azul) con la "M" blanca estilizada — que es cuadrada y reconocible a tamaño pequeño. Se generó con `ffmpeg` (sin herramientas de diseño) sobre el mismo fondo navy (`#0c1830`) que ya traía el ícono adaptativo de la app, en las 5 densidades (mdpi a xxxhdpi), cuadrado y redondo, más el foreground del ícono adaptativo. Verificado legible a 48×48 y sin recortes importantes bajo máscara circular (la forma que usan algunos lanzadores de Android).

### 4. Botón de eliminar colaborador (web) — solo admin y DESARROLLADOR

David: *"agregale un boton a la lista de colaboradores de tarento para eliminar que lo vea el admin y los desarrolladores"*.

El endpoint ya existía (`DELETE /talento/api/colaboradores/{id}`, borrado reversible — el modelo usa soft delete), pero estaba gateado por `talento.manage`, el mismo permiso de crear/editar, que pueden tener más roles además de admin/DESARROLLADOR. Se creó un permiso propio **`talento.colaboradores.delete`**, asignado solo a `super-administrator` y `DESARROLLADOR` (migración, mismo patrón ya usado para otros permisos de alcance restringido), y se reapuntó el endpoint a ese permiso.

En la lista de colaboradores: botón "Eliminar" (papelera) en la columna de acciones, visible solo con ese permiso (`v-hasPermission`, mecanismo estándar del sistema). Modal de confirmación propio (no un `confirm()` del navegador) mostrando el nombre del colaborador y aclarando que es reversible — no borra su historial (órdenes, liquidaciones, documentos).

Verificado en base de datos: `super-administrator` y `DESARROLLADOR` tienen el permiso; un rol de prueba sin relación (`client`) no lo tiene.

### Verificación del APK

`./gradlew assembleDebug` → `BUILD SUCCESSFUL`. Confirmado con `aapt dump badging`:
```
package: name='com.meganet.talento' versionCode='107' versionName='1.7'
application-label:'Meganet'
```

APK publicado: `http://192.168.105.11/downloads/meganet-v1.7-prueba.apk` (confirmado HTTP 200). Se usó un nombre de archivo distinto al `talento-v1.7.apk` anterior (del 5 de junio, una app completamente distinta — la original de flujo de campo) para no generar confusión, aunque ambas comparten el mismo paquete Android (`com.meganet.talento`) y número de versión por coincidencia.

### Commits

- MegaISP: `7e3060f0` (fallback de nombre) + `9ea63831` (botón eliminar) → mergeados a `main` vía `80226f14`. Migración `2026_10_01_131000_grant_talento_colaboradores_delete_to_admin_y_desarrollador` corrida en dev.
- TalentoEquipo (repo local, sin remote): `81f90dd` (fix de login + nombre + ícono), un solo commit con los 15 archivos.

### Pendiente

Falta que David instale el APK nuevo y confirme que el login ya funciona — el fix se verificó a nivel de red/certificado y de lectura de código, pero no hay forma de confirmar el flujo completo sin que alguien lo pruebe en un teléfono real conectado a la red de MegaISP.

## 2026-10-01 15:00 — Segunda vuelta: "sigue dando el mismo error" + salida de emergencia

David instaló el APK con el fix de IP→http y reportó el mismo error, pero el texto exacto que compartió
("No se puede conectar al servidor. Verifica tu red Wifi") corresponde al mensaje de la pantalla de
**login**, no al de "Configuración del servidor" — es decir, ya pasó la pantalla que arreglé (el health
check contra la URL guardada funcionó), y ahora el login específicamente falla por conexión.

**Hallazgo real:** instalar/actualizar un APK sobre uno ya instalado **no borra el almacenamiento de la
app** (AsyncStorage) — si el teléfono de David ya tenía guardada la URL vieja y mala
(`https://192.168.105.11`, de antes del primer fix), esa URL sigue ahí después de instalar el APK nuevo,
porque la pantalla de configuración del servidor se salta automáticamente cuando ya hay una URL
guardada. Y "Cambiar servidor" (la función que limpia esa URL) **solo vivía dentro del menú de Mi día**,
que exige haber iniciado sesión — círculo cerrado: para corregir la URL hace falta loguearse, pero no se
puede loguear mientras la URL esté mal.

**Fix:** mismo botón "Cambiar servidor" ahora también visible en la pantalla de login, debajo de
"Iniciar sesión" — no depende de tener sesión activa. Verificado con bundle de producción de Metro, sin
errores de sintaxis/imports. APK recompilado y republicado en la misma URL.

**Instrucciones para David:** instalar este APK más reciente y, si sigue viendo el error de conexión al
abrir la app, tocar **"Cambiar servidor"** (debajo del botón de Iniciar sesión) y volver a escribir
`192.168.105.11` — con eso se vuelve a correr el fix de detección de IP/protocolo desde cero. Si eso no
resuelve, lo más seguro es desinstalar la app por completo (no solo sobrescribir el APK) antes de
reinstalar, para partir sin ningún dato viejo guardado.

**Limitación de esta sesión, dicha explícitamente:** no hay forma de instalar ni ejecutar la app en un
dispositivo real ni en un emulador desde este entorno — todo lo de arriba está verificado a nivel de
código (lectura directa, bundle de Metro, compilación de Android) y de red (curl contra el servidor real
por HTTP y HTTPS), pero no hay una prueba end-to-end real del flujo de login en un teléfono. Si el error
persiste tras lo anterior, hace falta un dato que solo se puede obtener probando en el teléfono mismo
(ej. un mensaje de error más específico, o confirmar en qué pantalla exacta se queda).

### Commit adicional

TalentoEquipo: `1ef2191` (botón "Cambiar servidor" en login). APK republicado en la misma URL:
`http://192.168.105.11/downloads/meganet-v1.7-prueba.apk`.

## 2026-10-01 16:00 — Causa raíz real: el bundle de JS llevaba desde junio sin regenerarse

David volvió a reportar el mismo error, y señaló algo clave: la app mostraba **"v1.6"** abajo,
cuando el código fuente ya decía `APP_VERSION = '1.7'` desde antes de esta sesión. Esa pista llevó
a la causa real, distinta de todo lo investigado hasta ahora.

### Qué estaba pasando en realidad

`android/app/src/main/assets/index.android.bundle` — el JavaScript que de verdad corre dentro de
un APK standalone (sin Metro conectado) — estaba commiteado **una sola vez**, el 5 de junio
(commit `edcd78b`), y **nunca se había vuelto a regenerar**. `gradlew assembleDebug` **no
regenera este archivo por sí solo**: el plugin de Gradle de React Native solo re-empaqueta JS
automáticamente para builds de *release*; en *debug* asume que vas a conectar Metro en vivo desde
una computadora de desarrollo. Como este proyecto distribuye APKs de prueba para instalar
directamente en el teléfono (sin Metro), alguien tenía que correr `react-native bundle` **a mano**
antes de cada build — y ese paso nunca se volvió a hacer desde junio.

**Consecuencia real:** cada "APK de prueba" entregado hoy — incluidos los dos anteriores de esta
misma sesión — empaquetaba fielmente los cambios **nativos** (ícono nuevo, nombre "Meganet", en
`AndroidManifest`/`strings.xml`/recursos, que SÍ se compilan frescos cada vez), pero el
**JavaScript seguía siendo el de junio**. De ahí que ninguno de los cambios de hoy (fix de login,
botón "Cambiar servidor", y de hecho ninguna de las 15 secciones nuevas de Talento de la sesión
anterior) apareciera nunca en los APK entregados — y de ahí el "v1.6" que David vio, que en
realidad reflejaba con precisión el bundle congelado, no un error de los fixes en sí.

### Fix

1. **Inmediato:** se regeneró el bundle con el código actual
   (`react-native bundle --platform android --dev false ...`, con los mismos stubs temporales de
   `rn-fetch-blob`/`react-native-reanimated` ya usados antes en la sesión — borrados después de
   generar el bundle, nunca commiteados). Verificado **sin confiar en que el proceso "debió"
   funcionar**: se extrajo el `.bundle` de dentro del `.apk` ya compilado y se confirmó
   textualmente que "Cambiar servidor" (2 veces) y "1.7" están presentes — y se comparó el MD5 del
   archivo en `build/`, del archivo servido en `public/downloads/` y de una **descarga real por
   HTTP** (los 3 coinciden exactamente: `cff2a3015586fe8e50b44b922e40790e`).
2. **Estructural, para que no se repita:** nuevo script `bundle:android` en `package.json`:
   `build:apk` y `build:apk-debug` ahora **siempre** regeneran el bundle antes de correr
   `gradlew` — ya no depende de que alguien recuerde el paso manual.

### Commit

TalentoEquipo: `fd78293` (bundle regenerado + fix estructural de los scripts de build). APK
reconstruido y republicado en la misma URL:
`http://192.168.105.11/downloads/meganet-v1.7-prueba.apk` (verificado con descarga real, no solo
con el resultado del build).
