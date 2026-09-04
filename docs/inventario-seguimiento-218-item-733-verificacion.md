# Item #733 — Seguimiento de la pregunta sin resolver de #218 (RESUELTO — la pregunta ya tenía respuesta)

## Contexto

El item #218 ("Inventario: ~18 tipos de artículo siguen sin categoría herramienta/material") se
cerró el 2026-08-28 con 1 pregunta marcada `requiere_irving` sin `opcion_elegida`:

> ¿Cómo se clasifica cada uno de los tipos dudosos de inventario: herramienta, material, o hace
> falta una tercera categoría "equipo de cliente" (ONT, MODEM, TELEFONOS DE CASA)?

El generador automático de seguimientos creó el #733 para no perder esa pregunta. Pero el propio
cierre de #218 (`docs/bitacora-sesiones.md`, entrada `2026-08-28 16:39`) ya documentaba que **15
de los 17** tipos listados habían sido clasificados por trabajo previo (#572 y #1007), que
"ELIMINADOR" se clasificó ahí mismo (`equipo_cliente`, commit `72c4e822`), y que solo quedaba
"POWER" pendiente — abierto como sub-item **#684** por ser un tipo *mezclado* (no una duda de
categoría simple).

## Hallazgo: #684 cerró la última pieza 37 segundos antes de que naciera #733

```
701aeba4 fix(inventario): created_by=1 en el tipo nuevo (item #684)      2026-08-28 18:41:42 -0600
8cc5f5f2 fix(inventario): depura el tipo mezclado 'POWER' (item #684)
a7a93e0d Integra circuito #684 (...) a main
```

El log del item #733 registra su creación (`evento: item_creado`) en `2026-08-28T18:42:19-06:00`
— **37 segundos después** de que el commit de cierre de #684 quedara en `main`. Es una carrera del
generador automático de seguimientos: leyó el estado de #218 justo antes de que su propio
sub-item terminara de resolver la última pieza pendiente.

## Estado real verificado en la BD de dev (hoy)

Se revisó `inventory_item_types` directo contra la BD (no contra la descripción desactualizada del
item). Los 17 tipos originalmente listados como "dudosos" están **todos** clasificados:

| Tipo | `categoria` actual |
|---|---|
| ONT, MODEM, TELEFONOS DE CASA, ELIMINADOR | `equipo_cliente` |
| ACOPLADOR, SPLITTER, CONECTOR, CONECTORES, CABLE, PILAS, PAPELERIA, FLYERS, CARRETE, TENSOR, TENSORES, HOJAS | `material` |
| POWER | `NULL` — **a propósito** (ver abajo) |

`POWER` no quedó "sin decidir": #684 investigó sus 5 artículos reales y encontró que era un tipo
**mezclado** (1 módem mal capturado, 2 medidores ópticos, 2 fuentes de poder de red) — asignarle
una sola categoría habría sido incorrecto para 3 de los 5. La resolución correcta no era elegir
herramienta/material/equipo_cliente para el tipo, sino **mover cada artículo a su tipo real**
(`MODEM HUAWEI`, `MEDIDOR`, tipo nuevo `FUENTE DE PODER EQUIPO DE RED` con `categoria=equipo_red`).
El tipo `POWER` queda vacío (0 artículos) y sin categoría porque no tiene consumidor que dependa de
ella — borrarlo es un paso de limpieza de catálogo aparte, fuera de alcance.

Además, la "tercera categoría" que preguntaba #218 (`equipo_cliente`) y hasta una **cuarta**
(`equipo_red`, decisión de Irving en #1007) ya existen en `App\Models\InventoryItemType` y están
aplicadas en el catálogo — la pregunta del #733 ya tiene, de hecho, las dos respuestas que
planteaba como opciones.

## Conclusión

La pregunta que #733 traía sin resolver **ya no tiene nada pendiente**: se respondió en la
práctica por la cadena #572 → #1007 → #218 (ELIMINADOR) → #684 (POWER). No hay clasificación
dudosa restante de la lista original ni frontera de negocio abierta. Se cierra #733 sin cambio de
código — el "cambio" ya está en `main` desde antes de que el item existiera.

**Sin relación con este hallazgo:** el catálogo de `inventory_item_types` tiene ~51 tipos con
`categoria=NULL` que **no** forman parte de la lista original de #218/#733 (ej. `3M-288`, `ABS`,
`ADAPTADOR`, `BANDA`, `KIT`, `TELEFONO`…). Si alguien quiere clasificarlos, es trabajo nuevo con
su propio item — no es parte de lo que #733 preguntaba.
