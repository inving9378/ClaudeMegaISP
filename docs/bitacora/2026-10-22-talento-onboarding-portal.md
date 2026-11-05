## 2026-10-22 — Talento: el alta de campo da el acceso al portal que sí funciona

Falla 6 de la verificación del roadmap (`2026-10-22-talento-quita-roadmap.md`). Decisión del mismo
día: "hazlo como lo propones".

### Qué pasaba

"Generar onboarding" (paso 6 del flujo de campo) inventaba una contraseña y la guardaba en
`users.password` de la cuenta ligada al número de cliente y en "Contraseña Original"
(`client_additional_information.original_password`, que sale en el contrato). El portal del
cliente compara contra "Contraseña WEB" (`client_main_information.password`), así que la
contraseña que el técnico dictaba no entraba. El panel tampoco mostraba el usuario.

Además, la cuenta ligada se buscaba con `User::where('client_id', …)->first()`. En dev hay una cuenta
de administración (super-administrator) ligada a un número de cliente: el alta de campo de ese
cliente le habría cambiado la contraseña con la que entra al sistema.

En dev el paso nunca se usó (0 encuestas, 0 activaciones). En producción no se pudo revisar.

### Qué cambia (rama `fix/talento-onboarding-portal`)

- Si el cliente ya tiene Contraseña WEB (161 de los 167 clientes creados en los últimos 90 días
  en dev), el panel le muestra al técnico su Usuario WEB, esa contraseña y la dirección del
  portal. No se cambia nada.
- Si no la tiene, se genera una de 10 caracteres fácil de dictar (sin 0/O ni 1/l/I) y se guarda en
  Contraseña WEB. Queda en `activity_log` (`log_name = talento`) quién la generó, sin la contraseña.
- No se tocan la cuenta ligada al número de cliente ni la "Contraseña Original". Se escribe sin
  pasar por el observer de la ficha, que copiaría la contraseña a esa cuenta.
- El panel dice si la contraseña es la que ya tenía o es nueva, y que puede cambiarla en el
  portal, en Mi Perfil.

### Pruebas (transacción contra la base de dev, se deshace)

Antes del cambio: el portal no aceptaba lo que se dictaba, y se cambiaban la Contraseña Original y
la cuenta ligada. Después, en orden de trabajo y en tarea de campo: el portal acepta el usuario o
el número de cliente con la contraseña mostrada, en los casos "ya tenía", "no tenía" y "cliente
ligado a una cuenta de administración". La cuenta ligada y la Contraseña Original quedan iguales. El
panel se revisó en el navegador con respuestas simuladas (sin escribir en la base).

### Hallazgo aparte, sin tocar

Guardar la ficha de un cliente (`ClientMainInformationObserver::updateUserIfIsDifferent`) copia
nombre, correo, usuario y, si cambió, la contraseña a la primera cuenta con ese número de cliente.
Con la cuenta de administración ligada de arriba, editar la ficha de ese cliente le cambiaría el
usuario con el que entra al sistema. En dev hay 6 números de cliente con más de una cuenta.
