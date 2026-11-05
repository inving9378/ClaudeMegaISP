## 2026-10-24 — «Asignar trabajo»: sale el rol Desarrollador

**Pedido:** en Clientes potenciales › «Asignar trabajo», "falta el rol desarrollador".

**Causa:** la lista de «Asignar a» agrupa a los colaboradores activos de Talento por su puesto, y
Desarrollador no cuenta como puesto (`TalentoColaborador::ROLES_QUE_NO_SON_PUESTO`: no sale en el
carnet). Además, ningún desarrollador tenía ficha de Talento, y una orden de trabajo se asigna a una
ficha.

**Cambio:** `TalentoWorkOrderController::asignables()` agrega el grupo «Desarrollador» con los
colaboradores activos que tienen ese rol (sin volverlo puesto: el carnet y el resto no cambian).

**Datos en DEV (decisión del usuario: "Crearles su ficha ya"):** ficha de Talento activa, tipo interno,
para los 3 desarrolladores activos (fichas 44, 45 y 46), con el mismo `create()` del alta de Talento:
el observer les dio los permisos de su propia ficha (Mi día, sus documentos); no se les asignó
extensión de MegaVoz (su rol no la lleva) ni se generaron documentos (no tienen puesto con paquete).
Aparecen en la lista de colaboradores y en la de pagos con «sin regla de pago».
**En producción no se tocó nada:** allá, a quien deba recibir trabajo se le da de alta en Talento.

**Prueba:** `asignables()` como administrador en la rama → grupo «Desarrollador» con las 3 personas;
en main (sin el cambio) no salía.
