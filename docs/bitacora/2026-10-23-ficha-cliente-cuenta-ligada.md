## 2026-10-23 — Ficha de cliente: los datos solo se copian a la cuenta del cliente

Pedido: el pendiente 16 del reporte del 22-oct ("al guardar la ficha de un cliente se copian sus
datos a la cuenta ligada a su número").

### Qué pasaba

Al guardar la ficha, el sistema copiaba nombre, correo, teléfono, Usuario WEB y, si cambió, la
Contraseña WEB a la primera cuenta de usuario con ese número de cliente, sin ver de quién era.

- En dev hay una cuenta de personal ligada a un número de cliente: al guardar esa ficha perdía su
  nombre, correo y usuario de entrada.
- Seis números tienen dos cuentas (una vieja sin rol y la de rol cliente). Se actualizaba la
  vieja con el Usuario WEB que ya tiene la otra; el índice único lo rechazaba y la ficha no se
  podía guardar (error 500).
- La copia de la contraseña que hace el guardado de la ficha tampoco revisaba de quién era la
  cuenta.

### Qué cambia (rama `fix/ficha-cliente-cuenta-ligada`)

- `ClientMainInformationObserver::cuentaDelCliente()`: solo cuentas de cliente (rol client o
  sin rol, que es como quedaron las importadas) y nunca la del sistema. Si hay varias, la que ya
  tiene el Usuario WEB de la ficha; si no, la de rol client; si no, la primera.
- Si el Usuario WEB nuevo ya es de otra cuenta, la ficha no se guarda (como pasaba con el índice
  único) pero ahora con aviso en el campo, «Ese Usuario WEB ya lo usa otra cuenta; elige otro.», en
  vez de un 500 que la pantalla no mostraba. Se probó primero guardar y no copiarlo: dejaba la ficha
  y la cuenta con usuarios distintos y el cliente perdía el enlace con su cuenta.
- `ClientInformationController::update`: la copia de la contraseña solo llega a cuentas de
  cliente.
- Se decide por rol, no por id: aplica igual en producción. No se tocaron datos; la cuenta de
  personal sigue ligada a su número, ya sin que se le copie nada.

Probado con transacción que se deshace, sobre la base de dev:

- 14 comprobaciones guardando la ficha (cuenta de personal, cuentas dobles, cliente normal, cambio
  y no cambio de contraseña, Usuario WEB ocupado, cuenta importada sin rol) y 11 por el guardado
  real de la pantalla (`POST /cliente/update/{id}`) como administrador, más 3 con una ficha sin
  cuenta ligada. Todas pasan en la rama; en main fallan varias.
- En el navegador, como administrador: Usuario WEB ocupado → 422 y el aviso debajo del campo; la
  ficha y la cuenta quedan sin cambios.
- `client:normalizar-usuario-web --apply` da lo mismo en main y en la rama (870 normalizados, 0
  omitidos).
- Las pruebas existentes de portal y ficha (`Portal/AuthTest`, `HistorialCambiosClienteTest`,
  `CanceladoBajaDefinitivaTest`) dan lo mismo en main y en la rama: 15 bien y 11 con error al
  preparar datos del portal (llave foránea `clients.created_by` en la base de pruebas), ajeno a
  este cambio.

Pendiente aparte (ya existía): `client:normalizar-usuario-web` guarda con la clase base de la
ficha, a la que no está registrado este observer; con `--apply` la cuenta no recibe el Usuario WEB
normalizado (en dev quedarían 754 clientes con usuario distinto en ficha y cuenta).
