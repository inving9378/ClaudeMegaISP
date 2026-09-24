## 2026-09-24 15:00 — MegaVoz Fase 6: primera llamada real de punta a punta con David — 3 bugs reales encontrados y corregidos en vivo

### Contexto

Con el candado de pre-vuelo ya aplicado (ver
`docs/bitacora/2026-09-24-megavoz-fase6-candado-preflight.md`), se subió `piloto_porcentaje` a
100% temporalmente y se prendió el daemon (`voip:bot-voz-escuchar`) a mano para que David
probara a María con una llamada real, desde un celular, contra el troncal real de Irving
(5512099363). Se hicieron varias llamadas de prueba en vivo, iterando sobre 3 problemas reales
encontrados uno tras otro.

### Bug 1 — el saludo tardaba demasiado y Asterisk colgaba la llamada antes de que sonara

**Síntoma:** primera llamada real — sonó, contestó, pero David no escuchó nada; "puso estilo un
contestador para dejar un mensaje y nada más".

**Causa:** confirmado en el log de Asterisk:
```
ERROR app_audiosocket.c: Reached timeout after 2000 ms of no activity on AudioSocket connection
```
`AudioSocket()` en Asterisk trae un timeout de inactividad de **2000ms hard-coded** (no
configurable desde dialplan, `MAX_WAIT_TIMEOUT_MSEC` en `app_audiosocket.c`). El saludo se
sintetizaba EN VIVO (llamada real a OpenAI TTS + conversión ffmpeg) cada vez — con latencia de
red normal, eso fácilmente supera 2 segundos, y Asterisk colgaba el canal antes de que la
primera palabra del saludo llegara a sonar. Un valor negativo devuelto por la app de dialplan es
la misma convención de "colgar" ya documentada en el bug del candado de pre-vuelo — aquí el
daemon SÍ estaba disponible, el problema era la LATENCIA, no la disponibilidad.

**Fix:** `VoiceTtsService::sintetizarCacheado()` (nuevo) — cachea el resultado en disco por hash
del texto+voz, reutilizable entre llamadas. El saludo (texto fijo) se pre-calienta al arrancar
el daemon (antes de aceptar la primera conexión), así que sale de la caché en **~4ms** en vez de
esperar 1.7+ segundos a OpenAI. `BotVozEscucharCommand::hablarCacheado()` (nuevo) — igual que
`hablar()` pero sin borrar el `.pcm` después (es reusable, no efímero).

**Bug hermano encontrado al aplicar el fix:** el pre-calentado corría DESPUÉS de
`pcntl_signal(SIGCHLD, SIG_IGN)` — el mismo footgun ya documentado (SIGCHLD=SIG_IGN rompe la
espera de Symfony Process por el ffmpeg propio), esta vez en el proceso PADRE, no en un hijo
forkeado. Se movió el pre-calentado a ANTES de poner SIG_IGN.

**Verificado:** llamada real de prueba (uuid `324c917...`) — 3 turnos completos, sin ningún
timeout de Asterisk, `queue_log` limpio.

### Bug 2 — respuestas lentas (silencio muerto entre turno y turno)

**Síntoma:** "sí me escuchó pero se demora en responder lo que se le pregunta".

**Causa (no un bug, un diseño incompleto):** cada turno real encadena 3 llamadas de red
secuenciales — transcribir (Whisper) → pensar (LLM) → convertir a voz (TTS+ffmpeg) — que suman
varios segundos (medido después: STT ~1-2s, LLM ~1-1.5s, TTS+conversión ~1.5-2s). Sin nada que
decir mientras tanto, el que llama escucha silencio total.

**Fix:** `BotVozEscucharCommand::FRASES_ESPERA` (4 frases cortas, "Mmm, dame un segundo...",
etc.) — se habla UNA (cacheada, casi instantánea) justo después de detectar que la persona
terminó su turno, ANTES de arrancar los 3 pasos reales. Mismo mecanismo de caché que el saludo,
pre-calentadas también al arrancar el daemon. Se agregó además instrumentación de tiempos
(STT/LLM/TTS en ms por turno) en el log, para diagnóstico futuro.

**Verificado con David:** "se escuchó más rápido" tras el fix — confirmado en vivo.

**Nota de diagnóstico (no un bug):** en llamadas largas, el tiempo de "TTS" medido en el log
crece con el LARGO de lo que María dice (ej. instrucciones de reinicio de módem, ~35 segundos de
audio) — es correcto: `enviarAudio()` manda los frames a ritmo real (20ms/frame, por diseño, para
no desbordar el jitter buffer de Asterisk), así que reproducir una respuesta larga
necesariamente toma tanto como dura esa respuesta. El número en el log mide generación de audio
+ reproducción real juntas, no es una regresión de rendimiento.

### Bug 3 — a veces no entendía bien y repetía la misma pregunta

**Síntoma:** "verifica porque no entiende bien lo que se le dice a veces y repite la misma
pregunta".

**Causa:** confirmado en el log — turnos completos hechos de ruido de línea se transcribían como
frases sin sentido:
```
cliente dijo: "¡SUSCRÍBETE!"
cliente dijo: "Subtítulos realizados por la comunidad de Amara.org"
```
Estas son **alucinaciones clásicas de Whisper** (viene de sus datos de entrenamiento — subtítulos
de YouTube) cuando el audio que recibe NO tiene habla real. El umbral de energía del VAD (RMS
250, calibrado con audio sintético de prueba en la sesión anterior) resultó **demasiado bajo**
para el ruido de fondo real de una llamada de verdad (más alto que el audio de prueba) — el
ruido de línea solo ya disparaba "detecté voz", esos fragmentos se mandaban a Whisper, que
alucinaba texto. Con esa "respuesta" sin relación con lo que María acababa de preguntar, el LLM
razonablemente volvía a preguntar lo mismo — el síntoma reportado.

**Fix, 3 capas:**
1. `AudioSocketServer::escucharTurno()` — umbral RMS subido de 250 a 500, + un mínimo de 300ms
   sostenidos de energía real antes de considerarlo habla (un blip de 20-40ms no es una palabra).
2. `VoiceSttService::transcribir()` — cambiado a `response_format=verbose_json`, que trae
   `segments[].no_speech_prob` (la señal OFICIAL de Whisper para "esto no era habla real").
   Descarta el texto si `no_speech_prob >= 0.6`.
3. Lista de frases alucinadas conocidas (blocklist, defensa adicional): "subtítulos realizados
   por", "amara.org", "suscríbete", "gracias por ver el video", etc.

Un turno descartado por cualquiera de las 3 capas se trata exactamente como silencio — el bucle
ya sabía manejar eso, no se cambió esa parte (`if (! success || texto vacío) { turno++; continue; }`).

**Verificado en la llamada siguiente:** aparecen en el log entradas
`"turno descartado (silencio/alucinación)"` en vez de texto alucinado convertido en pregunta —
el filtro funciona.

### Bug 4 — volumen bajo y "un poco mal"

**Síntoma:** tras el fix de latencia, "se escuchó más rápido pero necesito que le subas el
volumen, se escucha bajito y un poco mal".

**Causa medida directamente** (script Python sobre el `.pcm` cacheado del saludo): el audio que
entrega OpenAI TTS, convertido tal cual a PCM, usaba solo **~27% del rango de 16-bit** (pico
8847/32768, RMS 1291.5) — mucho margen sin usar. Las dos quejas de David eran la MISMA causa: la
compresión mu-law típica de una llamada telefónica real tiene MÁS ruido de cuantización
precisamente en niveles bajos (asigna más resolución a señales fuertes) — "bajito" y "un poco
mal" no eran dos problemas, eran el mismo.

**Fix:** filtro `loudnorm=I=-16:TP=-1.5:LRA=11` (normalización EBU R128, techo de -1.5dB — nunca
clipea) agregado al comando ffmpeg en ambos métodos de `VoiceTtsService` (`sintetizar()` y
`sintetizarCacheado()`). Medido antes/después: pico subió de 27% a **84%** del rango, sin
recorte. Caché existente (grabada con el volumen viejo) borrada y regenerada con el fix aplicado.

**Verificado con David:** "ya marqué se escuchó mejor" — confirmado en vivo.

### Estado final tras esta sesión de pruebas

- `ia_bot_config.enabled=true`, `piloto_porcentaje=100` (⚠️ **subido a propósito para la
  prueba** — decisión pendiente de bajarlo de vuelta a 0 o dejarlo, ver nota abajo).
- Daemon corriendo a mano (`nohup`, no persistente todavía — sigue pendiente instalar
  `deploy/megaisp-bot-voz.service`, ver bitácora del candado de pre-vuelo).
- 4 llamadas reales completas verificadas de punta a punta, incluida una prueba de 2 llamadas
  simultáneas (concurrencia con `pcntl_fork()` confirmada funcionando en producción real, no
  solo en pruebas aisladas).
- Costo real acumulado de las pruebas: unos pocos centavos de dólar (STT+LLM+TTS por turno, ver
  logs).

### ⚠️ Pendiente — decidir el piloto real

`piloto_porcentaje` quedó en **100%** tras esta sesión de pruebas (subido deliberadamente para
que David pudiera probar). Con el daemon corriendo a mano (no persistente) y sin el servicio
systemd instalado, si el daemon se cae o el proceso termina (ej. se reinicia el servidor), el
candado de pre-vuelo protege — las llamadas simplemente caen a la cola real, no se cuelgan. Pero
mientras el daemon SÍ esté corriendo, **100% significa que TODO cliente real que llame habla con
María**, no es ya un piloto parcial. Decisión pendiente de Irving/David: bajarlo a un % real de
piloto, o dejarlo en 100% si ya se considera lista para producción completa.

### Pendiente técnico (sin resolver esta sesión, no bloqueante)

- Instalar `megaisp-bot-voz.service` (systemd, requiere a Irving).
- Explorar si vale la pena separar la medición de "generar audio" vs "reproducirlo" en el log
  (hoy la métrica TTS mezcla ambas, ver nota del Bug 2).
