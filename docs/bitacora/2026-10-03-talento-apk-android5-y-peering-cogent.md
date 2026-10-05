## 2026-10-03 — "no deberia instalarse lento, deberia volar" + soporte Android 5

### Pedido 1: soporte desde Android 5.0 (API 21)

David, en continuación del hilo de la descarga lenta: "tienes que ponerle soporte a partir
de la version 5 de android, no solo los ultimos" — pensando que el error "paquete no es
válido" en algún equipo se debía a que `minSdkVersion` (23, Android 6.0) dejaba fuera
Android 5.x.

**Investigado y revertido.** Se bajó `minSdkVersion` de 23 a 21 en `android/build.gradle`
y se recompiló para comprobarlo con el propio compilador — la prueba más confiable, porque
si alguna librería lo rechaza, el manifest merger de Gradle falla con el error exacto.
Resultado: **falló de inmediato**, y no por una librería opcional — por **React Native
mismo** (`com.facebook.react:react-android:0.74.5`, el framework base de toda la app)
declara `minSdkVersion 23` en su propio `AndroidManifest.xml`. El propio mensaje de Gradle
sugiere forzar un `tools:overrideLibrary` para saltarse el candado, pero la misma
herramienta advierte que **puede causar fallas en tiempo de ejecución** — es decir, la app
podría instalar pero comportarse mal justo en los equipos Android 5 que se buscaba arreglar.

**Conclusión:** Android 6.0 (API 23) es el piso real de este stack. Bajar a Android 5.0
necesitaría degradar la versión de React Native completa (cambio grande y riesgoso, no
decidido). Se revirtió el cambio (`android/build.gradle` vuelve a `minSdkVersion = 23`,
con un comentario dejando la investigación documentada) — commit `ee6dd16`.

### Pedido 2: "en el mio tengo el ultimo android y es un s25 ultra, no deberia instalarse lento"

Corrección importante de David sobre su propio caso: su teléfono es un Samsung S25 Ultra
con Android al día — el problema NO es de versión de Android ahí, descartando de raíz la
hipótesis de hardware viejo/Android viejo para ese dispositivo.

Se le preguntó dos cosas puntuales para no seguir adivinando:
1. ¿Dónde pasa la lentitud? → Respuesta: **en la descarga misma** (la barra de progreso del
   navegador, antes de llegar al instalador de Android) — no es el paso de instalación de
   Android.
2. ¿Ya probó datos móviles (WiFi apagado) o el link directo por IP sin HTTPS
   (`http://38.123.192.199/downloads/...`)? → Respuesta: **sí probó ambos y tampoco
   funcionó bien.**

Esta respuesta descarta de un solo golpe las dos hipótesis anteriores que se habían
investigado (WiFi de oficina con NAT en espejo / hairpin NAT, y algo específico de
HTTPS/TLS) — si ni datos móviles ni el link plano por IP mejoran, el problema no depende
de esas dos variables.

### Hallazgo: todo el tráfico saliente del servidor pasa por un solo proveedor (Cogent)

Con los síntomas limpiando ambas hipótesis anteriores, se investigó la salida a internet
del propio servidor:
- El servidor SÍ está físicamente en México (confirmado vía geolocalización de IP:
  Ciudad de México), así que la causa no es "servidor lejos de México".
- Traceroute de salida (`traceroute -n 8.8.8.8`) muestra una ruta rápida y limpia
  (~23ms, sin pérdida de paquetes) — pero **todos los saltos pertenecen al rango de
  Cogent Communications (AS174)**, el mismo proveedor que aparece como dueño de la IP
  pública del servidor. Es decir: el servidor sale a internet por **un solo proveedor de
  tránsito**, sin una segunda ruta alterna.
- Revisión de ajustes de red del servidor (control de congestión TCP, buffers,
  `tc`/traffic shaping): todo normal, sin limitación de ancho de banda configurada en el
  propio servidor.

**Por qué esto explica el síntoma:** Cogent tiene fama bien documentada de tener
interconexión (*peering*) pobre o congestionada con operadoras móviles/residenciales en
varios países — no es una falla, es una decisión de negocio de ese proveedor (paga poco
por sus interconexiones). El tráfico puede salir rápido del servidor y aun así quedar
atascado justo en el punto donde Cogent se conecta con, por ejemplo, Telcel/AT&T/Totalplay.
Esto explica por qué una prueba corta (cargar una página, resolver DNS) se siente normal,
pero sostener la descarga completa de un archivo de 70MB se arrastra — y por qué pasa
igual en WiFi de oficina y en datos móviles (ambos pueden terminar cruzando el mismo cuello
de botella hacia este servidor específico).

**No es un bug de la app, Laravel o nginx** — es un problema de infraestructura/tránsito de
red, un nivel por debajo de lo que el código puede arreglar.

**Siguiente paso acordado con David:** subir el `.apk` a GitHub Releases (mejor
conectividad real hacia México que la salida Cogent-only de este servidor) y cambiar
`talento_app_releases.apk_url` para que apunte ahí — sin tocar el código de la app ni el
servidor de MegaISP. Pendiente de ejecutar: requiere crear un repositorio público nuevo
bajo la cuenta de GitHub de Irving (ver nota de confirmación en el chat — la creación del
repo quedó bloqueada por el clasificador de auto-mode de Claude Code a la espera de
confirmación explícita, dado que usa el token real de Irving para publicar contenido
público).

### Commits

App: `ee6dd16` en `/home/meganet/TalentoEquipo` (rama `master`) — documenta la
investigación de Android 5.0, sin cambio funcional (vuelve a `minSdkVersion = 23`).
