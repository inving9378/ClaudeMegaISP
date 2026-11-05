## 2026-11-04 — Clientes: el listado conserva búsqueda, página, orden y filtros al volver

**Rama:** `fix/clientes-tabla-estado-historial` (sin mezclar; el merge y `npm run prod` los autoriza Irving).

### Qué se veía roto → qué pasa ahora

- Al entrar a un cliente y volver con la flecha atrás se perdía la búsqueda, la página, el orden y los filtros → ahora quedan igual, con los mismos resultados y una sola carga.
- F5 también los perdía → ahora los conserva.
- Elegir Estado hacía que la Fecha de Corte dejara de aplicarse aunque se siguiera viendo → ahora aplican los dos.
- Quitar los estados del filtro dejaba la tabla vacía → ahora vuelve a mostrar todos.
- Cerrar el filtro de Estado sin cambiar nada recargaba la tabla → ya no.
- Si fallaba la carga del listado, la pantalla quedaba bloqueada sin aviso → ahora se desbloquea y avisa.
- Eliminar un cliente con una búsqueda activa no refrescaba la tabla → ahora sí.

### Decisiones

- **El término de búsqueda NO va en la URL** (se mantiene #9990815: no registrarlo). Va en `history.state`, que sobrevive a atrás/adelante y a F5. Página, filas, orden, estado, fecha y búsqueda ampliada sí van en la query string.
- **`spa-nav.js` no se tocó.** Su único `pushState` crea la entrada nueva y su `popstate` no escribe, así que no pisa `history.state`.
- **`DatatableClient.vue` solo lo usa Clientes**, por eso no lleva prop opt-in. La lógica vive en `resources/js/hook/useTableUrlState.js` para reusarla en los demás listados (#1162).
- `fechaCorte` se guarda en ISO completo para repetir exactamente el mismo filtro.

### Trampas que quedaron resueltas (por si se replica en otro listado)

1. La primera carga debe mandar el término: la respuesta se descarta si no coincide con el buscador.
2. Vue monta antes del `pushState`. Para la URL existe `currentSearch()` (#708); para el estado se agregó `currentHistoryState()`. Distinguir «volver» de «clic en el menú a la misma URL» requiere un `popstate`: la URL es idéntica en los dos casos.
3. Con debounce pendiente: en `popstate` se cancela (la URL ya es la de otra entrada) y al desmontar en navegación hacia adelante se escribe (la entrada aún es la del listado).
4. `dir` debe ser booleano real: el servidor compara `=== true`.
5. `sortBy` se valida contra las columnas visibles: el servidor solo une esas.

### Comportamiento a saber

Abrir la misma URL en la misma pestaña se comporta como F5 y conserva la búsqueda. Para limpiarla: borrar el buscador o entrar por el menú.

### Pruebas

Playwright contra un build `npm run prod` de la rama: las 7 pedidas con los ajustes de Irving, más una prueba por cada corrección y por cada trampa. Resultado: 49 de 50 verificaciones; la que falla es el error de ApexCharts de abajo. La eliminación se probó con un id inexistente y respuesta simulada.

### Queda aparte

- #1167 — Error intermitente `Element not found` de ApexCharts en la ficha: salió en 3 de 4 corridas completas, en fichas de clientes distintos. Esta rama no toca la ficha, pero no se reprodujo en 4 vueltas dedicadas, ni en main ni en la rama, así que no se pudo determinar si ya existía.

### Incidente de esta sesión

Los items #1161 a #1164 se crearon con `estado_aprobacion=requiere_irving` creyendo que así no correrían solos. `destrabe(opus)` los aprobó en 2 minutos, y #1161 y #1164 se mezclaron a main sin revisión de Irving. **`requiere_irving` no retiene un item**: para retenerlo hace falta un freno vigente (`tieneFrenoHumano()`), como el rótulo `[BLOCKED-…]`, que se puso a #1162 y #1163.

#1164 quedó a medias (commit `d871c3257`): solo corrigió el conteo (`ClientDatatableHelper.php:947-948`), no las filas (`ScopeClient.php:37-38`). Medido al elegir un solo día con 7 clientes y 51 el día anterior: a las 10:00 paginador 51 y tabla 51 (los del día anterior); a las 20:00 paginador 51 y tabla 7. El rango además sigue corrido un día. Pendiente de decisión de Irving en #1166.
