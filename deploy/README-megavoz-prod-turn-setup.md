# MegaVoz en producción — servidor TURN + dialplan del mini-teléfono

> Release **V1.42-25.09.2026**. El código (mini-teléfono WebRTC, fix de conexión de
> audio, restricción de permisos) ya vive en `main` y llega a producción con la
> actualización normal ("Buscar actualizaciones"). Esta guía es la parte que **CC no
> puede hacer sola** — dev y prod no se alcanzan entre sí (frontera dura, ver
> CLAUDE.md §"ENTORNOS") — así que Irving/David la ejecutan directo en el servidor de
> producción. Sigue el mismo patrón que `README-cobranza-blaster-prod.md`.

## 0. Prerrequisito

Asterisk ya debe estar operativo en prod con AMI/ARI/PJSIP Realtime funcionando (es lo
mismo que ya usa CobranzaBlaster). Si CobranzaBlaster ya hace llamadas reales en ese
servidor, este prerrequisito ya está cumplido — MegaVoz se apoya en el mismo Asterisk,
no instala uno nuevo.

## 1. Servidor TURN (coturn)

Sin esto, las llamadas solo conectan entre navegadores en la misma red local — con
redes distintas (celular por datos, oficinas distintas, etc.) el audio nunca se arma.

```bash
sudo apt install coturn -y
sudo systemctl enable coturn
```

Generar una contraseña propia para prod (**nunca reusar la de dev**):

```bash
openssl rand -base64 24
```

Crear `/etc/turnserver.conf` (sustituir `<IP_PUBLICA_PROD>`, `<DOMINIO_PROD>` y
`<PASSWORD_GENERADA>` por los datos reales del servidor):

```
listening-port=3478
tls-listening-port=5349
listening-ip=<IP_PUBLICA_PROD>

fingerprint
lt-cred-mech
realm=<DOMINIO_PROD>
user=megavoz:<PASSWORD_GENERADA>

min-port=49152
max-port=65535

cert=/etc/letsencrypt/live/<DOMINIO_PROD>/fullchain.pem
pkey=/etc/letsencrypt/live/<DOMINIO_PROD>/privkey.pem

no-cli
no-tlsv1
no-tlsv1_1
```

⚠️ **`lt-cred-mech` y `realm=` son obligatorios** — sin ellos coturn responde
`401 Unauthorized` a TODO, sin aviso claro. (Así se rompió la primera vez en dev: un
paste interrumpido dejó el archivo incompleto.)

```bash
sudo systemctl restart coturn
sudo systemctl status coturn   # debe verse "active (running)"
```

Verificar sin necesitar navegador (coturn trae su propio cliente de prueba):

```bash
turnutils_uclient -u megavoz -w '<PASSWORD_GENERADA>' <IP_PUBLICA_PROD>
```

Si autentica y arma la sesión, el servidor está listo.

### Firewall

Abrir en el firewall del servidor (si tiene uno activo — dev no tenía ninguno, prod
puede ser distinto):

- **3478/UDP y 3478/TCP** — señalización TURN/STUN
- **49152-65535/UDP** — rango de relay (el tráfico de audio en sí)
- **5349/TCP** — opcional, solo si se quiere TURN sobre TLS

## 2. `.env` de producción

Agregar (mismo patrón que las demás variables VoIP ya presentes en `config/voip.php`):

```
MEGAVOZ_TURN_URL=turn:<IP_PUBLICA_PROD>:3478
MEGAVOZ_TURN_USERNAME=megavoz
MEGAVOZ_TURN_PASSWORD=<PASSWORD_GENERADA>
```

Luego el warm-up de siempre (nunca `config:cache` suelto — ver CLAUDE.md):

```bash
php artisan config:clear && php artisan route:clear && php artisan queue:restart
```

## 3. Dialplan — extensión gemela WebRTC

`/etc/asterisk/extensions.conf` **no se despliega solo** (vive fuera de git a
propósito — ver el encabezado de
`app/Modules/Addons/VoIP/provisioning/extensions.conf.referencia`, que es la copia de
referencia versionada). Si prod ya tiene ese archivo de una instalación previa de
VoIP, confirmar que ya incluye este bloque; si no, agregarlo a mano:

```
; MegaVoz — gemela WebRTC de una extensión (webNNNN)
exten = _web[1-9]XXX,1,NoOp(Interno (WebRTC): ${CALLERID(num)} -> ${EXTEN})
 same = n,Set(CDR(userfield)=interno)
 same = n,Dial(PJSIP/${EXTEN},30)
 same = n,Hangup()
```

Ver el archivo `.referencia` completo para el contexto `[from-internal]` alrededor de
este bloque (viene con el `[1-9]XXX`/`[1-9]XX` normales al lado). Tras editar:

```bash
sudo asterisk -rx "dialplan reload"
```

## 4. Instalar/activar el addon VoIP

Si en prod el módulo VoIP nunca se instaló: entrar como super-administrator/
DESARROLLADOR a **Administración → Módulos** (`/admin/modules`) y darle "Instalar" a
VoIP. Si ya estaba instalado de antes (troncal/extensiones ya en uso), la actualización
normal ("Buscar actualizaciones") ya trae el código nuevo — no hace falta reinstalar,
solo confirmar que el módulo siga `activo`.

La restricción de visibilidad (solo super-administrator/DESARROLLADOR ven troncales/
extensiones/grupos/bot IA) llega sola con la migración del deploy — sin paso manual.

## 5. Verificar

1. Con un usuario que tenga extensión asignada (no necesariamente admin): entrar a
   **Mi Teléfono** y confirmar que aparece el mini-teléfono.
2. Hacer una llamada interna de prueba entre dos navegadores en redes DISTINTAS
   (ej. uno por WiFi de oficina, otro por datos móviles) — sin esto en la misma red
   local el TURN nunca se ejercita y una falla real pasaría desapercibida.
3. Confirmar audio en ambos sentidos y que conecta en pocos segundos.
4. Con un usuario que NO sea super-administrator/DESARROLLADOR: confirmar que
   `/voip/troncales` y `/voip/extensiones` NO son accesibles (403 o sin verlos en el
   menú), pero que el mini-teléfono sigue funcionando.
