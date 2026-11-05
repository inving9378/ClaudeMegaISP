## 2026-10-30 — Pendientes de Talento y de la app (logo, cursos, aviso de versión, permisos, Google)

Pedido de David: «soluciona los pendientes». Lo que se resolvió, lo que quedó listo para correr con sudo
y lo que necesita a otra persona.

**Resuelto**
- **Logo de la pantalla de entrada desde fuera.** `/talento/api/app/branding` daba
  `/storage/logo_meganet/…`, que fuera de la oficina pide la contraseña de nginx. Ahora da
  `/talento/api/app/branding/logo?v=<fecha del archivo>` (`TalentoMobileApiController::appBrandingLogo`):
  el logo de la empresa, sin datos de nadie, solo imágenes de `public/` o del disco público. Sin cambio en
  la app. Verificado por el dominio y por la IP: 200 image/png.
- **Imágenes de referencia de los cursos desde fuera.** El detalle del curso de la app agrega
  `reference_image_url` con enlace firmado de 1 hora a `/talento/api/curso-imagen-firmada/{tipo}/{id}`,
  como la foto de la credencial; la app lo usa y, con un servidor anterior, sigue usando `/storage`.
  Probado: con firma alterada, cambiando la imagen o sin firma → 403; con un archivo temporal en el
  servidor (dentro de una transacción deshecha, archivo borrado) → 200 image/png.
- **Aviso de versión nueva.** En `talento_app_releases` la última era la 1.7 (junio, por IP): ningún
  teléfono veía aviso de la 1.8 en adelante. Se dio de alta la 1.23 (id 5,
  https://dev.meganett.com.mx/downloads/meganetV123.apk, no obligatoria) y se desactivó la 1.7.
  `/talento/api/app/latest`: 1.7 y 1.22 → hay actualización; 1.23 → no. El QR de descarga de
  «Dispositivos» también da ya la 1.23. Con esto los teléfonos de la 1.20 o anterior se actualizan solos
  y ya no hacen falta las excepciones de nginx del gafete para esas versiones.
- **App 1.23 recompilada** (sin subir versión) con lo de los cursos: `/home/meganet/TalentoEquipo` `39f11c5`
  + bundle `2f7c5a0`, en `master`. APK reemplazado (MD5 `01bab388692b87c4c4115ab52a20168b`, misma firma,
  verificado por el dominio y la IP). Servidor: merge `cdbf4911b`.

**Listo para correr con sudo (nginx de dev)**
`deploy/nginx/dev-acceso/server-excepciones.conf`: `/downloads/` con `expires epoch` (el navegador del
teléfono pregunta siempre si el APK cambió; si no cambió, no lo vuelve a bajar). Se quitaron del archivo
las excepciones `/credencial/` y las dos imágenes del gafete: eran para la app 1.20 o anterior y nunca se
instalaron, así que el archivo queda igual a lo instalado más esa línea:

    sudo cp /var/www/megaisp/deploy/nginx/dev-acceso/server-excepciones.conf /etc/nginx/snippets/megaisp-dev-excepciones.conf
    sudo nginx -t && sudo systemctl reload nginx
    curl -sI https://dev.meganett.com.mx/downloads/meganetV123.apk | grep -i cache-control   # → no-cache

**Hallazgos**
- **Las 10 imágenes de referencia de los tipos de penalización no existen en el servidor** (subidas el
  9-sep, `talento/penalty-types/…`; no están en `storage/app/public` ni en ningún otro lado del disco).
  No cargan ni en la web ni en la oficina: hay que volver a subirlas en Talento → Penalizaciones.
- **Permisos del Mostrador (28→29 oct).** El rol no cambió de fecha, `activity_log` no tiene nada de roles
  y ninguna sesión de Claude de esa noche tocó permisos. El historial de peticiones (Debugbar) empieza el
  29-oct 15:06, después. Lo único que borra varios permisos de un rol desde la web es guardar en
  Administración → Roles (`/administracion/permisos/update-permission-for-role/5`): el servidor quita todo
  lo que no venga marcado, y los permisos de Talento están todos en la pestaña «Otros», que se carga
  aparte. Si se guarda con esa pestaña sin cargar o con esas casillas desmarcadas, se van todos. Es lo
  más probable, sin prueba. Para saber quién (la oficina y el usuario no salen, solo la IP y la hora):

      sudo zgrep -h "update-permission-for-role/5 " /var/log/nginx/access.log.2.gz /var/log/nginx/access.log.1

  Esa noche se abrió la terminal web con la cuenta genérica `admin` (21:52 y 22:08, desde
  192.168.100.118). Blindar ese guardado es código de permisos: decisión de Irving.
- **Búsqueda de direcciones de Google:** la llave de dev responde `REQUEST_DENIED — This API is not
  activated`: hay que activar «Geocoding API» en la consola de Google del proyecto de esa llave. Mientras,
  el respaldo (buscador de los mapas) sí encuentra direcciones.

**Necesitan a otra persona** (sin tocar): qué hacer si los descuentos superan el pago y desde qué semana
se cobra un préstamo (Irving, es dinero); qué documentos firma el Mostrador (Irving o RH); subir a
producción los cambios de Talento del 27 al 30 (confirmar antes); borrar el préstamo de prueba (cuando
David lo diga).
