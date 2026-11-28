## 2026-11-27 14:10 — VoIP: la asistente de voz y la voz de Campañas «al máximo» + pantalla nueva

Pedido de Irving: «refactor de la pantalla de configuración de la IA de VoIP (usa impeccable) y
dejar la IA de VoIP funcionando al máximo, igual que la de Campaña». Rama `voip/ia-maria-al-maximo`.

### Lo que estaba mal (revisado en el código y en el log del servicio)

- La voz elegida en la pantalla no se usaba (siempre «nova») y la temperatura tampoco.
- El saludo a clientes nunca se usaba: la asistente no sabía quién llamaba (AudioSocket solo manda un
  UUID). Todas las conversaciones salían como «lead» y sin teléfono.
- Los leads nunca se creaban: la pestaña siempre estaba vacía.
- La transcripción se guardaba como `{rol, contenido}` y la pantalla leía `{role, text}`: se veía vacía.
- «Grupo de timbrado para transferencias» no hacía nada (la transferencia siempre sigue a la cola del
  mismo grupo). Los «prerequisitos» de la pantalla eran fijos (siempre verde/amarillo).
- Turnos de 13 a 23 s en el log de septiembre: la frase de espera sonaba primero y todo lo demás
  esperaba; la medición de «TTS» incluía decir la respuesta completa.
- Lo que llegaba mientras la asistente hablaba (su eco, ruido) abría el turno siguiente: de ahí
  «cliente dijo: ¡Muchas gracias!» sin que nadie hablara.
- Silencio del cliente: preguntaba «¿sigues ahí?» hasta agotar 12 turnos (~5 min).
- Tras una actualización el servicio (y el listener de Campañas) seguía con el código viejo hasta
  que alguien lo reiniciara a mano: el deploy solo hace `queue:restart`.
- Campañas: el audio salía sin normalizar (bajo y con estática en mu-law, lo que ya se había
  corregido en la asistente), el saldo se leía «1,234.50» y la fecha «30/11/2026», y sin fecha fin
  decía «antes del la brevedad posible».

### Lo que se hizo

- Quién llama: el plan de marcado arma `xxxxxxxx-xxxx-ca11-NNNN-NNNNNNNNNNNN` (número rellenado con
  «f»); `AudioSocketServer::numeroQueLlama()` lo lee. Si es cliente: saludo por su nombre y contexto
  para la IA (estado, plan, saldo de la ficha `balances`, fechas de pago y corte, referencia MEG
  calculada). Si dice que no es el titular, no da datos.
- Reglas de llamada añadidas a las instrucciones (1–2 oraciones, una pregunta a la vez, sin listas),
  fecha y hora, base de conocimiento; el saludo queda en la conversación (ya no vuelve a saludar).
- Frase de espera en un proceso aparte mientras se transcribe/piensa/genera la voz; la respuesta se
  parte en oraciones que se piden en paralelo (`OpenAiTtsDriver::synthesizeMany`); razonamiento
  `none` con respaldo automático a `low` si el modelo no lo acepta (gpt-5 no lo acepta).
- Modelos por defecto (Módulos IA): oído `gpt-4o-mini-transcribe` (entiende mejor el audio de
  teléfono y cuesta la mitad que whisper-1), voz `gpt-4o-mini-tts` (acento de México, entiende el
  tono). Campañas también `gpt-4o-mini-tts` primero. Lo asignado a mano no cambia.
- Al colgar: resumen, categoría, si se resolvió y, si no es cliente y quiere contratar, el lead.
- Dos silencios o el tope de turnos/tiempo: avisa y pasa a una persona.
- Voz, tono, velocidad y llamadas a la vez desde la pantalla (migración `2026_11_27_150000`).
- Reinicio tras deploy: el servicio deja de aceptar, espera las llamadas en curso y sale (systemd lo
  levanta); el listener de Campañas igual. «Instalar dependencias» (pasos 50 y 55) reinicia el
  servicio si su código es más viejo que su arranque (`servicio_desactualizado` en common.sh).
- Pantalla nueva (`VoipIaBotManager.vue`): estado real, interruptor, configuración por secciones con
  barra de guardar, «Escuchar» saludos, «Probar» sin llamar (también como un cliente), conversaciones
  con motivo/resumen, leads y base de conocimiento con diálogos accesibles. Mismo mundo visual que
  Campañas (tokens `.cp-raiz`, `CampanasDialogo`). Revisada en claro/oscuro, 1440 y 390 px.
- Campañas: loudnorm (medido: media -28 dB → -16.5 dB, pico -1.4 dB), «1,234 pesos con 50
  centavos», «5 de diciembre», «a la brevedad» sin fecha fin.

### Pruebas

- Unitarias: AsistenteVozTest (17), AudioSocketAsistenteTest (7, con sockets de verdad y procesos),
  VozCampanasTest (10) y las de VoIP/Campañas que ya había; instalador test-50 (26) y test-55 (29).
- Llamada simulada contra el servicio de la rama (puerto 9199, base de dev, APIs reales, sin marcarle
  a nadie): con el cliente de prueba #6810 contestó saldo, fecha límite y suspensión, pidió pasar con
  una persona y se despidió antes de transferir; con un número desconocido que quería contratar quedó
  el lead con nombre, colonia, servicio y plan. Silencio tras la frase de espera: 1–3 s (antes ~4.5 s
  después de una frase que bloqueaba). Costo ≈ 1 centavo de dólar por llamada.
- Asterisk real (contexto temporal por AMI, canal Local, quitado al final): el plan de marcado arma el
  UUID válido con el número y AudioSocket lo entrega.
- Datos de prueba borrados al terminar; el piloto de dev volvió a 0.

### Pendiente

- Dev: el servicio `megaisp-bot-voz` sigue con el código anterior hasta reiniciarlo una vez
  (`sudo systemctl restart megaisp-bot-voz`); desde esta versión ya se reinicia solo tras cada deploy.
- Prod (al publicar): «Buscar actualizaciones», luego «Instalar dependencias» de VoIP una vez (para que
  la asistente y el listener arranquen con el código nuevo), guardar la configuración de la asistente
  (regenera el plan de marcado con el número en el UUID) y F5.
- Para bajar la espera a menos de un segundo haría falta la API de voz en tiempo real (otra arquitectura).

## 2026-11-27 14:30 — Revisión independiente (agente revisor) y correcciones

Un revisor sin contexto encontró 9 puntos; 8 se corrigieron y 1 no era error:

1. Si fallaba el oído o la voz (sin IA, sin saldo), la llamada daba vueltas («no te escuché») hasta el
   tope. Ahora pasa a una persona (si ni el saludo sale, sigue directo a la cola).
2. El tope de un turno contaba también el silencio antes de hablar: un dictado largo se cortaba. Ahora
   la espera para empezar es la de la pantalla y, ya hablando, hasta 30 s. Fin de turno: 1 s de silencio
   (antes 0.8 s, partía frases en una pausa).
3. Carrera en la caché de audio entre el precalentado y una llamada: ahora se escribe aparte y se
   renombra al final.
4. El respaldo de razonamiento se olvidaba en cada llamada (un proceso por llamada): se recuerda en
   caché por modelo, con un tercer nivel sin razonamiento para modelos que no lo aceptan.
5. El proceso de precalentado heredaba el puerto: al reiniciarse, el puerto seguía aceptando. Lo cierra.
6. «Probar» se cortaba a los 19 intercambios (tope 40): ahora 80.
7. Un «sí» muy corto contaba como silencio (y al segundo se transfería): ahora pide que lo repita; el
   mínimo para transcribir bajó de 300 a 200 ms.
8. El historial empezaba con el saludo de la asistente (Claude/Gemini lo rechazan): el saludo va en
   las instrucciones.
9. «gpt-4o-mini-tts ignora la velocidad»: medido, no es cierto (0.75 → 8.2 s, 1.25 → 5.2 s).

Llamada simulada repetida tras las correcciones: mismo flujo (saldo, transferencia con despedida, sin
volver a saludar). Los tiempos de esa corrida salieron más altos (servidor con carga 6–9 en 4 núcleos).
