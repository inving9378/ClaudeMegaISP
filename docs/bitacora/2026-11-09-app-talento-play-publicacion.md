## 2026-11-09 — App Talento: lo necesario para publicarla en Google Play

Pedido de David: publicar la app 1.26 en Google Play. La cuenta de desarrollador ya existe y es de
organización (sin la prueba cerrada de 12 personas). Decisión: **pública** (cualquiera la encuentra;
sin cuenta del sistema no entra).

### Antes
- La foto de evidencia ya guarda en el teléfono con la 1.26 (fila en `talento_work_order_media` de dev,
  08:37). Arreglo del 9-nov, rama `fix/evidencia-color-marca` de la app.
- Paquete: `public/downloads/meganetV126.aab` (misma firma que los APK, 16 KB, API 36).

### Qué se hizo
- **Guía con los textos para copiar:** `public/reportes/play-store.html` (detrás del candado). Cubre crear
  la app, acceso para el revisor, anuncios, clasificación, público (18+), seguridad de los datos,
  ficha, llave de firma, versión de producción y lo que sigue tras la aprobación.
- **Imágenes** en `public/reportes/play/`: icono 512 con el logo de Meganet, igual al del teléfono. El
  `assets/playstore/icono-512.png` de octubre es otro diseño (`icon-master.svg`) y no coincide con el
  icono de la app. También el gráfico destacado 1024×500 y 5 capturas de 1080×1920, sacadas de las
  pantallas de la app (react-native-web) con datos de ejemplo, sin clientes reales.
- **Cuenta para el revisor de Google, solo en dev:** `revision_play` (rol TECNICO, ficha de Talento
  propia) con 3 órdenes de ejemplo de hoy, sin cliente, 0 puntos y sin cobro. La contraseña solo se
  dio en el chat. Se probó por `https://dev.meganett.com.mx/talento/api` (exento del candado): entra
  y ve sus 3 órdenes. No se usó `demo_tecnico_bajo` porque sus órdenes de prueba apuntan a un cliente
  con datos reales.
- **Aviso de privacidad para la ficha:** la dirección de producción
  (`v1megaisp.meganett.com.mx/legal/privacidad-talento`), porque la de dev pide contraseña desde fuera.
- **Revisado del paquete:** sin ID de publicidad, sin permisos de fotos o archivos, sin ubicación en
  segundo plano ni servicio en primer plano. No hace falta ninguna declaración especial.

### Llave de firma
- Para que los teléfonos con el APK se actualicen desde Play, Play debe firmar con la misma llave
  (alias `talento`). Eso exige la herramienta `pepk.jar` de Google con la llave pública de cifrado
  que da Play Console.
- El modo automático no deja a Claude descargar y correr esa herramienta. Queda un script para que la
  persona lo corra (`llave-play.sh`, en el scratchpad de la sesión): lee las contraseñas de
  `keystore.properties` sin mostrarlas y deja el zip cifrado en `public/reportes/play/`, que se borra
  después de subirlo.
- No se probó que `pepk.jar` acepte las contraseñas por la entrada estándar. Si falla, la alternativa
  es que Google cree su llave; entonces cada teléfono tendría que desinstalar el APK una vez.

### Falta
- Llenar Play Console con la guía, generar el zip de la llave y enviar a revisión.
- Tras la aprobación: que el aviso de «Actualizar» apunte a la página de Play
  (`talento_app_releases.apk_url`, en dev y en producción con bitácora previa).
- Visto: `android/app/release.keystore` tiene permisos 644 (lo puede leer cualquier usuario del
  servidor); su contraseña lo protege, pero conviene dejarlo en 600.
