## 2026-10-23 — Talento › Credenciales: la fecha de vencimiento y la edición funcionan

Al registrar una licencia de prueba a la cuenta de prueba de mostrador salieron tres fallas en la
lista «Credenciales» de la ficha (nadie las había visto: en dev no había ninguna credencial):

- **«Vence: Invalid Date»**: la pantalla esperaba la fecha como `2027-01-15` y el servidor la manda
  como `2027-01-15T06:00:00.000000Z`. Ahora se toma solo el día.
- **El editor abría con las fechas en blanco** (mismo motivo): obligaba a volver a capturarlas.
- **Editar no guardaba nada, sin avisar**: el editor mandaba un `PUT` con archivo adjunto
  (multipart), y PHP no lee ese cuerpo en un `PUT`, así que el servidor recibía la petición vacía.
  Ahora va como `POST` con `_method=PUT`, igual que el resto de formularios con archivo.

Probado en el navegador como administrador: la tarjeta muestra «15 ene 2027», el editor abre con
las dos fechas y un cambio en las notas se guarda y se ve en la tarjeta (se dejó como estaba).
