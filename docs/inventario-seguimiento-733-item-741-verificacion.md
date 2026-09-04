# Item #741 — Seguimiento de la pregunta sin resolver de #733 (RESUELTO — misma carrera del generador, ya respondida)

## Contexto

El item #733 ("Seguimiento: pregunta sin resolver de #218") se cerró el 2026-08-28 con 1 pregunta
`requiere_irving` sin `opcion_elegida`:

> ¿Cómo se clasifica cada uno de los tipos dudosos de inventario: herramienta, material, o hace
> falta una tercera categoría "equipo de cliente" (ONT, MODEM, TELEFONOS DE CASA)?

El generador automático de seguimientos creó el #741 para no perder esa pregunta — **exactamente
el mismo mecanismo** que ya había generado a #733 a partir de #218.

## Hallazgo: es la misma carrera, un nivel más abajo

El log de #733 muestra que el merge a main y la generación de #741 ocurrieron en el **mismo
segundo** (`2026-08-28T19:43:06-06:00`):

```
{"evento":"merge_a_main","branch":"circuito/item-733-...","merge_commit":"3a9905a2..."}
{"evento":"cierre_incompleto","faltantes":["enlace_revision no resuelve..."]}
{"evento":"seguimiento_generado","hijo":741,"preguntas":1}
```

El generador leyó el arreglo estructurado `preguntas[].opcion_elegida` de #733 (que seguía en
`null`) sin mirar que el propio item #733 **ya se había cerrado `completado`** con la respuesta
completa en su `reporte_coloquial` y en `docs/inventario-seguimiento-218-item-733-verificacion.md`:
la pregunta heredada de #218 ya no tenía nada pendiente (se respondió en la práctica por la cadena
#572 → #1007 → #218 (ELIMINADOR) → #684 (POWER), antes de que #733 siquiera naciera).

## Verificación directa contra la BD de dev (hoy, 2026-08-29)

Se releyó `inventory_item_types.categoria` para confirmar que el estado documentado en #733 sigue
vigente:

| Tipo | `categoria` actual |
|---|---|
| ONT, MODEM, TELEFONOS DE CASA, ELIMINADOR | `equipo_cliente` |
| ACOPLADOR, SPLITTER, CONECTOR, CONECTORES, CABLE, PILAS, PAPELERIA, FLYERS, CARRETE, TENSOR, TENSORES, HOJAS | `material` |
| POWER | `NULL` — a propósito (tipo mezclado, ver `docs/inventario-seguimiento-218-item-733-verificacion.md`) |

Las 17 entradas originales de la lista de #218 siguen clasificadas igual que cuando #733 cerró; no
hubo cambios ni regresiones. Las categorías "equipo_cliente" y "equipo_red" (la 3ª y 4ª categoría
que la pregunta planteaba como opción) siguen existiendo y aplicadas en `InventoryItemType`.

## Conclusión

#741 no trae información nueva: es un seguimiento generado por una condición de carrera del
generador automático (lee el campo estructurado `preguntas[]` antes de que el cierre del padre
termine de escribir su resultado), sobre una pregunta que **ya estaba resuelta cuando #741 nació**.
Se cierra sin cambio de código — igual que #733, el "cambio" ya estaba en `main` desde antes.

**Nota aparte, sin relación con este hallazgo:** el catálogo sigue teniendo ~51 tipos con
`categoria=NULL` fuera de la lista original de #218 (ej. `3M-288`, `ABS`, `KIT`, `TELEFONO`…). Si
se quiere clasificarlos es trabajo nuevo con su propio item, no parte de lo que #733/#741
preguntaban.

## Deuda de fondo (para quien la retome)

La causa raíz — el generador de seguimientos puede correr en la misma vuelta en la que el padre
resuelve su última pregunta, produciendo un hijo ya-respondido — se repitió una vez más
(#218→#733 y ahora #733→#741). Es una condición de carrera menor y de bajo costo (el hijo se cierra
con una verificación de 5 minutos), pero si se repite una tercera vez vale la pena que el
generador espere a que `opcion_elegida` refleje el cierre real del padre antes de fijar la
pregunta, o que lea `reporte_coloquial` del padre antes de decidir que algo quedó "sin resolver".
No se toca aquí — es una mejora al propio mecanismo del circuito, fuera de alcance de este item.
