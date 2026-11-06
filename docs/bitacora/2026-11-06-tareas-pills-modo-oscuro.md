## 2026-11-06 — Tareas: las etiquetas recuperan su color en modo oscuro

**Qué se veía.** En `/scheduling/task`, con modo oscuro, las etiquetas de estado, prioridad y fecha
atrasada salían todas con letra blanca sobre un fondo muy oscuro. Por eso «Por Hacer», «Terminado»,
«Media» y «Alta» casi no se distinguían.

**Causa.** Los colores oscuros de esas etiquetas sí existían desde el cambio de estilo del 4-nov
(`resources/sass/app.scss`), pero la regla general de `resources/sass/base/dark_mode/dark_mode.scss`
pone `color: #fff !important` a todo `span`. La misma regla ya había dado este problema en Vendedores y
en Facturación, y se resolvió igual: excluyendo esas clases.

**Arreglo.** Se agregan `:not(.task-chip):not(.task-chip-dot)` a esa regla. Esas clases solo se usan en
las columnas de esta lista.

**Probado.** En el navegador con la cuenta de prueba de administrador, con las 50 filas de la primera
página, midiendo el color real de cada etiqueta:

| Etiqueta (modo oscuro) | Antes | Ahora | Contraste ahora |
|---|---|---|---|
| Por Hacer | blanco | azul | 7.39 |
| Terminado | blanco | verde | 8.53 |
| En Progreso, Media | blanco | amarillo | 8.69 |
| Alta, fecha atrasada | blanco | rojo | 7.33 |
| Fecha normal | blanco | blanco, sin fondo | 17.4 |

El modo claro no cambia: mide lo mismo que antes.

**Visto y no tocado.**
- En el Calendario, las iniciales de la persona son blancas sobre el color de esa persona, y con un
  color claro casi no se leen (1.78 con verde claro). Pasa en los dos modos.
- La regla general sigue quitándole el color a cualquier otra etiqueta en `span` del sistema. No se
  quitó porque afecta a todas las pantallas.

Quién: sesión de David (Claude Code).
