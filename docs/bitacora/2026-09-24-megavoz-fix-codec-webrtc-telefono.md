## 2026-09-24 15:15 — MegaVoz: llamada del mini-teléfono web a una extensión de escritorio se caía al conectar

### Síntoma

David, probando el mini-teléfono WebRTC (Fase 2) para llamar a Irving en su extensión de
escritorio real (1001): "ya le marqué y el 1001 se cae al momento" — la llamada conecta y se
cae de inmediato, sin sonar nada.

### Causa

`/var/log/asterisk/messages.log`:
```
WARNING channel.c: No path to translate from PJSIP/web9001-... to PJSIP/1001-...
```

Las extensiones "gemelas" WebRTC (`web{numero}`, creadas por
`ReclamadorExtensionAutomatico::crearGemelaWebrtc()` en Fase 1) se provisionaban con
`codecs = 'opus,alaw,ulaw'` — Opus primero. Un teléfono de escritorio real (`1001`) solo tiene
`ulaw,alaw` (sin Opus). Aunque ambos endpoints comparten ulaw/alaw en su lista, Asterisk terminaba
negociando **códecs distintos en cada tramo** de la llamada (el navegador prefiere Opus, así que
ese tramo quedaba en Opus; el teléfono de escritorio no lo tiene, así que su tramo quedaba en
ulaw) — y este servidor **no tiene ningún transcodificador de Opus instalado** (confirmado:
`module show like opus` solo lista `res_format_attr_opus.so`, que negocia el formato en el SDP
pero no convierte audio; no existe ningún `codec_opus.so` en el disco). Sin manera de convertir
entre los dos, Asterisk no puede unir la llamada → la cuelga al conectar.

### Fix

`ReclamadorExtensionAutomatico::crearGemelaWebrtc()`: orden de códecs cambiado a
`'ulaw,alaw,opus'` (ulaw primero). ulaw/alaw (G.711) es parte **obligatoria** del estándar
WebRTC — cualquier navegador lo soporta, así que este cambio no le quita capacidad a nadie, solo
deja de depender de una conversión que este servidor no puede hacer. Opus sigue en la lista como
tercera opción (útil si algún día se instala el transcodificador, o para negociaciones
navegador↔navegador donde ambos lados sí podrían coincidir directo en Opus sin necesitar
traducción).

**Aplicado también a las 4 gemelas WebRTC ya existentes** (`web1001`, `web1004`, `web1201`,
`web9001`) — actualizado el campo `codecs` en BD + reprovisionadas contra PJSIP realtime
(`AsteriskProvisioningService::provisionarExtension()`, escritura directa a `ps_endpoints` vía
la conexión `asterisk_rt` — sin tocar archivos de `/etc/asterisk`, así que el fix quedó vivo de
inmediato, sin necesitar `dialplan reload` ni reiniciar Asterisk). Verificado:
`pjsip show endpoint web9001`/`web1001` → `allow: (ulaw|alaw|opus)`.

### Pendiente de verificar

David reintentando la llamada al 1001 tras el fix (no confirmado en el momento de este commit —
el fix se aplicó y se le pidió que probara de nuevo).
