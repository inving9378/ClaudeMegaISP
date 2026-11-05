## 2026-10-23 — Talento: «Mi día» como pestaña de la propia ficha

Pedido: lo que salía en «Mi trabajo (Portal)» debe estar en la ficha de Talento, como pestañas
nuevas, para todos; el Portal aparte se retira.

Esta rama agrega la pestaña **Mi día** a la ficha propia (no la ve un supervisor ni un
administrador viendo la ficha de otro: marcar entrada, subir evidencia o firmar es de la propia
persona):

- Asistencia de hoy: registrar entrada y salida con ubicación (misma revisión de lugar de trabajo).
- Órdenes de hoy y su detalle: iniciar, capturar evidencia con la cámara en vivo, firmas del
  técnico y del cliente, completar y aceptar.
- Aviso de documentos reabiertos pendientes de volver a firmar.

Sin lógica nueva en el servidor: usa los mismos endpoints `/talento/portal/*` que usaba el Portal.
La ficha recibe `secciones` (las mismas que `Actor::sections()` le daba a la persona en el Portal)
y muestra la pestaña si trae `mi_dia`.

Igual que en el Portal, la cámara y la ubicación del navegador solo funcionan entrando por https.
