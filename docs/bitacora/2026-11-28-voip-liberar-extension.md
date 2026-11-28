## 2026-11-28 16:30 — VoIP: la extensión de un usuario bloqueado, desactivado o eliminado queda libre

Pedido de Irving: «si hay un usuario asociado a una extensión y ese usuario se bloquea/elimina/desactiva, esa
extensión automáticamente debe quedar libre para asignársela a otro usuario». Rama `voip/liberar-extension`.

### Antes
`UserVoipObserver` dejaba la extensión con su dueño:
- desactivado: la quitaba de Asterisk;
- bloqueado: le cortaba las salientes (`from-internal-restringido`);
- reactivado: se la devolvía.

Al eliminar, la llave foránea solo le quitaba el dueño y la dejaba viva en Asterisk. En dev, la 1002 (física) y
la 2006 (web) seguían dadas de alta con su usuario inactivo.

### Ahora
- `LiberadorExtensiones` (VoIP/Services) libera:
  - la quita de Asterisk, para que ni quien se fue ni nadie la use mientras no tenga dueño;
  - le quita el dueño y regresa a `from-internal` la que el bloqueo restringió (otro contexto puesto a mano se
    respeta);
  - al teléfono web le pone contraseña nueva, porque la anterior pudo quedarse en el navegador de quien se fue;
    la del físico vive en el aparato y no se cambia.
  - Si Asterisk no deja quitarla, se queda asignada (no queda una «libre» que todavía funciona con la contraseña
    de antes).
- `UserVoipObserver`:
  - `estado` distinto de `activo` → libera;
  - al eliminar, `deleting` anota y `deleted` libera: si el borrado falla, no se toca nada;
  - reactivar no la devuelve: se asigna la que toque en Extensiones, y al asignarla se da de alta sola, como ya
    hacía editar.
- `voip:reconciliar-estados` reescrito: libera lo que el observer no pudo (usuario no activo o que ya no
  existe), deja para la vuelta siguiente la que está en llamada y reporta las de usuarios activos sin dar de
  alta. Programado cada hora. Los contadores se reinician en cada corrida.
- Extensiones: la que no tiene dueño dice «Libre».
- `docs/modulos/voip.md` actualizado.

### Pruebas
Punta a punta con el código de la rama, la base de dev en una transacción que se deshizo y un Asterisk falso (no
se tocó el de dev):
- bloquear libera la física y la web (física con su contraseña, web con nueva, contexto normal, fuera de
  Asterisk);
- reactivar no la devuelve;
- asignar la web liberada a otra persona la da de alta;
- desactivar con Asterisk caído la deja asignada; la reconciliación (en prueba dice «por liberar») la libera,
  junto con la 1002 y la 2006 de dev;
- borrado que falla por otra tabla: nada cambia; borrado que ocurre: queda libre.
