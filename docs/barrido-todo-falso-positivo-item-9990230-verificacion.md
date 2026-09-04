# Item #9990230 — Barrido: "Todo" en `DashboardController.php:35` (RESUELTO — falso positivo del barrido)

## Hallazgo original

El barrido exploratorio (Torre 24/7 Pieza 5b, #908) marcó:

```
app/Modules/Addons/Tickets/Controllers/DashboardController.php:35: if ($estado != 'Todo') {
```

## Causa raíz

`BarridoService::detTodoFixme()` hace un grep case-insensitive `\b(TODO|FIXME|deprecated)\b`
sobre **cada línea** de cada archivo PHP del módulo, sin distinguir comentarios de código. La
línea 35 no es un marcador de pendiente: es una comparación contra el string `'Todo'`, el
valor centinela que envía el `<select>` de filtro de estado en el frontend para pedir
"todos los estados" (sin filtrar).

Mismo patrón exacto que **#9990229** (`fix(gestionred#9990229)`, commit `6d6b2ee9`), donde el
mismo regex matcheó la palabra española "TODO" dentro de un comentario de migración. La
diferencia aquí: en #9990229 el match caía en un **comentario** (se pudo reescribir sin tocar
comportamiento); en #9990230 el match cae en **código funcional real** (comparación de string),
así que no hay "comentario que reescribir" — reescribir el valor `'Todo'` rompería el contrato
con el frontend.

## Verificación de que `'Todo'` es un valor real, no dead code

`grep` de los 3 métodos que usan este patrón (`getTicketAssignedToMe`, `getTicketAssignedTo`,
`getTicketsByDateAndStatus`) contra sus consumidores confirma el envío real del string `'Todo'`:

- `resources/js/components/module/tickets/component/AssignedTicket.vue:35` — `<option value="Todo">Todos</option>`
- `resources/js/components/module/tickets/component/AssignedTicket.vue:89` — `getTicketAssignedToMe("Todo")` (llamada inicial)
- `resources/js/components/module/tickets/component/ListAssignedTicket.vue:35` — `<option value="Todo">Todos</option>`
- `resources/js/components/module/tickets/component/ListAssignedTicket.vue:109` — `getTicketAssignedTo("Todo")` (llamada inicial)
- `resources/js/components/module/tickets/DashboardTicket.vue:68` — `<option value="Todo">Todos</option>`
- `resources/js/components/module/tickets/DashboardTicket.vue:147` — `ref("Todo")` (valor por defecto del filtro)

`$estado != 'Todo'` en `DashboardController.php:35` (y los mismos guards en `getTicketAssignedTo`
línea 52 y `getTicketsByDateAndStatus` línea 151) es exactamente la contraparte backend de ese
filtro: "si el usuario pidió un estado específico, filtra; si pidió 'Todo', trae todo". Lógica
vigente y correcta — **nada que resolver ni eliminar**.

## Resolución

Sin cambio de código de negocio (cambiarlo rompería el filtro de estado del dashboard de
Tickets). El hallazgo queda cerrado como `completado`; el dedup por `auditor_fingerprint`
(`AuditorService::yaExiste()`, columna existe independientemente del estado del item) evita que
una futura corrida del barrido sobre el módulo Tickets vuelva a crear este mismo item — DoD
cumplido sin tocar `BarridoService::detTodoFixme()`.

## Nota para quien lo lea después

`detTodoFixme()` seguirá produciendo falsos positivos futuros sobre cualquier ocurrencia de la
palabra española "todo/toda/todos/todas" en código o comentarios (dos incidentes ya: #9990229 y
#9990230). Endurecer el regex para exigir sintaxis real de marcador (`//\s*TODO`, `#\s*TODO`,
`/\*\s*TODO`) en vez de `\bTODO\b` suelto evitaría la clase completa, pero es un cambio al motor
del barrido mismo — fuera del alcance mínimo de este item puntual. Si se repite una tercera vez,
vale la pena abrir un item dedicado a `BarridoService::detTodoFixme()`.
