## 2026-10-24 — «Asignar trabajo»: día con calendario en español y hora en dos listas

**Pedido:** en Clientes potenciales › «Asignar trabajo», la fecha salía en formato inglés y pedía hora
con segundos (no dejaba capturarla); que no tenga formato en inglés, que no pida segundos, que salga
un calendario para el día y dos listas para hora y minuto.

**Antes:** un `<input type="datetime-local">`: el navegador decide el idioma y el formato (en inglés si
el navegador está en inglés) y, según el navegador, pide segundos.

**Ahora (`AsignarTrabajo.vue`, se usa igual en la ficha del cliente y en la del prospecto):**
- **Día:** al tocar el campo se abre un calendario (QDate de Quasar) en español, semana de lunes a
  domingo, con botón de hoy y «Quitar fecha». El día elegido se ve como «miércoles, 28 de octubre de
  2026», aunque el navegador esté en inglés.
- **Hora:** lista de 12 a.m. a 11 p.m. y lista de minutos de 5 en 5 (:00 … :55). Sin segundos. Se
  habilitan al elegir el día; sin hora, la orden queda solo con el día.
- Se manda `scheduled_at` como `AAAA-MM-DD HH:MM` (o solo el día); el backend no cambió.
- El calendario de Quasar usa `.row` por dentro y el Bootstrap global lo rompe: se neutraliza solo
  dentro del calendario (mismo arreglo que `PortalClienteApariencia.vue`).

**Prueba** (servidor de la rama, navegador en inglés a propósito): 13/13 — calendario en español y
en cuadrícula, se cierra al elegir, texto del día en español, listas de hora y minutos, la orden se
crea con `2026-10-28 16:30` y queda 28 oct 4:30 p.m. La orden de prueba (task 1693) y sus avisos se
borraron después.

## 2026-10-24 — El calendario no se veía en modo oscuro

**Pedido:** "en modo oscuro el calendario no se ve".

**Causa:** el calendario de Quasar quedaba con fondo blanco y el tema oscuro del sistema le ponía la
letra blanca: los días no se veían.

**Cambio:** `<q-date :dark="darkMode">` con el mismo `darkMode` de `hook/appConfig.js` que actualiza la
luna del topbar (`ModeVisualBody.vue`), así sigue el modo aunque se cambie sin recargar.

**Prueba** (servidor de la rama): modo oscuro → fondo rgb(29,29,29) con letra blanca; modo claro igual
que antes (fondo blanco, letra gris); cambiar a oscuro con la luna y abrir el calendario → oscuro.
