## 2026-09-17 08:xx — Incidente: sitio completo caído (500) por permisos de `.env` — causa raíz y fix

**Síntoma reportado por Irving:** `http://38.123.192.199:3032/talento/compensacion` daba 500,
y poco después también `http://38.123.192.199:3032/talento/ordenes` y de hecho **el sitio
entero** (confirmado: hasta `/login` daba 500).

**Causa raíz:** para probar en vivo la notificación de WhatsApp al crear una OT (a pedido
explícito de Irving: "prende WHATSAPP_SENDER_ENABLED en el .env de dev para hacer la prueba y
despues lo pones en false otra vez"), se editó `/var/www/megaisp/.env` dos veces (agregar la
línea, luego quitarla) usando la herramienta de edición de archivos. Esa edición **reescribió el
archivo y le cambió el GRUPO de `meganet:www-data` a `meganet:meganet`**, mismo patrón que el
"hardening a 640" de `.env` del 27-ago (ver memoria `permisos_repo_world_writable`) —
`www-data` (el usuario bajo el que corre PHP-FPM, el proceso que sirve las páginas web) dejó de
tener NINGÚN permiso sobre el archivo (no es owner, no está en el grupo).

**Efecto:** Laravel arranca vía `public/index.php` → `LoadEnvironmentVariables` intenta leer
`.env` y, sin permiso de lectura, Dotenv no carga NADA — todo `env()` cae a su default
hardcodeado. `APP_ENV` cayó a `production` (el fallback de Laravel cuando no hay valor real) y
`APP_KEY` quedó vacío → `MissingAppKeyException` en el boot del `EncryptionServiceProvider`,
**antes de que la sesión/CSRF/cualquier ruta pudiera resolver** → 500 en absolutamente todo,
incluido `/login`. `APP_DEBUG` TAMBIÉN cayó a su default (`false`), así que ni siquiera se veía
el detalle del error — solo la página genérica "500 Server Error" (la segunda mitad del pedido
de Irving, "si esto es test debería estar activo el debug", ya estaba correcta en el `.env`
real — `APP_DEBUG=true` — pero PHP-FPM no podía leer ESE valor tampoco, por el mismo motivo).

**Por qué solo se notó en web y no en CLI:** `php artisan tinker` y todo comando por consola
corren como el usuario `meganet` (dueño del archivo, `rw-`) → siguieron leyendo `.env`
perfecto durante todo el incidente. Por eso decenas de comandos `tinker` funcionaron sin
problema mientras el sitio completo estaba caído — la investigación por CLI no tenía forma de
detectar el problema por sí sola.

**Diagnóstico:** `storage/logs/laravel-2026-09-17.log` mostraba
`production.ERROR: No application encryption key has been specified.` en cada request — la
pista definitiva de que `.env` no se estaba leyendo del todo (no un bug de código). `ls -la
.env` confirmó `-rw-r----- meganet meganet` con mtime EXACTO al segundo del último `Edit` sobre
el archivo. `id www-data` confirmó que `www-data` no pertenece al grupo `meganet`.

**Fix:** `chgrp www-data .env` (el usuario `meganet` SÍ pertenece al grupo `www-data`, así que
no hizo falta root) + `chmod 640` de refuerzo. Verificado: `/login` volvió a 200 de inmediato.
Se reinició el pool de `queue:work` (corre como `www-data`, pudo haber arrancado durante la
ventana rota) para que las 2 instancias tomaran un `.env` sano desde cero. Ventana total del
incidente: **~08:33 a ~08:48** (≈15 min), en dev únicamente.

**Regla para sesiones futuras (guardada en memoria):** cualquier edición de
`/var/www/megaisp/.env` con una herramienta de archivos (no `sed -i`/sudo directo) puede
resetear su grupo a `meganet:meganet` — verificar SIEMPRE `ls -la .env` después de tocarlo y
`chgrp www-data .env` si el grupo cambió, antes de dar la edición por terminada. Más seguro
todavía: usar `sed -i` sobre el archivo existente (edición in-place, no reescribe/renombra) en
vez de una herramienta que reconstruye el archivo desde cero.

**Nota separada:** durante la misma ventana se probó `WHATSAPP_SENDER_ENABLED=true` con destino
real `+13054576208` (a pedido explícito de Irving) — el mensaje se creó y encoló bien, pero el
envío real falló porque la instancia de WhatsApp (`meganet-ventas`) está desconectada de verdad
(`state:"close"` en Evolution) — requiere que alguien escanee el QR con un teléfono real. El
flag se revirtió a como estaba (ausente del `.env`) apenas terminó la prueba, según lo pedido.
