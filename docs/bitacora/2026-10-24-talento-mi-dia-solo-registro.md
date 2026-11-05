## 2026-10-24 — Talento: la entrada y la salida se registran en Asistencia; Mi día solo muestra lo registrado

**Pedido:** "en mi día pones registrar entrada pero eso está en la parte de asistencia, ahí deja nada
más lo que sería logs, ejemplo, registraste entrada a xxxx y salida más los trabajos del día".

**Qué cambia en pantalla (ficha propia)**
- **Asistencia** tiene arriba la tarjeta **"Tu asistencia de hoy"** con **Registrar entrada** /
  **Registrar salida** (con ubicación, igual que antes; si está fuera de lugar queda marcada para
  revisión). Al registrar, la tabla de abajo se actualiza. Ya no sale el aviso que mandaba a Mi día.
- **Mi día** ya no tiene botones de asistencia. La tarjeta **"Registro de hoy"** dice:
  "Registraste tu entrada a las 08:02 a.m. en Oficina principal" y "Registraste tu salida a las …"
  (o "Aún no registras tu salida."). Si no hay entrada: "Aún no registras tu entrada hoy. Se registra
  en Asistencia" (el enlace abre esa pestaña). Si quedó abierto el turno de otro día, lo avisa y
  manda a Asistencia. Debajo siguen **Órdenes de hoy** con todo su flujo (iniciar, evidencia, firmas,
  completar, aceptar).
- Quien abre la ficha de otra persona no ve la tarjeta para registrar (igual que antes).

**Archivos**
- Nuevo `TalentoMarcarAsistencia.vue` (lo que estaba en Mi día: mismos endpoints
  `/talento/portal/asistencia/{hoy,checkin,checkout}`).
- `TalentoAsistencia.vue`: muestra esa tarjeta con `esPropia && puedeMarcar`; se quita el aviso y el
  evento `ir-a-mi-dia`.
- `TalentoMiDia.vue`: tarjeta "Registro de hoy" de solo lectura + evento `ir-a-asistencia`.
- `TalentoColaboradorFicha.vue`: `puede-marcar` = la persona tiene «Mi día»; Mi día → Asistencia.
- Manual («Tu ficha — Mi día») + su captura `ficha-mi-dia.png` (datos de ejemplo) y la nota de
  Talento para JARVIS (`entrenamiento.json`; la guía se regenera cuando se recapture la llave de IA).
  En prod, el texto del manual se aplica con el mismo comando del seeder de
  `2026-10-23-talento-manual-jarvis-sin-portal.md`.

**Pruebas** (servidor de la rama, navegador): 20/20. Con datos simulados: sin entrada, turno abierto,
turno cerrado, entrada marcada para revisión y turno de ayer sin cerrar. De verdad con
`prueba_mostrador`: registrar entrada y salida desde Asistencia y verlas en Mi día (registros de
prueba borrados después). Administración en la ficha de otro: sin tarjeta para registrar, tabla igual.
