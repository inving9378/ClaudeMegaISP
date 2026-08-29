# Item roadmap #673 — Pieza 2: que la válvula ablande la frontera dura (RESUELTO — premisa obsoleta)

**Título del item:** "Pieza 2 — Que la válvula ablande la frontera dura ('requiere_irving') en vez
de apagarla ('pasa')". Sub-item de #646, creado 2026-08-28 16:19:43 como seguimiento de una pieza
pendiente de ese item.

## El cambio propuesto ya estaba implementado un día antes de que naciera el item

El texto de #673 describe el código de `JarvisService::fronteraDuraDeItem()` como:

```php
if ($item->frontera_valvula === 'mencion') return null; // el control DESAPARECE
```

y propone que `mencion` baje la frontera a `requiere_irving` en vez de apagarla del todo a
`pasa`. Verificado contra el código actual (`app/Modules/Addons/Roadmap/Services/JarvisService.php`,
método `fronteraDuraDeItemDetalle()`, líneas 458-482): eso es exactamente lo que ya hace, desde el
commit `17d79baf` — **"feat(circuito): fronteras duras gobernables + las dos guardas de la válvula
(#648)"**, fechado **2026-08-27 15:06:11**, casi 24h antes de que #673 se creara (2026-08-28
16:19:43). El texto de #673 quedó describiendo un estado del código que ya había cambiado.

Semántica actual, verificada línea por línea:

- `mencion` + modo `ablandar` (el que pide #673) → la categoría se retiene como `requiere_irving`,
  **nunca** como `pasa` — motivo textual: *"La válvula lo selló como MENCIÓN, así que la frontera
  «{categoria}» se ablandó a «requiere Irving» — nunca a «pasa»."*
- `mencion` + modo `apagar` (comportamiento legacy, preservado a propósito como opción) → sigue
  desapareciendo el control, igual que antes de #648.
- Falla-segura si no se puede leer la configuración de la válvula (tabla ausente/BD caída): cae a
  `ablandar` (el modo que retiene), no a `apagar` — documentado explícitamente en el código como
  "misma regla que hace que la válvula caiga del lado del keyword cuando el modelo no contesta".

El modo es gobernable (`TorreConfig::valvulaModo()`, tabla `torre_config`, columna `valvula_modo`,
consumida por `TorreFronterasController` en la pestaña Configuración de la Torre — ver memoria
`project_torre_configuracion`), y el **default cuando no está seteado es `ablandar`**
(`TorreConfig.php:88`). Verificado en la BD de dev: `valvula_modo = 'ablandar'` hoy.

## Verificación de consumidor

`TorreAutomationPolicy::estadoInicial()` (línea 288 del archivo real) delega en
`fronteraDuraDeItem($item)` sin cortocircuitar — es justo el punto que #673 pedía revisar
("verificar si el cambio se reduce a no cortocircuitar en `fronteraDuraDeItem()` y dejar que la
política haga su trabajo"). Ya es así: la política no tiene lógica propia de `mencion`/`accion`,
solo consume el resultado ya ablandado.

## La ambigüedad que el propio item señalaba

#673 pedía, antes de implementar, confirmar si la respuesta de Irving a la pregunta q2 de #646
(sobre "ablandar" en el sentido de contexto/tokens) aplicaba tal cual a este mecanismo concreto
(`mencion`/`accion` sobre texto). Es un no-issue ahora: no hace falta resolver esa ambigüedad
porque no hay nada que implementar — el propio Irving ya definió y comprometió la semántica final
directamente en el commit `17d79baf` (autor `Irving MegaISP`, vía sesión de Claude Opus 5), sin
pasar por la vía q2. Esa fue la decisión real, no la respuesta genérica de #646.

## Frontera dura — consultado antes de cerrar

El item exigía explícitamente `circuito:consultar` antes de cualquier acción, por tratarse de un
control de seguridad/acceso, incluso con brief de alta confianza. Se consultó a Thomas con el
hallazgo de arriba; respondió **PROCEDE** con la opción recomendada: cerrar como resuelto
documentando, sin tocar el código de la frontera (que ya está en el estado pedido y no se justifica
tocar dos veces la misma pieza de seguridad).

## Conclusión

No hay cambio de código que aplicar: la Pieza 2 de #646 ya se implementó completa en #648, con la
semántica exacta que #673 pedía (`mencion` ablanda a `requiere_irving`, nunca a `pasa`, modo
gobernable, default correcto, falla-segura del lado retenido). Se cierra #673 documentando el
hallazgo para que quede trazable por qué no generó ningún commit sobre `JarvisService`.

---
*Generado por el Circuito CC (worker wt-1) el 2026-08-29, item roadmap #673. Sin cambios de código
sobre la frontera dura — verificación de solo lectura contra
`app/Modules/Addons/Roadmap/Services/JarvisService.php`, `TorreAutomationPolicy.php` y
`TorreConfig.php`, más consulta a Thomas antes de cerrar.*
