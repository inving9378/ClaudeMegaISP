## 2026-11-06 — App: lo atrasado en rojo y botón de reprogramar en «Mi día» (#1158, fase 4)

**Cómo estaba.** La app ya recibía las órdenes atrasadas, pero se veían igual que las demás y no se
podían mover: reprogramar solo existía en la web. Además, las etiquetas de estado de «Mi día» salían en
inglés («pending»), porque la pantalla esperaba otros nombres que los que manda el servidor.

**Servidor** (rama `feat/talento-reprogramar-app`):
- `POST /talento/api/ots/{id}/reprogramar`, con las reglas de «Mi día» en la web
  (`reprogramarPropia`): solo órdenes propias, abiertas y de Talento; de hoy en adelante; motivo de al
  menos 5 letras, que queda en el historial con quien lo movió.
- El detalle de la orden (`GET /ots/{id}`) agrega `atrasada` y `fecha`, para avisar también al abrirla
  desde el calendario o el historial.

**App** (`/home/meganet/TalentoEquipo-avisos`, rama `feat/mi-dia-atrasadas`, sale de `feat/calendario`;
versión 1.26 porque es algo nuevo):
- Tarjeta en rojo con «Atrasada · N días», aviso arriba de la lista y botón «Reprogramar».
- Ventana con calendario del mes (los días pasados no se pueden tocar), motivos rápidos iguales a los de
  la web y texto libre. Sin día y motivo no deja mover. Si el servidor rechaza o no hay señal, dice por
  qué y no se cierra.
- El detalle de la orden trae el mismo aviso y botón.
- «Mi día» se actualiza al volver del detalle.
- Las etiquetas de estado salen en español y con letra oscura, porque la blanca casi no se leía sobre
  naranja, azul claro o verde. También en el detalle.

**Diferencia con la web.** La web abre un aviso al entrar a «Mi día» si hay atrasadas; la app no lo abre,
para no estorbar al técnico en campo: muestra el aviso fijo arriba de la lista.

**Probado.**
- Servidor: `ReprogramarAppTest` (6 pruebas, 33 comprobaciones): mueve la propia y conserva la hora,
  guarda motivo y autor, deja de salir atrasada, no mueve la de otra persona, rechaza días pasados,
  motivo corto o solo espacios, lo terminado y lo que no es de Talento. También pasan `CalendarioAppTest`
  y `OrdenesAtrasadasTest`.
- App: en un navegador con tamaño de teléfono (360×640, 412×915 y horizontal), modo claro y oscuro, con
  datos de prueba: tarjetas, ventana, mover, rechazo del servidor, sin señal y detalle, sin errores.
  Aquí no hay emulador: **falta probarla en un teléfono**.

Quién: sesión de David (Claude Code).

**APK de prueba**, sin registrar como actualización del equipo:
https://dev.meganett.com.mx/downloads/meganetV126.apk (MD5 `dd0f5362ba59949566d39ce3fb8a6f15`). Versión 1.26 (126), misma firma que
la 1.25 y `zipalign -P 16` correcto; lleva también el calendario de la 1.25 y los avisos de la 1.24. Sin
la consulta del servidor en main, el botón da error al mover la orden.
