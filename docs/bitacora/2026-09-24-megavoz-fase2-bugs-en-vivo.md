## 2026-09-24 09:30 — MegaVoz Fase 2: 5 bugs reales encontrados y corregidos en vivo con David

Sesión de debugging en vivo probando el mini-teléfono ya cerrado (Fase 2). David reportó
síntomas uno por uno; cada uno resultó ser un bug real, no percepción.

### 1. Llamar a alguien mostraba "Llamada entrante"

JsSIP dispara `newRTCSession` para las DOS direcciones (`data.originator`: `local` saliente,
`remote` entrante) — el handler no lo miraba y siempre ponía `estadoLlamada='entrante'`,
pisando lo que `llamar()` acababa de fijar como `saliente` (el evento dispara SÍNCRONO
dentro de `ua.call()`, antes de que esa función regrese). De paso `llamar()` volvía a llamar
`engancharSesion()`, duplicando listeners. Fix: un solo dueño del estado (el handler,
diferenciado por `data.originator`); `llamar()` solo dispara `ua.call()`.

### 2. El modal desaparecía sin avisar al terminar

Sin respuesta / colgaron / rechazada / cancelada — el modal se cerraba de golpe. Se agregó
un mensaje ("No contestaron"/"Ocupado"/"Llamada rechazada"/etc., según la causa que da
JsSIP) + "Cerrando…" 2 segundos antes de limpiar la sesión de verdad.

### 3. Sin tono de "está timbrando"

Confirmado que NO era bug de Asterisk (la extensión de eco sonaba bien, el teléfono real
del 1001 timbraba bien, la señalización WSS end-to-end estaba sana). Por SIP el que llama
nunca recibe audio real mientras el otro lado suena — eso solo empieza al contestar. Ese
tono lo genera el propio teléfono/app que llama, nunca la central — pieza que nunca se
había construido aquí. Agregado con Web Audio (440+480Hz, cadencia 2s tono/4s silencio,
sin archivo de audio, arranca dentro del mismo clic de "Llamar" para no chocar con las
políticas de autoplay).

### 4-5. "El botón solo funciona en una esquina" — 2 intentos fallidos antes del correcto

- Intento 1: sospeché overlap con otro widget flotante (ayuda), subí el z-index del
  mini-teléfono a 10050. No lo arregló.
- Intento 2: sospeché que el ícono `<i>` de FontAwesome no repasaba el clic al botón
  (patrón real y conocido). Agregué `pointer-events:none` a los íconos. Tampoco lo arregló.
- **Diagnóstico real**: le pedí a David correr `document.elementFromPoint()` en los 5 puntos
  del botón (centro + 4 esquinas) desde la consola del navegador — Playwright no estaba
  disponible en esta sesión (se instaló a mitad de conversación, solo carga en la siguiente).
  Primer intento de script falló por comillas tipográficas al pegar (autocorrector del SO);
  reescrito sin comillas especiales, funcionó a la segunda.
  El resultado fue inequívoco: en el centro y las esquinas de abajo, el navegador devolvía
  `<div class="position-fixed bottom-0 end-0 p-3" style="z-index:11000">` — el contenedor
  del toast "Guardado correctamente" de `VoipTroncales.vue`/`VoipExtensiones.vue`/
  `VoipGruposTimbrado.vue`. Ese wrapper vive SIEMPRE en el DOM (sin `v-if`), con z-index muy
  por encima de cualquier otra cosa del sitio, anclado a la MISMA esquina inferior-derecha
  que la burbuja del mini-teléfono. Aunque el toast en sí esté oculto por Bootstrap sin
  mensaje, el wrapper sigue teniendo una caja real (por su padding `p-3`) que tapaba
  clics — solo la porción del botón que se asomaba fuera de esa caja respondía.

  Fix: `pointer-events:none` en el wrapper del toast por defecto (nunca bloquea nada sin
  mensaje que mostrar), `pointer-events:auto` en el `.toast` mismo (su botón de cerrar
  sigue funcionando cuando sí se muestra). Mismo patrón corregido en las 3 pantallas — están
  copiadas una de otra. Revisados y descartados: `VerTicket.vue` (ya usa `v-if` en el
  wrapper), `VoipIaBotManager.vue` (crea el toast por JS solo cuando hace falta),
  `FleetDocumentsDashboard.vue` (también usa `v-if`).

### Lección para la próxima vez

Ante "un botón flotante solo funciona en una esquina", sospechar PRIMERO de un
toast/notificación con wrapper siempre-en-DOM y z-index alto cerca de esa esquina — no
asumir que el problema es del propio botón. `document.elementFromPoint()` desde la consola
del navegador es la forma más rápida y concluyente de confirmarlo sin necesitar Playwright.

### Commits

`24758342`/`43f7346c` (dirección de llamada + cierre con aviso), `6850f018`/`4635f024`
(tono de llamando + z-index), `d0d28635`/`fcf33ffe` (pointer-events de íconos, no era la
causa real pero es una mejora legítima que se queda), `c171589f`/`5a1f80a5` (fix real:
pointer-events del toast).
