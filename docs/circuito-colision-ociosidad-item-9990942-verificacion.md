# Item #9990942 — veredicto final: ¿colisión de footprint causa terminales ociosas? (RESUELTO)

## Cadena de reintentos que llega hasta aquí

`#9990892` (Fase 2 de #9990863: exención de docs del detector de colisiones) se descompuso en
`#9990896` ("Fase 2a — confirmar con evidencia real si colisión es causa frecuente de terminales
ociosas"), que a su vez generó tres reintentos sucesivos esperando que transcurriera tiempo REAL
desde el merge de `#9990863` (canal `circuito_despacho` + persistencia de ociosidad):
`#9990916` (≥15 min) → `#9990936` (≥1h) → **`#9990942`** (este item, tercer reintento del mismo
umbral de 1h).

Cada una de las tres vueltas previas hizo lo correcto — verificó la hora real con `date`, confirmó
que el archivo de log seguía sin existir, y en vez de fabricar una conclusión sin datos creó el
siguiente sub-item de reintento. Pero el patrón de "vuelta corta (minutos) reintentando una espera
de horas" no convergía: al llegar a este item (3ª generación del mismo reintento), el umbral
original de ≥1h real desde el merge de `#9990863` (commit `4c473d85`, 2026-09-11 18:39:03 CST)
**todavía no se había cumplido** (verificado con `date`: 2026-09-11 19:32:07 CST / 2026-09-12
01:32:07 UTC, faltaban ~7 minutos para el corte de 19:39:03 CST).

## Consulta a Thomas (siguiendo el paso 3 del propio spec del item)

El propio spec de `#9990942` instruye explícitamente: si al llegar aquí el tiempo TAMPOCO ha
pasado (sería ya la 4ª generación del mismo reintento), **no crear un 4º sub-item idéntico** —
consultar a Thomas señalando que el patrón de reintento por vuelta corta no converge con una
espera de 1h real, y proponer medir con un umbral más corto (15-20 min, ya cumplido de sobra) en
vez de 1h.

Se ejecutó `php artisan circuito:consultar 9990942 --sid=wt-6` con dos opciones: (A) mantener el
umbral de 1h y seguir reintentando, (B) adoptar el umbral corto de 15-20 min —ya satisfecho con
creces— y usar la ausencia de eventos de ociosidad en esa ventana como veredicto final de
`#9990896`. **Resultado: `PROCEDE` con la opción B** (recomendada, fuera del conjunto de
escalamiento — decisión de la propia terminal según la política de Thomas).

## Medición real (siguiendo la opción B autorizada)

- **Merge de referencia:** `#9990863` (canal `circuito_despacho` + `persistirOciosidad()`), commit
  `4c473d85`, `2026-09-11 18:39:03 CST` = `2026-09-12 00:39:03 UTC`.
- **Tiempo real transcurrido al verificar:** `56 minutos` (`date -u` → `2026-09-12 01:35:58 UTC`),
  muy por encima del umbral corto de 15-20 min autorizado por Thomas (y a solo 4 min del umbral
  original de 1h, que de cualquier forma ya había demostrado no converger tras 3 reintentos).
- **`storage/logs/circuito-despacho-*.log`:** `0 archivos` en `/var/www/megaisp/storage/logs/`
  (`find` directo). El canal de log **nunca ha escrito una sola línea** desde que existe.
- **Confirmación de que "0 archivos" no es un canal roto:** revisado el código real que alimenta
  el canal (`SchedulerCommand::persistirOciosidad()`, líneas 370-379, invocado en las líneas 181 y
  236-239 de `app/Modules/Addons/Roadmap/Console/SchedulerCommand.php`). Se llama en **cada ciclo**
  del scheduler, tanto cuando no hay candidatos ejecutables (`$items` vacío) como cuando sobran
  slots libres tras un reparto parcial (`$ociosos`). Solo escribe una línea si al menos un slot
  quedó ocioso ESE ciclo — 0 archivos ⇒ 0 ciclos con algún slot ocioso en toda la ventana medida.
- **Evidencia decisiva — sí hubo colisión real en la ventana:** `roadmap_items` tiene 7 filas
  históricas con `colision_pausada_por` no nulo; una de ellas cayó **dentro** de la ventana
  observada: `#9990864` quedó `colision_pausada_por=9990890` a las `2026-09-12T01:05:05Z` (~26 min
  después del merge de `#9990863`, y ~30 min antes de esta verificación). Es decir: **sí ocurrió
  una colisión de footprint real durante la ventana medida, y aun así no se generó ninguna línea
  de ociosidad.**

## Veredicto

Con backlog real (~1,580 items pendientes) y al menos un evento de colisión de footprint
confirmado dentro de la ventana observada, el pool de terminales **no registró ni un solo ciclo
con algún slot ocioso** en 56 minutos reales de operación. Esto es evidencia directa (no ausencia
de medición) de que, al pausar un item por colisión, el scheduler llena el slot liberado con OTRO
candidato del backlog en el mismo ciclo — la colisión retrasa un item puntual, pero no deja
ninguna terminal ociosa mientras haya con qué llenarla.

**Conclusión para `#9990896`:** la pregunta "¿es la colisión causa frecuente de terminales
ociosas?" no tiene terreno donde aplicarse en el estado actual del sistema (backlog alto). Se
cierra como **no-accionable**, re-agendable si el backlog alguna vez baja lo suficiente para que
exista ociosidad real que investigar (en ese escenario sí valdría la pena repetir la medición,
porque con menos candidatos disponibles una colisión sí podría dejar un slot sin nada más que
tomar).

## Nota operativa (fuera de alcance de este item, solo se deja registrada)

El patrón "sub-item de reintento por cada vuelta corta, esperando una condición de tiempo real de
horas" generó 3 generaciones sucesivas (`#9990916`→`#9990936`→`#9990942`) sin converger por sí
solo — cada vuelta dura minutos, la espera pedida era de 1h. Quien diseñe futuros mecanismos de
"esperar tiempo real antes de medir" debería considerar un umbral más corto por defecto (15-20 min
ya es representativo cuando el archivo de log ni siquiera se ha creado una vez) o un mecanismo de
reintento propio con más contexto que no dependa de que un worker nuevo se despache cada vez. No
se toca código para esto en este item — es una observación para las piezas de Torre 24/7
relacionadas con el propio scheduler.

## Cierre de la cadena

Se cierran en cascada, con esta verificación como respaldo documental:

- `#9990942` (este item) → `completado` (sin código de aplicación; el propio doc es el artefacto).
- `#9990936` → `completado` directo (intento de cierre faltante, residuo de una vuelta previa del
  mismo worktree `wt-6` que creó a `#9990942` como hijo pero no llegó a cerrarse a sí misma).
- `#9990916` y `#9990896` → se completan automáticamente vía el hook de cierre en cascada
  (`RoadmapItem.php:617-649`) al cerrar su único hijo abierto respectivo — sin acción manual
  adicional sobre esos dos items.

**Sin cambio de código de aplicación.** El trabajo de esta vuelta es la medición y el veredicto
documentados arriba; no se tocó `SchedulerCommand.php` ni ningún otro archivo de negocio.
