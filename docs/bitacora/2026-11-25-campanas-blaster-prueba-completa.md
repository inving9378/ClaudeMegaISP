# 2026-11-25 — Campañas/Blaster: prueba de punta a punta y lo que se corrigió

## Pedido
Irving preguntó si se había probado completo Campañas/Blaster. No se había hecho: solo el Dashboard, la grabación, la
voz y la pantalla. Se probó en test, salieron fallas graves, y pidió corregir todo. Sobre la suspensión eligió
**quitarla de las campañas** (opción a).

## Cómo se probó (sin marcarle a nadie)
- **Llamadas:** el job real del blaster con el AMI real de test. Solo se cambió el canal `PJSIP/<troncal>/<número>`
  por canales `Local/` a un contexto temporal en memoria (`prueba-cob-claude`: contesta, no contesta, ocupado,
  rechaza), creado y quitado por AMI. El listener (`cobranza:ami-listener`) y los workers de colas fueron los reales.
  Los números eran ficticios (55000000xx).
- **Activación:** la de cobranza y la de aviso por zona se probaron dentro de una transacción revertida, con
  `Bus::fake`.
- **Pantalla:** se recorrió con Playwright: lista, ver llamadas, pausar, activar por zona, alta con validaciones y
  eliminar.

## Lo que falló y cómo quedó
1. **Suspensión.** Si en el último intento la llamada no se contestaba, daba ocupado o fallaba, se encolaba
   suspender el servicio del cliente 24 h después. Pasaba aunque hubiera pagado y aunque la campaña fuera un aviso.
   Ahora `ProcessCallResultJob` ya no suspende; el corte por adeudo sigue siendo el de facturación.
2. **La activación de cobranza tronaba.** En test, 9 de 2,180 morosos tenían dos números en el campo de teléfono y
   no cabían en la columna; el insert fallaba y la campaña nunca se activaba.
   - Ahora `CobranzaLlamada::normalizarTelefono` toma el primer número de 10 dígitos y acepta +52, 044 y dos números.
   - A quien no tiene un número que se pueda marcar no se le carga; el aviso al activar dice cuántos son.
   - La carga va en una transacción.
3. **Estados.** Ocupado y rechazada quedaban como «fallida» sin reintento, y «sin contestar» dependía del orden de los
   eventos: la causa del Hangup y el `OriginateResponse` se pisaban.
   - Ahora el desenlace lo da `OriginateResponse` según su `Reason`: 5 es ocupado, 1 y 3 son sin contestar, lo demás
     es falla.
   - Con eso se reintenta mientras queden intentos y al final queda `no_contesto`, `ocupado` o `fallida`.
   - La contestada no se vuelve fallida por nada que llegue después.
4. **El AMI leía las respuestas desfasadas.** El Originate leía el bloque que llegara primero: FullyBooted,
   Newchannel o la respuesta de la acción anterior. La mitad de las llamadas quedaban como fallidas mientras timbraban
   y se volvían a marcar. Ahora se espera la respuesta con el mismo `ActionID`, y el blaster abre su sesión con
   `Events: off`.
5. **Llamadas mudas.** La carpeta de audios (`/var/lib/asterisk/sounds/cobranza`) no existía y la app no la puede
   crear. Si no había voz, la llamada salía igual.
   - Ahora sin voz no se marca: en cobranza la llamada se pospone 15 minutos sin gastar intento, y en aviso no se
     marca a nadie.
   - Las fallas de la carpeta o de ffmpeg quedan en el log.
6. **Nadie actualizaba los estados.** El listener solo tenía una guía para instalarlo a mano. Ahora «Instalar
   dependencias» de VoIP tiene un **paso 55** (`lib/55-campanas.sh`) que deja lo siguiente:
   - La carpeta de audios, en `asterisk:asterisk 2770` como las grabaciones.
   - ffmpeg.
   - El servicio systemd `megaisp-campanas-ami`, que sale cada hora para tomar el código nuevo.

   Además, una llamada «marcando» sin noticias por 10 minutos se trata como no contestada.
7. **Otras fallas:**
   - Nada marcaba «pagada». Ahora, antes de cada llamada de cobranza, se vuelve a ver el saldo vencido: si ya pagó
     queda `pagada` y no se le llama, y el saldo que se dice es el de hoy.
   - Las fechas de inicio y fin no se respetaban. Ahora, pasada la fecha fin, la campaña queda `completada`, y lo
     mismo cuando ya no quedan llamadas.
   - «Días vencimiento» no se usaba. Ahora sí.
   - Sin límite de canales salían 50 llamadas a la vez. Ahora el tope por omisión es 10
     (`cobranza.canales_por_defecto`).
   - El error de validación se veía como `[ "..." ]`, y los errores de fechas, horas o intentos no se mostraban.
   - Asterisk podía avisar antes de que se guardara el uniqueid. Ahora la fila se guarda antes del Originate.
   - Salía un audio por llamada sin limpiar. Ahora se borran los de más de 3 días y el nombre lleva el texto, así que
     si cambia el saldo se genera de nuevo.

## Resultado en test, ya corregido
- La llamada contestada quedó `contestada`, con la voz grabada (7.6 s) y visible en el Dashboard.
- Con 3 intentos, sin contestar, ocupado y rechazada quedaron `pendiente` con reintento a +30 min.
- Con 1 intento quedaron `no_contesto`, `ocupado` y `fallida`.
- El teléfono incompleto quedó `excluida`. La llamada «perdida» (marcando desde hacía 20 min) volvió a la fila.
- En cobranza, el cliente sin adeudo quedó `pagada` y no se le marcó.
- No se encoló ninguna suspensión. Cada Originate leyó su propia respuesta.
- La activación de cobranza cargó 2,173 morosos; 7 sin teléfono válido. Se revirtió.
- Pantalla, ya compilada:
  - Sin nombre dice «El campo nombre es obligatorio.».
  - Con las fechas al revés se ve el error.
  - Activar por zona carga los distritos y avisa cuántos clientes se cargaron.
- Pruebas: `tests/Unit/VoIP/BlasterCampanasTest.php` (37) y `tests/Unit/VoIP` completo (101). También
  `test-55-campanas.sh` (24) y los demás del instalador, incluido el flujo completo (50).

## En prod
Con la versión y después **«Instalar dependencias»** de VoIP, que corre el paso 55. Antes de eso no conviene lanzar
campañas: sin carpeta de audios no se marca a nadie, y sin el servicio los estados no se actualizan.

## En test
El instalador no corre en dev, así que en test no están ni la carpeta de audios ni el servicio. En la prueba se usó
una carpeta en `storage/` y el listener a mano. Quedan pausadas, para verlas, las campañas de prueba con números
ficticios: `PRUEBA-CLAUDE-E2E` y las `PRUEBA-CLAUDE-E2E-2A/2B/2C`.

## Segunda comprobación (Irving: «compruébalo una vez más todo»)
Se repitió todo desde cero y un revisor aparte leyó el código sin saber las conclusiones. Encontró 9 puntos y se
corrigieron todos.
1. **Dos pases juntos le marcaban dos veces al mismo cliente.** Ya pasaba desde antes: el de la activación y el del
   cron, o dos campañas en los dos workers.
   - Ahora solo corre un pase del blaster a la vez (`WithoutOverlapping('blaster')`).
   - Además cada llamada se aparta con un `UPDATE … WHERE estado='pendiente'` antes de marcar.
   - Probado con dos procesos a la vez sobre la misma campaña: cada número se marcó una sola vez.
2. **Contestadas que se volvían a marcar si el listener se perdía el aviso**, por ejemplo al reiniciarse cada hora.
   - Ahora `OriginateResponse` Success también cuenta como contestada.
   - La llamada sin noticias se resuelve con el CDR (`voip_llamadas`): si dice ANSWERED queda contestada.
   - El servicio se levanta en 1 s.
3. **«Instalar dependencias» preparaba Campañas/Blaster aunque el módulo estuviera apagado.** Los comandos se
   registraban siempre; ahora solo con el módulo activo.
4. **El instalador daba el servicio por bueno aunque se cayera y se levantara en bucle.** Ahora exige que siga
   activo y con el mismo proceso después de unos segundos.
5. **El tope de canales era por campaña.** Ahora cuenta las llamadas en curso de toda la troncal.
6. **Teléfonos de un solo número que se rechazaban:** «55  1234  5678», «(55) 1234-5678», con extensión. Ahora se
   aceptan, y «sin teléfono» se cuenta por cliente.
7. **El listener viejo de supervisor, si alguien lo instaló con la guía a mano,** ahora se quita con respaldo.
8. **Pantalla:**
   - «Canales simultáneos» decía «Sin límite»; ahora dice «10 (por omisión)».
   - Los errores de voz salían repetidos.
   - Un error del servidor al crear no decía nada.
   - Dejar vacío un campo con valor de siempre daba error 500.
9. **Si se caía la conexión con Asterisk a media pasada, se gastaban intentos sin llamar.** Ahora se detiene la pasada
   y esa llamada vuelve a la fila sin gastar intento.

Comprobado en test con las mismas llamadas internas:
- Contestada, sin contestar, ocupado y rechazada con 1 y con 3 intentos.
- Segundo intento.
- Cobranza a un moroso con saldo (número ficticio; la voz con sus datos no salió a OpenAI) y a uno que ya pagó.
- Fecha fin pasada y fecha inicio futura; la campaña se completa sola.
- Los dos pases a la vez, la llamada perdida resuelta con el CDR, el tope de la troncal y la caída del AMI.
- El comando `cobranza:llamada-prueba`.

Grabaciones con voz en el Dashboard. Pruebas: `tests/Unit/VoIP` (108) y el instalador (`test-55` 29, flujo completo
50).
