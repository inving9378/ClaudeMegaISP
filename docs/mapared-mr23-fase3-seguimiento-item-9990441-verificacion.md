# Item #9990441 — Seguimiento de la pregunta sin resolver de #9990428 (verificación)

## Contexto

`#9990428` ("MR-23 fase 3 — cerrar el DoD del árbol: quitar iconos redundantes + sumar
puertos/empalmes/clientes al panel") se cerró con 3 preguntas marcadas `requiere_irving` sin
`opcion_elegida` en el momento del cierre → el hook de seguimientos (#1008) generó `#9990441`
automáticamente para que Irving las respondiera.

Irving sí las respondió (log de `#9990441`, `irving:CARLOS`, 2026-09-07 08:48): eligió la
**Opción 1** en las tres:

- **q1** (`c0c9285e4f26ffb5`): "Quitar solo iconos duplicados por tipo de nodo".
- **q2** (`704abd49dd276c86`): "Puertos (usados/total), Empalmes (count), Clientes
  (activos/total) — 3 KPI cards arriba del panel".
- **q4** (`27dc9900861a6e84`): "Mostrar 'activos/total' (activos = status activo; total = todos
  los asociados al nodo excluyendo baja definitiva)".

## Hallazgo

El trabajo real de `#9990428` ya se había implementado y mergeado a `main` **antes** de que la
respuesta de Irving quedara registrada (commits `e8e0812b` + `16a1ca57`, integrados vía
`e99fb7cf`, el 2026-09-06 17:34-17:44 — la respuesta de Irving es del 2026-09-07 08:48). El
generador de seguimientos leyó el arreglo `preguntas[]` sin `opcion_elegida` en el instante del
cierre y creó `#9990441` sin saber que la implementación ya resolvía el mismo criterio — mismo
patrón de carrera de timing ya documentado varias veces en `CLAUDE.md` (`#733`, `#741`, `#753`,
`#9990003`, `#9990353`, `#9990385`).

Verificado contra el código real en `main`, lo ya implementado cumple las 3 opciones elegidas:

- **q1** — `ProjectsComponent.vue` (commit `16a1ca57`): se quitaron los 3 iconos por fila que el
  panel ya cubre (`editar`, `eliminar`, `mostrar en el mapa`); se conservaron `convertir a`,
  `cambiar clasificación` y `adicionar componente` por ser acciones de organización del
  árbol/proyecto (no del elemento) — la propia descripción del `#9990428` ya distinguía esto
  caso por caso, y el resultado final coincide con el objetivo de fondo de la Opción 1 (quitar lo
  redundante con el panel).
- **q2** — `LayersController::resumen()` (`app/Modules/Addons/MapaRed/Controllers/LayersController.php:55-84`)
  devuelve exactamente `puertos{total,ocupados,libres}` / `empalmes{total}` /
  `clientes{total,activos}`, consumido por `ElementSidePanel.vue` como 3 secciones tipo KPI
  ("Puertos", "Empalmes", "Clientes colgados") — coincide con la Opción 1 literal.
- **q4** — `clientesActivos` cuenta `ClientMainInformation::where('estado', 'Activo')` sobre los
  `client_id` únicos de los puertos del nodo, mostrado como `activos / total` en el panel —
  coincide con la Opción 1 literal.

## Verificación

- `git log --oneline -- LayersController.php ElementSidePanel.vue ProjectsComponent.vue` confirma
  los commits de `#9990428` ya en `main` antes del timestamp de la respuesta de Irving.
- `git show 16a1ca57` confirma la eliminación literal de los 3 `<q-item-section>` de
  editar/eliminar/mostrar-en-mapa.
- `grep` sobre `ElementSidePanel.vue` confirma las 3 secciones reales (ocupados/total,
  empalmes total, activos/total) conectadas al endpoint `/mapa-red/api/layers/{id}/resumen`.

## Conclusión

Nada pendiente de implementar — las 3 preguntas de `#9990441` ya están resueltas por el código
que ya vive en `main`. **Sin cambio de código** (verificación, mismo criterio que los items
citados arriba).
