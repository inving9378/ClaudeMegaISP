# Item #9991076 — Seguimiento: pregunta sin resolver de #9991075 (RESUELTO — decisión ya aplicada por la cadena de items hermanos)

## Qué pedía el item

Auto-generado al cerrar `#9991075` ("Vendedores: restyle visual con estilos de la Torre — todas
las pestañas y subpestañas") con 2 preguntas `requiere_irving` sin `opcion_elegida`:

- **q1** — ¿Cómo estructurar el trabajo de restyle para que Irving valide progresivamente sin
  bloquear el circuito?
- **q3** — ¿Qué hacer si al replicar el look de la Torre aparecen inconsistencias funcionales
  menores (ej. tabla actual no encaja limpio en el patrón de cards/pestañas de la Torre)?

## Verificación

Ambas preguntas **ya tenían respuesta de Irving** antes de que este item se reclamara: el log
del propio `#9991076` registra, el 2026-09-14 08:11:29 (`por: irving:admin`, `decision: aprobar`):

```
"respuestas":{"q1":"c3dd44f846240246","q3":"f4fffeef4d2c872a"}
```

- **q1 → Opción 1** (recomendada, confianza alta, reversible): ir pestaña por pestaña con
  item/rama separada por pestaña, empezando por el listado principal; cada pestaña = 1 item nivel
  B con screenshots claro+oscuro para validación de Irving antes de abrir la siguiente.
- **q3 → Opción 1** (recomendada, confianza alta, reversible): mantener la estructura/lógica
  actual de Vendedores intacta y solo envolverla con el chrome visual de la Torre (contenedores,
  pestañas, tipografía, badges); si queda algún desajuste menor, reportarlo a Irving con
  screenshot en vez de tocar markup/lógica.

**Esa exacta estrategia ya está en ejecución**, vía una cadena de items creados directamente bajo
`#9991075` (no bajo este seguimiento) — confirmado contra la BD real del roadmap:

| Item | Pestaña | Estado |
|------|---------|--------|
| `#9991077` | Listado `/sellers/seller` (`SellerListar.vue`) | `completado`, mergeado |
| `#9991078` | Ficha individual del vendedor (`Menu.vue`, 7 pestañas) | `completado`, mergeado |
| `#9991079` | Comisiones/Tipos/Estados (pantallas de Configuración) | `aprobado_irving`, mergeado |
| `#9991080` | Formularios de reglas de comisión (AddRule/EditRule) | `en_progreso` (otra terminal) |

El propio `#9991075` ya había hecho el "listado principal" (commit `e86f2906`) antes de que
naciera este seguimiento. El cierre de `#9991077` documenta el mismo patrón: header
icono+título+wrapper `vnd-wrap`, `Datatable.vue` compartido intacto — es decir, **exactamente**
la Opción 1 de q3 (envolver con el chrome de la Torre, sin tocar lógica/estructura compartida).

## Conclusión

No queda nada por decidir ni por implementar en `#9991076` mismo: su único propósito era escalar
las 2 preguntas, ya están respondidas, y la respuesta ya se está aplicando en los items hermanos
(`#9991077`→`#9991078`→`#9991079`→`#9991080`, este último activo en otra terminal). Cerrado sin
cambio de código, como registro de la verificación.
