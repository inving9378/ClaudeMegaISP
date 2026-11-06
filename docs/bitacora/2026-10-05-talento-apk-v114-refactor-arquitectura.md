## 2026-10-05 — v1.14 (114): primer build publicado tras el refactor de arquitectura

Tras los 4 commits de refactor de arquitectura de esta misma sesión
(`useAsyncResource`, mapas de estrategia para los if-chains de kind/tipo,
división de `api/client.js` por dominio, normalización de `getOT()`), se
armó y publicó la primera versión que empaqueta ese código.

**Limitación honesta:** este servidor no tiene ningún teléfono ni emulador
Android conectado (`adb devices` sin resultados, sin AVD creado, y sin
aceleración de hardware KVM para correr uno a velocidad usable) — no se pudo
probar tocando una pantalla real. Lo que SÍ se verificó, automatizado:

- Build (`gradlew assembleDebug`) exitoso, APK de 140,677,100 bytes.
- El bundle empaquetado contiene el código del refactor (`unwrapOt`, mensajes
  de `OTDetalleScreen`) y la versión correcta (1.14) — confirmado extrayendo
  el bundle de dentro del `.apk` real y grepeando su contenido.
- MD5 idéntico en 3 puntos: build, copia pública (`meganetV114.apk`),
  descarga real desde `dev.meganett.com.mx`.
- `talento_app_releases` actualizado (fila activa = 114); `GET
  /talento/api/app/latest` confirma `update_available` desde 113 → 114 con
  la URL correcta.
- `GET /talento/api/app/download-qr` (con token Sanctum real) confirma que
  el QR sigue apuntando a `/app/descargar` (la página de detección de
  dispositivo del item anterior), y que esa página con User-Agent Android
  redirige correctamente al `.apk` nuevo.
- Archivo anterior (`meganetV113.apk`) borrado tras confirmar que el nuevo
  link funciona.

**Pendiente real:** validación visual de Irving/David en un teléfono de
verdad — que la app instale y funcione igual que antes (el refactor no debía
cambiar nada visible, pero solo una prueba real en pantalla lo confirma).

**Commit:** `d276d0a` en `/home/meganet/TalentoEquipo` (rama `master`).
