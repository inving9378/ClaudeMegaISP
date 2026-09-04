# Item #753 — Seguimiento de la pregunta sin resolver de #741 (RESUELTO — tercera repetición de la misma carrera; generador corregido)

## Contexto

El item #741 ("Seguimiento: pregunta sin resolver de #733") se cerró el 2026-08-28 con la misma 1
pregunta `requiere_irving` sin `opcion_elegida` que venía arrastrando desde #218 → #733:

> ¿Cómo se clasifica cada uno de los tipos dudosos de inventario: herramienta, material, o hace
> falta una tercera categoría "equipo de cliente" (ONT, MODEM, TELEFONOS DE CASA)?

El generador automático de seguimientos creó #753 — **tercera vez consecutiva** que la misma
pregunta textual produce un hijo nuevo, exactamente la condición de carrera que
`docs/inventario-seguimiento-733-item-741-verificacion.md` (§"Deuda de fondo") ya había anotado:
"si se repite una tercera vez vale la pena que el generador espere a que `opcion_elegida` refleje
el cierre real del padre... o que lea `reporte_coloquial`".

## Verificación directa contra la BD de dev (2026-08-29)

Se releyó `inventory_item_types.categoria` completo (403 tipos). Las 17 entradas originales de la
lista de #218 siguen clasificadas igual que documentaron #733 y #741, sin cambios ni regresiones:

| Tipo | `categoria` actual |
|---|---|
| ONT (49), MODEM (4), TELEFONOS DE CASA (37), ELIMINADOR (250) | `equipo_cliente` |
| ACOPLADOR (134), SPLITTER (140), CONECTOR (389), CONECTORES (16), CABLE (65), PILAS (299), PAPELERIA (246), FLYERS (249), CARRETE (339), TENSOR (103), TENSORES (7), HOJAS (28) | `material` |
| POWER (46) | `NULL` — a propósito (tipo mezclado, resuelto en #684 moviendo cada artículo a su tipo real) |

Nada pendiente de la lista original de #218. (El catálogo sigue teniendo ~51 tipos con
`categoria=NULL` fuera de esa lista original — p. ej. `3M-288`, `ABS`, `KIT` — pero eso es trabajo
nuevo sin relación con lo que #218/#733/#741/#753 preguntaban.)

## Cambio de código: se frena la cadena en el generador (esta vez SÍ, a diferencia de #733/#741)

A diferencia de los dos cierres anteriores, aquí se corrige la causa raíz en vez de solo
documentar el hallazgo, porque el propio doc de #741 marcó la tercera repetición como el punto de
frenar:

- `JarvisService::cadenaSeguimientoRepetida()` (nuevo) camina la cadena `origen_item_id` del item
  que se está cerrando y cuenta cuántos ancestros ya cargaban **la misma pregunta textual**
  (comparación exacta tras `trim`). A partir de 3 generaciones idénticas, la pregunta se considera
  "ya perseguida lo suficiente" y **no genera un cuarto hijo**.
- `RoadmapItem::saving()` (el hook `#1008`) ahora separa, de las preguntas sin `opcion_elegida`,
  las que ya formaron cadena repetida (`omitidas`) de las genuinamente nuevas (`porGenerar`).
  Solo estas últimas producen un hijo (`generarSeguimientoPreguntas`); las omitidas solo dejan una
  entrada `seguimiento_omitido_cadena_repetida` en el log del item cerrado — auditable, sin crear
  ruido en la bandeja.
- El item #753 en sí se cierra con `preguntas[0].opcion_elegida` fijado a la respuesta real (ver
  tabla arriba) para que, aunque el guard fallara, la pregunta ya no cuente como "sin resolver".
  Es decir: se corrigió tanto el dato de este item puntual como el mecanismo general.
- No se tocó `preguntasSinResolver()` ni el resto del gate de cierre (`verificarCierre`) — el
  cambio es aditivo, solo agrega un filtro extra antes de decidir generar hijo.

## Por qué no se tocó #733 ni #741

Editar sus `preguntas[].opcion_elegida` retroactivamente no cambia nada (el hook solo dispara en
`saving()` cuando `estado_aprobacion` transiciona a `completado`, y ambos ya están cerrados) y
corre el riesgo de re-disparar otros hooks del modelo sin necesidad. Quedan como están, con su
propio doc de verificación como registro histórico.

## Conclusión

#753 no trae información nueva sobre la clasificación del inventario — la pregunta ya estaba
resuelta desde #684. Lo que sí es nuevo es que esta vez se corta la cadena en el generador para que
no exista un #77x/#78x repitiendo lo mismo una cuarta vez.
