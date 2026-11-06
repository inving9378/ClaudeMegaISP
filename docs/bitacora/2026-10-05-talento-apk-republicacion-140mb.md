## 2026-10-05 — Republicación en la misma ruta con las 4 arquitecturas (prueba de MD5)

Continuación de la investigación de la descarga lenta (ver `2026-10-02-talento-apk-nunca-termina-descargar.md`
y `2026-10-03-talento-apk-android5-y-peering-cogent.md`). Sin acceso a GitHub disponible para mover
la distribución fuera del servidor (intentado: creación de repo nueva bloqueada por permisos del
token; push a una rama aislada del repo existente bloqueado por el clasificador de auto-mode de
Claude Code — ninguna de las dos pudo ejecutarse), se decidió una prueba más simple primero: revertir
el recorte de arquitecturas (`ndk.abiFilters`) aplicado el 2-oct y republicar en la **misma URL**
(`https://dev.meganett.com.mx/downloads/meganetV113.apk`) para ver si el cambio de contenido/MD5 en
sí tiene algún efecto, descartando el tamaño como variable (ya se había descartado antes, pero no
se había probado revertirlo de verdad).

**Aplicado:**
- `android/app/build.gradle` — quitado el bloque `ndk.abiFilters`, vuelve a empaquetar las 4
  arquitecturas nativas (arm64-v8a, armeabi-v7a, x86, x86_64). Commit `037e62b` en
  `/home/meganet/TalentoEquipo`.
- Recompilado (`gradlew clean` + `assembleDebug`, necesario por el cambio de config nativa).
- Resultado: **140,676,363 bytes** (antes 69,847,947), MD5 `b0f7de1b5b3fd95c41709e4fb746c6ed` (nuevo).
- Publicado sobrescribiendo el mismo archivo en `public/downloads/meganetV113.apk` — **la URL no
  cambió**. Verificado MD5 idéntico en 3 puntos (build, copia pública, descarga real desde
  `dev.meganett.com.mx`). `talento_app_releases.file_size` actualizado a 140,676,363; `apk_url`
  intacto.

**Nota honesta:** el hallazgo del día anterior (toda la salida del servidor pasa por un único
proveedor de tránsito, Cogent, con peering débil hacia operadoras móviles/residenciales) sigue sin
tocarse — nada de esto cambia la ruta de red real entre el teléfono y el servidor. Esta prueba es
exactamente eso, una prueba, para confirmar o descartar si el MD5/contenido del archivo influye,
antes de insistir en la hipótesis de infraestructura de red.

**Pendiente:** validación real — intentar la descarga de nuevo y confirmar si esta vez termina.

### Corrección (misma sesión) — confirmado que SÍ descargó bien

La prueba real confirmó que el archivo de 140 MB (más pesado que el de 67 MB que fallaba)
descargó completo por la **misma** ruta de red, mismo servidor, mismo dominio. Esto **contradice**
la hipótesis de peering de Cogent del día anterior — un cuello de botella real de tránsito de red
no debería mejorar con un archivo más grande. Teoría más probable ahora: algo en el camino (un
proxy transparente de la operadora móvil, o caché del propio teléfono) tenía guardada una
respuesta dañada/parcial para esa URL exacta, y el cambio de contenido (ETag/Last-Modified nuevos)
la invalidó — no el tamaño. **Se descarta la hipótesis de infraestructura de red del servidor**
como causa raíz confirmada; queda como hipótesis descartada, no como hallazgo firme.
