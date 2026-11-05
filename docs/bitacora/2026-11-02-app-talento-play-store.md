## 2026-11-02 — App Talento: compilación lista para Google Play (React Native 0.81, Android 16)

Pedido de David: «prepara la compilación de la app para poder publicarla en la Play Store».

**Por qué no bastaba con compilar la 1.23.** Dos reglas de Google Play la rechazaban:
1. **Librerías nativas en páginas de 16 KB** (obligatorio desde el 1-nov-2025 para apps nuevas y
   actualizaciones). Medido en el APK de la 1.23: 44 de 45 librerías de `arm64-v8a` venían alineadas a
   4 KB (`0x1000`); la de React Native 0.74 viene precompilada así y no hay forma de corregirlo sobre el
   archivo terminado. React Native trae librerías de 16 KB desde la 0.77.
2. **Apuntar a Android 16 (API 36)** desde el 31-ago-2026 (la app estaba en API 34). Con API 36 la app
   va de borde a borde (detrás de la barra de la hora y de la de abajo) y ya no se puede desactivar.

David eligió actualizar en una rama aparte (la alternativa era publicarla como app privada de la
empresa, que exige registrar cada teléfono en un sistema de administración).

**Cambio** (`/home/meganet/TalentoEquipo`, rama `build/play-store` en el worktree
`/home/meganet/TalentoEquipo-play`; no mezclada a `master`; commits `b702b7a` Android y librerías,
`f50e4ac` pantallas, `6ce5899` bundle):
- React Native 0.74.5 → **0.81.6** (React 18 → 19.1), con las librerías nativas que lo acompañan:
  safe-area-context 5.6, screens 4.18, vision-camera 4.7.2, permissions 5.4, webview 13.16. Se conserva
  la arquitectura de siempre (`newArchEnabled=false`) para no cambiar cómo se dibujan las pantallas.
- Android: `compileSdk`/`targetSdk` 36, Gradle 8.14.3, Kotlin 2.1.20, autolinking nuevo, NDK 28.2
  (arma las librerías en 16 KB sin banderas extra; la carpeta del NDK 27.1 del servidor estaba vacía).
  `minSdk` sube de 23 a **24** (Android 7.0): es el piso de React Native desde la 0.76; Android 6.0
  deja de poder instalarla.
- Se quitan los permisos `FOREGROUND_SERVICE` y `FOREGROUND_SERVICE_LOCATION`: venían del esqueleto
  inicial de junio y nada los usa; tenerlos obliga a llenar en Play Console una declaración con video.
- Borde a borde: `SafeAreaProvider` en la raíz; las pantallas de detalle dejan abajo el alto de la barra
  del sistema; login y configuración de servidor bajan el botón de tema y llevan una franja teal detrás
  de la hora; las ventanas emergentes (firma, ubicación manual, firma de documentos, liquidaciones,
  incidencia, reporte de avance, escáner) dejan espacio arriba, abajo y para el teclado
  (`src/hooks/useMargenesModal.js`). Con borde a borde Android ya no encoge la pantalla al abrir el
  teclado: `MainActivity` le deja abajo el alto del teclado, como hacía antes `adjustResize`.
- `npm run build:aab` arma el paquete para Play (`android/app/build/outputs/bundle/release/app-release.aab`).

**Versión.** Sigue siendo 1.23 / versionCode 123 (regla de David: solo lo nuevo sube versión). Firmada
con la misma llave de la 1.23, para que los teléfonos que ya la tienen puedan actualizarse desde Play si
en Play Console se sube esa misma llave como llave de firma de la app.

**Verificado:** compila (`bundleRelease` + `assembleRelease`); las 12 librerías nativas de
`arm64-v8a` y `x86_64` del paquete para Play quedan en 16 KB (`0x4000`) y el APK pasa
`zipalign -c -P 16`; apunta a API 36; el certificado de firma es el mismo de la 1.23 (misma huella
SHA-256); el APK lleva el JavaScript compilado en esta rama. Falta probarla en un teléfono (sobre todo
las pantallas junto a las barras del sistema y el teclado en las ventanas emergentes).

**Para probar / subir** (nombres aparte; la 1.23 que usa el equipo, `meganetV123.apk`, no se tocó):
- APK de prueba: https://dev.meganett.com.mx/downloads/meganetV123-play.apk
  (MD5 `9817e81fa59f957f9bf844311b2ec664`); se instala encima de la 1.23.
- Paquete para Play Console: https://dev.meganett.com.mx/downloads/meganetV123-play.aab
  (MD5 `60df2eb2d703dd61d68a5416049cdece`).

**Al publicar en Play** (no es pendiente de dev, es parte de publicar): cambiar el enlace «Actualizar»
de la versión vigente (`talento_app_releases.apk_url`) a la página de la app en Play; Google no permite
que una app de Play se actualice descargando un APK por fuera.
