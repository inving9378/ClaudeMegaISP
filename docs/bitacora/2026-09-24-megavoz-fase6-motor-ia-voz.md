## 2026-09-24 12:00 — MegaVoz Fase 6: motor de IA de voz en tiempo real ("María"), prototipo aislado

Última fase del plan de Atención a Clientes de MegaVoz. Instrucción de David: "sigue
con la fase 6". Dado el tamaño (8-12 días estimados en el plan original, riesgo
"alto"), se planeó primero (EnterPlanMode) y se acotó el alcance de esta pasada a
**construir y probar el motor aislado**, sin conectarlo todavía al flujo real de
clientes — exactamente como quedó aprobado.

### El hallazgo que cambió el tamaño real del trabajo

Al investigar, resultó que el diseño de "el cerebro" de María **ya estaba hecho**,
de una sesión anterior: migración `2026_06_11_310000_create_ia_bot_tables.php` +
modelos `IaBotConfig`/`IaBotConversation`/`IaBotKnowledgeBase`/`IaBotLead` +
pantalla admin `/voip/ia-bot` (`IaBotController`, ya funcionando). Ya existía un
`system_prompt` completo para María (con la convención `[TRANSFERIR]` para que la
IA misma marque cuándo pasar a un humano) y una base de conocimiento sembrada
(internet/voip/facturación/cobertura). **Lo que no existía era el motor real** —
`IaBotController` era CRUD puro, cero lógica de conversación, sin ninguna pantalla
de "probar el bot" en ningún lado.

### Piezas reusadas, nada de infraestructura paralela

- **Asterisk ya tenía TODO lo necesario**: `app_audiosocket`/`chan_audiosocket`/
  `res_audiosocket` (protocolo AudioSocket) compilados y corriendo — cero cambios
  al provisionador.
- **LLM**: `IAAdaptadorFactory` (mismo adaptador que ya usa Jarvis) — sin cliente
  HTTP propio.
- **TTS**: envuelve `OpenAiTtsDriver` (Marketing) — sin duplicar la llamada a
  OpenAI como sí hace `CobranzaTtsService`.
- **STT (Whisper)**: no existía NINGÚN STT en el repo, pero la resolución de la
  API key sí — mismo trait/proveedor `openai` que ya usa el TTS. Sin credencial
  nueva que pedir.
- **Patrón de daemon TCP**: calcado de `flotas:gps-listen`, con `pcntl_fork()`
  por llamada (una central sí necesita atender varias llamadas a la vez, a
  diferencia del listener de GPS que es secuencial a propósito).

### Piezas nuevas

`VoiceSttService`, `VoiceTtsService`, `ConversacionBotService` (arma el turno,
detecta `[TRANSFERIR]`, persiste transcript/costo), `AudioSocketServer`
(protocolo AudioSocket + VAD simple por energía RMS), comandos
`voip:bot-voz-probar` (prueba STT→LLM→TTS sin tocar Asterisk) y
`voip:bot-voz-escuchar` (el daemon real).

### 2 bugs reales encontrados y corregidos probando en vivo

1. **`SIGCHLD=SIG_IGN` del padre rompía ffmpeg en el hijo, en silencio.** Para no
   acumular procesos zombie del daemon se ignora `SIGCHLD` — pero esa
   configuración se HEREDA al hijo que forkea cada llamada, y ahí rompe la forma
   en que Symfony Process espera a que `ffmpeg` termine: `Process::run()`
   reportaba `isSuccessful()=false`/`exitcode=-1` **aunque ffmpeg hubiera
   terminado bien y el archivo PCM existiera de verdad en disco** — el síntoma
   era "TTS falló al hablar: No se pudo convertir el audio a PCM" con el audio
   YA CREADO. Reproducido de forma aislada (script de prueba con/sin
   `SIGCHLD=SIG_IGN`) antes de aplicar el fix, para confirmar la causa exacta.
   Corregido: `pcntl_signal(SIGCHLD, SIG_DFL)` al inicio de cada hijo.
2. **Doble `fclose()` del mismo socket** — `AudioSocketServer::cerrar()` +
   el `finally` del comando cerraban el mismo recurso dos veces → `TypeError`
   en PHP 8 (no silenciado por `@`, a diferencia de versiones viejas de PHP).
   Corregido dejando un solo punto de cierre.

### Verificado end-to-end, con costo real, sin inventar nada

1. `voip:bot-voz-probar` — dos escenarios de texto reales: pregunta de la base de
   conocimiento ("no tengo internet") → respuesta correctamente guiada por el KB
   sembrado (preguntó por el módem/luz verde/WiFi visible, calcado del script);
   pedido explícito de un humano ("quiero hablar con una persona") →
   `[TRANSFERIR]` detectado correctamente y NO disparado en el primer caso. Audio
   de salida verificado real (RMS de voz ~1248, no silencio/basura).
2. **Ida y vuelta STT→TTS→STT**: la transcripción de un audio ya sintetizado por
   el propio bot coincidió PALABRA POR PALABRA con el texto original — confirma
   que TTS y STT son fieles el uno al otro.
3. **Cliente AudioSocket de prueba** (full-duplex real con `stream_select`, sin
   tocar Asterisk — el primer intento, secuencial, se estancaba en un
   deadlock de buffer lleno): saludo recibido íntegro (290 frames = 92,800
   bytes), transcrito de vuelta EXACTO al `greeting_lead` configurado en
   `IaBotConfig`.
4. **Llamada REAL contra Asterisk** — `channel originate` hacia un contexto de
   prueba temporal y aislado (`[bot-voz-test]`, agregado a mano al archivo YA
   incluido `megaisp_dialplan.conf`, **nunca tocó** `grupo-1`/la cola real de
   clientes/`DialplanGeneratorService`). Primer intento falló
   (`app_audiosocket.c: Failed to parse UUID '1790275586.1'` — `${UNIQUEID}` de
   Asterisk NO es un UUID válido para `AudioSocket()`, hace falta
   `${UUID()}`, la función nativa de Asterisk para esto). Corregido y
   reintentado: la llamada conectó de verdad, ejecutó el protocolo completo,
   colgó limpio, sin canales huérfanos (`core show channels concise` vacío al
   terminar). Contexto de prueba y datos de prueba (conversaciones, audio)
   limpiados antes de cerrar — `grupo-1` y el troncal real de Irving (`id=3`,
   Grandstream) quedaron verificados intactos después.

### Nota operativa — proveedor de IA

`ia_proveedores` (la tabla que usa `IAAdaptadorFactory`, compartida con el chat de
Jarvis) estaba **completamente vacía** en dev — ninguna fila, nada configurado, a
pesar de que `config('services.anthropic.key')` SÍ tiene una key real (la que usan
otras partes del sistema vía `ClaudeApiClient`). Se creó una fila Claude con esa
misma key ya configurada (sin inventar ningún secreto nuevo) — **pero la cuenta de
Anthropic no tiene crédito** (confirmado con la API real: "Your credit balance is
too low"). Se desactivó esa fila (queda documentado el motivo en su propio
`ultimo_error`) y se activó una fila OpenAI (`gpt-4o-mini`, usando la key real que
YA vive en el Hub — el mismo `api_integrations` que usa TTS/STT) para poder
probar el motor de verdad. `resolverProveedor()` ya prioriza Claude automáticamente
en cuanto Irving recargue la cuenta y alguien reactive esa fila — no hace falta
tocar código.

**Efecto colateral a tener presente**: como `ia_proveedores` estaba vacía antes,
CUALQUIER otro consumidor de esta tabla (el chat de Jarvis, por ejemplo) estaba
igual de roto — con esta fila de OpenAI activa, ese chat también debería empezar a
funcionar (aunque respondiendo con OpenAI en vez de Claude, hasta que se recargue
la cuenta).

### Pendiente — la siguiente pasada, con luz verde aparte

- Reemplazar `Playback(beep)` en `DialplanGeneratorService::buildColaExten()` por
  el `AudioSocket()` real — esto SÍ toca el flujo que ya reciben clientes reales.
- El interruptor de piloto al 20% + horario de oficina.
- Concurrencia probada bajo carga real (más de 1 llamada IA simultánea a la vez).
- Barge-in (que el que llama pueda interrumpir a la IA a media respuesta).
- Calibrar el VAD (el corte de turno por silencio, 900ms, cortó una respuesta
  larga del propio bot a la mitad en una de las pruebas — el TTS de OpenAI deja
  pausas naturales entre oraciones que a veces superan ese umbral; puede necesitar
  ajuste fino con voz humana real, no solo con audio sintético de prueba).
