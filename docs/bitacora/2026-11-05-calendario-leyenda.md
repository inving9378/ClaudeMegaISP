## 2026-11-05 — Calendario: la leyenda desaparecía al cargar

Pedido de David: «en el calendario se debe mostrar siempre la leyenda, al principio se muestra pero al
cargar el calendario se quita». Rama `fix/calendario-leyenda-visible`.

**Causa.** No se ocultaba: estaba debajo de la cuadrícula. Mientras llegaban las tareas la cuadrícula
estaba vacía y la leyenda salía junto a la barra; al pintarse los 720 px del calendario bajaba a 928 px
de la parte alta de la página, fuera de la pantalla en un monitor de 768 px de alto.

**Ahora** (`QCalendar.vue`, solo frontend)
- La leyenda va arriba de la cuadrícula, así que no se mueve al cargar.
- Se queda fija debajo de la barra superior del sistema al bajar por la página. Para eso, solo en esta
  pantalla, `.main-content` pasa de `overflow: hidden` a `clip`: con `hidden`, `position: sticky` no
  funciona. Se limita con `:has(.gc-leyenda)`; en un navegador sin `:has` la leyenda sigue arriba de la
  cuadrícula, pero no se queda fija al bajar.

**Verificado** en la copia de trabajo (127.0.0.1:8095, cuenta `prueba_admin`), a 1366×768, 1920×1080 y
390×844: al cargar, al llegar al final de la página, en Mes, Semana, Día y Lista, y con los filtros
abiertos, la leyenda queda dentro de la pantalla y nada la tapa, sin desplazamiento horizontal ni
errores. Revisada también en modo oscuro (1366×768, con el atributo de modo oscuro puesto a mano).

**Límite.** En el teléfono la leyenda ocupa 3 renglones (82 px) y al quedarse fija le quita ese espacio
a la cuadrícula.

Quién: sesión de David (Claude Code).
