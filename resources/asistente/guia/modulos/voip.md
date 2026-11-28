<!-- Generada por `php artisan asistente:aprender voip` desde docs/modulos/voip.md, el menú y las pantallas de alta · huella ff8277ed040026d67d7b932313e5341adf6d55c7 · 2026-11-28. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# VoIP (conmutador telefónico)
**En pocas palabras:** Aquí se dan de alta las líneas del proveedor, los números internos de cada persona y a quién le timbra una llamada entrante, y todo se aplica solo en la central telefónica.

## Para qué sirve
- Crear, editar y dar de baja líneas del proveedor (troncales) y extensiones sin tocar la central telefónica a mano. Los cambios se aplican al guardar.
- Decidir a quién timbra una llamada entrante. Cada troncal se asocia a un grupo de timbrado, que puede sonar a todos a la vez o uno por uno en orden.
- Mantener separados dos tipos de números: **1000–1999** para aparatos físicos (teléfono IP o softphone, un número por aparato) y **2000–2999** para teléfonos web (el teléfono del navegador, uno por persona, en la misma área que su aparato). Todos se marcan con 4 dígitos y cada número timbra solo su línea.
- Liberar la extensión de quien deja la empresa para poder asignarla a otra persona.

## Pantallas
- [VoIP › Dashboard](/voip) — entrada al módulo.
- [VoIP › Troncales](/voip/troncales) — alta, edición y baja de las líneas del proveedor.
- [VoIP › Extensiones](/voip/extensiones) — alta y asignación de los números internos de cada persona.
- [VoIP › Grupos de timbrado](/voip/grupos-timbrado) — grupos que definen a quién le suena una llamada entrante.
- [VoIP › Asistente IA](/voip/ia-bot) — configuración, conversaciones, prospectos y base de conocimiento del asistente telefónico. Está en construcción parcial: todavía no contesta llamadas en vivo.

## Cómo se hace
### Dar de alta o cambiar una troncal
Se hace en [Troncales](/voip/troncales). Al guardar, si la troncal está activa, queda aplicada en la central. Si cambias datos de conexión (servidor, puerto, usuario, tipo, códecs, transporte o contraseña), se vuelve a aplicar sola.

### Que una troncal timbre a un grupo
Se hace en [Troncales](/voip/troncales) (la troncal) y en [Grupos de timbrado](/voip/grupos-timbrado) (el grupo). Cada vez que cambia algo, el sistema rehace solo el enrutamiento de las llamadas entrantes.

### Dar de alta un teléfono web
1. Entra a [Extensiones](/voip/extensiones).
2. Usa el botón «Nueva extensión».
3. En tipo, elige «Teléfono web». Si no pones número, toma el primero disponible del área de la persona.

A Mostrador, Ventas y Técnicos el teléfono web se les da solo al darlos de alta en Talento.

### Asignar una extensión libre a otra persona
Se hace en [Extensiones](/voip/extensiones). Las extensiones sin dueño aparecen como «Libre». Al asignarla, se da de alta sola en la central.

### Qué pasa cuando un usuario se bloquea, se desactiva o se elimina
Su extensión queda libre y sin dueño, fuera de la central. Si era teléfono web, se le cambia la contraseña. Si estaba restringida por un bloqueo, regresa a su estado normal. Reactivar al usuario **no** le devuelve la extensión. Si la central no responde en ese momento, el sistema lo reintenta cada hora.

### Reglas de numeración
- El número del teléfono web nunca se parece al de un aparato. Si existe la 1003, no habrá 2003, y el formulario no deja dar de alta un par parecido.
- «Sincronizar con Asterisk» pasa los teléfonos web viejos a un número propio y deja uno por persona. Se guarda el número anterior para el historial.

## Qué datos pide
### Alta de troncal (se hace dentro de [Troncales](/voip/troncales))
- **Obligatorios:** Nombre, Tipo, Direccion, Host
- **Opcionales:** ninguno

### Alta de extensión (se hace dentro de [Extensiones](/voip/extensiones))
- **Obligatorios:** Numero, Nombre
- **Opcionales:** ninguno

### Alta de grupo de timbrado (se hace dentro de [Grupos de timbrado](/voip/grupos-timbrado))
- **Obligatorios:** Nombre
- **Opcionales:** ninguno

## Quién lo usa
Lo usan principalmente los administradores de telefonía. Cobranza y Talento también aprovechan la misma central telefónica.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
