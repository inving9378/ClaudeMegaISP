# Desplegar y validar CobranzaBlaster en producción — guía operativa

> Item roadmap **#67**. El código del blaster (listener AMI, correlación por ChannelId,
> `ProcessCallResultJob`, troncal Servnet vía PJSIP Realtime) ya está resuelto y vive en
> `main` — ver `docs/modulos/cobranzablaster.md`. Esta guía es el **runbook** que preparó CC
> para que **Irving ejecute el despliegue directamente en el servidor de producción**
> (decisión explícita de Irving en el item #67, opción recomendada: *"CC solo prepara
> artefactos en dev y documento"* — CC no toca prod, no tiene acceso a Servnet ni a
> credenciales de producción).
>
> Alcance de la primera llamada real: **a un número interno/propio de Irving**, nunca a un
> cliente moroso (decisión de Irving en el item, opción recomendada de la pregunta
> "¿con qué alcance hacer la primera llamada real?"). Para eso existe el paso 4 con el
> comando `cobranza:llamada-prueba`, que nunca toca datos de morosos reales.

## 0. Antes de empezar

En el `.env` de producción deben existir (si falta alguno, el blaster no puede originar
llamadas — ver `config/voip.php` y `config/cobranza.php`):

```
AMI_HOST=127.0.0.1
AMI_PORT=5038
AMI_USERNAME=megaisp
AMI_SECRET=<secreto AMI real del servidor>
AMI_CONTEXT=cobranza-blaster
ASTERISK_ARI_HOST=127.0.0.1
ASTERISK_ARI_PORT=8088
ASTERISK_ARI_USER=medussa
ASTERISK_ARI_PASS=<secreto ARI real del servidor>
BLASTER_TTS_VOICE=nova           # opcional, default nova
```

La API key de OpenAI (TTS) se resuelve por el Hub de integraciones (`/integraciones`,
provider `openai`) con fallback a `OPENAI_API_KEY` en `.env` — no hace falta una key nueva
si el Hub ya tiene una activa.

## 1. Listener AMI (daemon bajo Supervisor)

```bash
sudo cp /var/www/megaisp/app/Modules/Addons/CobranzaBlaster/stubs/supervisor-ami-listener.conf \
        /etc/supervisor/conf.d/megaisp-cobranza-ami.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start megaisp-cobranza-ami
sudo supervisorctl status megaisp-cobranza-ami   # debe verse RUNNING
```

Es el reemplazo del "webhook AMI" (Asterisk no hace HTTP POST por sí solo): mantiene un
socket abierto al AMI, filtra los eventos de sus propias llamadas (`uniqueid` `cob-*`) y
despacha `ProcessCallResultJob`. Sin este daemon corriendo, las llamadas se originan pero
**su estado nunca se actualiza** en `cobranza_llamadas`.

## 2. Dialplan del blaster

```bash
# Incluir el contenido de este stub dentro de /etc/asterisk/extensions.conf
# (o un #include si tu extensions.conf ya está modularizado):
cat /var/www/megaisp/app/Modules/Addons/CobranzaBlaster/stubs/extensions-cobranza-blaster.conf

sudo asterisk -rx "dialplan reload"
sudo asterisk -rx "dialplan show cobranza-blaster"   # debe listar la extensión 's'
```

El contexto `[cobranza-blaster]` (`AMI_CONTEXT` del paso 0) es donde el AMI Originate
entra a contestar, reproducir el audio TTS y colgar. Nota interna en el propio stub: la
opción "presione 1 para hablar con un asesor" está **deliberadamente** apuntada a un mensaje
temporal (roadmap #190, decisión de Irving) — no falla, pero tampoco transfiere a nadie
todavía.

## 3. Troncal Servnet — configuración vía UI (ya NO requiere sudoers)

⚠️ **Actualización sobre la descripción original del item #67:** el paso "sudoers www-data
para `asterisk -rx sip reload`/`sip show peers`" describía la arquitectura **vieja**
(chan_sip + `sip.conf` escrito a mano). Eso ya se reemplazó (item #185/C4,
`VoipConfiguracionController` + `App\Modules\Core\Voice\VoiceGateway`): la troncal se
provisiona por **PJSIP Realtime** vía AMI/ARI (llamadas de red, no `exec`/`shell_exec`) y el
canal de originate es `PJSIP/servnet/<telefono>`. **No hay ningún `exec()`/`shell_exec()`
en `VoiceGateway` ni en `AmiConnectionService`** — se verificó por grep sobre el código
actual. Este paso queda documentado por si el sudoers ya existía de antes, pero **no es un
prerrequisito** para que el blaster funcione hoy.

Pasos reales:
1. Entrar a `/cobranza/voip` (permiso `cobranza.configure`).
2. Llenar host/puerto/usuario/secret SIP de Servnet + `callerid_nombre`/`callerid_numero`
   (⚠️ **obligatorio**: sin CallerID configurado, `AmiConnectionService::originate()` **aborta
   la llamada a propósito**, #277 — nunca sale con un CallerID placeholder).
3. Guardar → provisiona el endpoint PJSIP Realtime y hace `reloadPjsip()`.
4. Botón "Probar conexión" (`/cobranza/voip/test`) → debe reportar el endpoint registrado.

Si "Probar conexión" no reporta registrado: revisar credenciales Servnet y que el puerto
SIP/RTP esté abierto en el firewall del servidor hacia Servnet (no hay paso adicional de
sudoers que resuelva esto).

## 4. Prueba de llamada real — SOLO a un número controlado

**Nunca** actives una campaña real para esta prueba. Usa el comando dedicado (item #67),
que origina una única llamada por el mismo camino que usa el blaster en producción, pero
con una campaña de prueba que **siempre queda en `borrador`** (el cron
`cobranza:blast-activas` solo toca campañas `activa`, así que nunca la recoge ni la
duplica):

```bash
cd /var/www/megaisp
php artisan cobranza:llamada-prueba 55XXXXXXXX --nombre="Prueba Irving"
```

Reemplaza `55XXXXXXXX` por el número **propio de Irving** (celular o extensión Meganet) —
decisión explícita del item: la primera llamada real NO es a un cliente. El comando:
- Verifica que la troncal esté `activa` y con CallerID configurado (si no, avisa y no marca).
- Genera un audio TTS de prueba explícito ("Esta es una llamada de prueba…", no un guion de
  cobranza real).
- Origina la llamada por AMI y deja registro en `cobranza_llamadas` +
  `cobranza_llamada_eventos`, igual que una llamada real de campaña.

En paralelo, para ver la correlación de eventos en vivo:
```bash
sudo supervisorctl tail -f megaisp-cobranza-ami
```

## 5. Criterios de éxito (antes de dar el despliegue por validado)

Decisión de Irving en el item #67 (opción recomendada, criterios mínimos):
- [ ] Registro SIP/PJSIP con Servnet OK (`/cobranza/voip` → "Probar conexión" → registrado).
- [ ] La llamada de prueba del paso 4 timbra y el audio se escucha claro y bidireccional
      (silencio o corte = revisar `Wait(1)`/CODECs `ulaw`/`alaw` en el dialplan).
- [ ] El CallerID que llega al teléfono de Irving coincide con `callerid_numero` configurado.
- [ ] La llamada queda registrada en BD: `cobranza_llamadas.estado` avanzó de `marcando` a
      `contestada`/`no_contesto` (no se quedó pegada) y hay filas en
      `cobranza_llamada_eventos` (Originate + al menos un evento más) — esto confirma que
      el listener (paso 1) está vivo y correlacionando.
- [ ] El costo de la llamada en el panel de Servnet coincide con lo esperado (tarifación).

## 6. Rollback

Decisión de Irving en el item #67 (mismo criterio mínimo):
```bash
# Apagar el daemon (deja de procesar eventos AMI; las campañas activas quedan sin
# actualizar su estado, pero no se originan llamadas nuevas si además pausas cualquier
# campaña 'activa' desde /cobranza/campanas):
sudo supervisorctl stop megaisp-cobranza-ami

# Revertir el dialplan si hace falta (quitar el include del extensions.conf) + reload:
sudo asterisk -rx "dialplan reload"

# Desactivar la troncal desde /cobranza/voip (checkbox "activa") si se necesita
# cortar por completo el originate de nuevas llamadas.
```

No hay migración de BD que revertir en este item (0 cambios de esquema); es 100%
configuración de infraestructura + un comando de prueba aislado.

---
_Preparado por el Circuito CC (item roadmap #67) para ejecución manual de Irving en
producción. CC no tiene acceso a producción ni a las credenciales de Servnet — ver
`CLAUDE.md` § "CANDADOS DUROS"._
