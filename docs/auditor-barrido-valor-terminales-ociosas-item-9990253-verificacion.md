# Item #9990253 — Auditor continuo: ¿los `[BARRIDO]` entregan valor real? ¿Por qué hubo terminales ociosas? (RESUELTO — investigación, sin cambio de código)

Item de **verificación, solo lectura**: mide el Motor de Auditoría Continua (`circuito:auditor`,
#559) ya en marcha. No propone construirlo, no cambia configuración, no cierra items ajenos.

## Parte 1 — ¿los `[BARRIDO]` entregan valor real?

### Corrección de método: la métrica "0 con `reporte_tecnico`" mide la columna equivocada

`reporte_tecnico` es una columna **vestigial**, no una señal de calidad del `[BARRIDO]`:

```
Total roadmap_items: 1057
Con reporte_tecnico NO NULO: 0   ← en TODO el sistema, no solo en los BARRIDO
Con reporte_coloquial NO NULO: 952
Filas en roadmap_item_reports: 5901
```

El diseño "dual" (`reporte_tecnico` + `reporte_coloquial`, migración `2026_07_11_140000`) fue
**reemplazado** por "TORRE V2" (`ReportarItemCommand` / tabla `roadmap_item_reports`, append-only,
ver su propio docblock: *"Reemplaza el `comentarios_claude .= EJECUTADO` que el prompt del ejecutor
mandaba hacer por tinker"*). El flujo de cierre vigente (el mismo que gobierna esta terminal) pide
`reporte_coloquial` + `circuito:reportar --tipo=cierre`, **nunca** `reporte_tecnico`. Por eso la
columna está en 0/1057 — ítems humanos, de Cowork y del auditor por igual. No es una señal de que
el `[BARRIDO]` "cierre en falso": es una columna que nadie llena desde que existe TORRE V2.

La señal real de si el `[BARRIDO]` deja rastro es `roadmap_item_reports`, y ahí **sí hay reporte de
cierre en los 12/12** items de la muestra, cada uno citando el archivo/línea tocado y el motivo
(ver tabla de la Parte 1 abajo — todos los `[cierre]` fueron consultados directo de esa tabla).

### Muestra: 12/12 items `[BARRIDO]` creados el 2026-09-04, diff real de 10 de ellos

Todos con rama; 11/12 con `merge_commit` (el 12° — #9990252 — se mergeó *mientras* se escribía este
reporte, ver más abajo). Duración mediana del ciclo: **158s (~2.6 min)** — rango 98s–333s. Un ciclo
de ~2 min no es un defecto por sí mismo (son barridos read-only sobre un solo archivo/línea); lo que
importa es qué encontró cada uno.

**Diffs verificados** (con `git diff merge-base branch-tip`, aislando SOLO el commit propio de cada
item — el `git show` directo de varios merges arrastraba contenido de ramas hermanas por el orden de
creación en cascada, así que se recalculó contra el merge-base real):

| Item | Archivo:línea | Veredicto | Qué encontró |
|------|---------------|-----------|---------------|
| #9990242 | `WhatsAppPanelController.php:293` | **cosmético** | Reescribe "acceden a todo" → "sin restricción" — solo esquiva el regex, cero cambio de comportamiento. |
| #9990240 | `ExtensionController.php:62` | **cosmético** | "a todo el staff" → "al personal completo" — mismo patrón. |
| #9990239 | `ApiController.php:679` (MegaFamilia) | **cosmético** | "método por defecto" → "por defecto (defaultMethodId)" — la palabra que disparó el match fue **"método"** (contiene "todo" como subcadena, ver causa raíz abajo), no "todo" suelto. |
| #9990237 | `ConciliacionDemoCommand.php:20` | **cosmético** | "Todo se marca con..." → "Cada dato generado se marca con..." — esquiva el regex. |
| #9990236 | `MetaOAuthController.php:149` | **cosmético** | "Guardar todo en el config" → "Guardar los datos obtenidos en el config" — esquiva el regex. |
| #9990234 | `FleetDocumentController.php:41,368` (Flotas) | **cosmético** | "envía todo como string" / "serializa todo a string" → "cada campo" — esquiva el regex en 2 líneas. |
| #9990233 | `TalentoLiquidacionController.php:54` | **cosmético** | Quita la referencia de fase "(2.2)" del comentario del guard anti-recálculo — el comentario seguía vigente, solo se limpió una referencia interna. |
| #9990229 | migración `grant_olt_permissions_to_mostrador.php:17` | **cosmético** | "TODO a super-administrator" → "todos los permisos a..." — esquiva el regex (aquí el match SÍ era la palabra mayúscula "TODO", pero usada como "everything", no como marcador de pendiente). |
| #9990248 | `ManualCepDriver.php:6` (PortalPago) | **valor real** | Corrige una afirmación **incorrecta** del docblock ("Útil... como respaldo cuando el servicio de Banxico está caído/bloqueado") — verificado contra `CepValidatorService::driverForMode()`/`BanxicoCepDriver`: `ManualCepDriver` NO es un fallback automático, solo se usa con `PAGOS_CEP_MODE=manual`; quien degrada ante Banxico caído es `BanxicoCepDriver` mismo. Documentación que antes mentía sobre el comportamiento del sistema de conciliación de pagos, ahora correcta. |
| #9990230 | `DashboardController.php:35` (Tickets) | **valor real (negativo→cierre correcto)** | Detectó que `'Todo'` en `$estado != 'Todo'` es el valor centinela real del filtro de estado (verificado contra 6 usos en 3 componentes `.vue` que envían literalmente `"Todo"`), NO un marcador TODO. Documentó el hallazgo en `docs/barrido-todo-falso-positivo-item-9990230-verificacion.md` y **no tocó código de negocio** — la resolución correcta era no tocar nada, y eso hizo. |
| #9990252 | `PermissionController.php:40` | **valor real — corrige la causa raíz** | Mismo patrón (`'Todo ya estaba sincronizado'` en código vivo). En vez de solo reescribir esa línea, **corrigió el detector** `BarridoService::detTodoFixme()` para exigir marcador dentro de comentario (`//`, `/*`, `#`) + mayúsculas exactas — el mismo patrón que ya usaba `AuditorService::detTodos()`. Esto es la pieza que cierra el patrón entero, ver "Causa raíz" abajo. |
| #9990243 | `EmbajadoresController.php:92` (PortalCliente) | **cosmético** | "todo en vivo y scopeado" → "cálculo en vivo, scopeado" — esquiva el regex. |

**¿Habría pasado una revisión humana?** Los 9 cosméticos: sí, sin objeción (no cambian
comportamiento, texto sigue siendo correcto), pero un revisor humano probablemente los habría
marcado como "no ameritaba un item propio" — son ruido de bajo costo, no trabajo perdido.
Los 3 con valor real sí son el tipo de hallazgo que se espera de una auditoría: dos correcciones de
documentación técnicamente falsa (#9990248, indirectamente #9990230 al documentar el patrón) y el
arreglo real del detector (#9990252).

### Causa raíz de por qué el 92% (11/12) fue ruido: bug real en `BarridoService::detTodoFixme()`

```php
// ANTES (código con el que corrieron 11 de los 12 items de la muestra):
if (! preg_match('/\b(TODO|FIXME|deprecated)\b/i', $linea, $m)) { continue; }
```

Dos mecanismos de falso positivo, confirmados con evidencia real de la muestra:

1. **La palabra española "todo" es válida y común en comentarios/código en español**
   ("todo el staff", "todo reporte", "todo en vivo", "TODO a super-administrator", el valor
   centinela `'Todo'` del filtro de Tickets). El regex no distingue "marcador de pendiente" de
   "palabra común del idioma" — no exige que esté dentro de un comentario ni en mayúsculas.
2. **Coincidencia por subcadena dentro de otra palabra**, causada por `\b` sin el modificador
   `/u`: PCRE sin `/u` calcula `\w`/`\b` byte a byte (`[A-Za-z0-9_]`), así que un carácter
   multibyte UTF-8 como "é" cuenta como **no-palabra** y crea un límite de palabra falso. Por
   eso "**mé**todo" (m-é-t-o-d-o) matchea `\btodo\b`: el byte de "é" rompe la cadena justo antes
   de "todo". Esto explica el match en #9990239 (`ApiController.php:679`, "método por defecto")
   donde no hay ninguna ocurrencia visible de la palabra suelta "todo".

**Ya arreglado, sin que este item tuviera que tocarlo**: el propio item #9990252 (parte de la
misma muestra) corrigió el detector en el commit `257e8b8d` (mergeado `6a6c12a6`, 2026-09-04
08:05:25) para exigir marcador de comentario (`//`, `/*`, `#`) + `TODO`/`FIXME` en mayúsculas
exactas — el mismo patrón que ya usaba `AuditorService::detTodos()` en otro lugar del propio
sistema. Verificado leyendo el diff del merge commit directamente:

```php
// DESPUÉS (ya en main desde 2026-09-04 08:05:25):
if (! preg_match('#(?://|/\*+|\#)\s*(TODO|FIXME|[Dd]eprecated)\b#u', $linea, $m)) { continue; }
```

Nota operativa: el worktree local de esta terminal (`wt-2`) seguía con la versión vieja del
archivo al momento de escribir este reporte (se sincronizó al arrancar la vuelta, antes de que
#9990252 mergeara) — **no se tocó** ese archivo desde aquí; la corrección real vive en el commit
de #9990252, verificado contra el propio historial de `main` (`git log`/`git show`).

### Veredicto Parte 1

Los `[BARRIDO]` **sí entregan valor real, pero concentrado en uno de cada doce**: el 92% de esta
muestra fue ruido cosmético de bajo costo (comentarios reescritos, cero riesgo, cero regresión —
todos verificados con `php -l` + `php artisan --version` antes de mergear), producto de un bug real
y ya identificable en el propio detector. El 8% restante entregó exactamente lo que se espera de
una auditoría automática: dos correcciones de documentación técnicamente incorrecta y, sobre todo,
el fix del propio detector que corta el patrón de raíz — el motor terminó auditándose y
corrigiéndose a sí mismo dentro de la misma tanda que generó el ruido. El ciclo de ~2 min es
coherente con la naturaleza del hallazgo (un archivo, una línea): no es indicio de cierre
ceremonial, el rastro en `roadmap_item_reports` (12/12 con `[cierre]` citando archivo+línea+verificación)
lo confirma.

## Parte 2 — ¿por qué hubo 4 terminales ociosas el 2026-09-03?

### Tabla de verdad real de `debeCorrer()` (`AuditorService.php:129-193`)

Orden de evaluación (el primer `false` gana; `forzar` es la única salida temprana a `true`):

| # | Condición | Resultado si se cumple |
|---|-----------|------------------------|
| 1 | `!habilitado()` (`torre_config.auditor_activo = false`) | **NO corre** — kill-switch propio del motor |
| 2 | `circuito->isPaused()` (kill switch global #342) | **NO corre** |
| 3 | `--forzar` | **SÍ corre**, ignora todo lo demás |
| 4 | `gastoApagado()` (Nivel 2 "EL GASTO", #712) | **NO corre** — gate DURO, se evalúa ANTES que cola/slots. Solo se re-arma cuando un item REAL (sin `auditor_fingerprint`) se completa, o (desde #891) un sondeo half-open cada `gasto_reintento_min` min |
| 5 | Intervalo desde última corrida no cumplido (cooldown, alargado por sequía Nivel 1) | **NO corre** |
| 6a | `cola >= umbral` (3) **y** `slots < slots_min_disparo` (2) | **NO corre** — "hay trabajo, no hace falta generar" |
| 6b | `cola >= umbral` **y** `slots >= slots_min_disparo` | **SÍ corre** — "se dispara para no dejarlas ociosas" (#980) |
| 6c | `cola < umbral` | **SÍ corre siempre**, sin importar slots |

`profundidadCola()` (la "cola" de esta tabla) **ya aplica el mismo tope `paralelo_mismo_modulo`**
que usa el reparto real (reusa `ejecutablesParalelo([], 200)`, que descuenta contra
`itemsEnVueloPorModulo()` cuando la perilla es > 1) — así que si un módulo ya tiene tantos items en
vuelo como su tope permite, esos items **no cuentan** en `cola` aunque estén reclamables. Esto es
relevante para el escenario medido: con 2 en vuelo y tope=2 sobre el mismo módulo, los 8 restantes
del mismo módulo se cuentan como "cola=0", no "cola=8".

### El punto (6b) — la mitigación de slots — **no existía todavía** en el momento medido

```
$ git log -1 --format="%H %ci" -S "slots_libres_min_disparo" -- AuditorService.php
d0d8e99992ed32b019040094ef32edee4f4b2404 2026-09-03 17:35:54 -0600
fix(circuito#980): slots_libres dispara el auditor aunque la cola no baje del umbral
```

El fix (item #980, sub-item de #907, ver su propia entrada en `CLAUDE.md`) se mergeó el
**2026-09-03 a las 17:35:54**. El escenario descrito en este mismo item #9990253 (10 elegibles,
mismo módulo, tope=2, 2 en vuelo + 4 ociosas) es precisamente la medición que **motivó** la
creación de #907→#980/#981/#982 esa misma tarde — es decir, ocurrió **antes** de las 17:35, con el
código que **no tenía** la rama (6b) de la tabla de arriba. Antes de ese fix, con `cola` calculada
en 0 (por el descuento de módulo-en-vuelo explicado arriba) el motor de todos modos habría corrido
por la rama (6c) — así que la causa real de las 4 ociosas no fue que el motor no disparara, sino
que **el motor no tenía forma de usar slots libres como señal de "genera más trabajo"**: sin (6b),
`slots_libres` no entraba en ninguna decisión, y aunque el motor generara algo nuevo, lo generado
caía en el MISMO módulo saturado (tope=2) y no liberaba ninguna de las 4 terminales ociosas — el
verdadero cuello de botella no era "¿corre o no corre el motor?" sino "el motor no tenía instrucción
de generar trabajo en módulos DISTINTOS al saturado para desahogar el tope de paralelismo".

**Confirmado con el log real**: `storage/logs/roadmap-externo-2026-09-03.log` no tiene NINGÚN
evento `auditor-genero-trabajo` ni `auditor-fallo` ese día (los nombres de evento que el propio
item sugería buscar); tiene 5 eventos `auditor-ciclo` con `"modo":"dry-run"` agrupados a las
14:14-14:15 — invocaciones manuales de alguien probando el comando, no el disparo automático del
`SchedulerCommand` (que registra `"modo":"vivo"` cuando corre por cron). Es decir: en todo el
2026-09-03, el disparo automático desde el scheduler **nunca llegó a ejecutar un ciclo `ciclo(true)`
con resultado** — coherente con que, antes de las 17:35, la cola (ya descontada por módulo) estaba
en 0 pero el intervalo/cooldown normal (rama 5, Nivel 1) seguía espaciando las corridas, y ninguna
tocó justo la ventana en que había slots libres detectables.

### Estado ACTUAL (2026-09-04, verificado en vivo, solo lectura vía `circuito:auditor` sin `--apply`)

```
cola reclamable=0 · umbral=3 · terminales libres=3 · racha seca=12 · gasto apagado=sí
Generador APAGADO por sequía (Nivel 2, #712): 12 ciclo(s) seguido(s) sin hallazgos nuevos.
Se re-arma solo cuando un item REAL se complete.
Escaneo por módulo: 30 módulos auditados, 16 en DoD de Fase 1, TODOS con "nuevos"=0
(todo gap detectado por los detectores actuales ya tiene item, abierto o cerrado).
```

Hoy, con 3 terminales libres, el motor **no genera nada** — pero NO por el mismo bug que el
2026-09-03: es el gate Nivel 2 (`gastoApagado`, fila 4 de la tabla, que se evalúa ANTES que
`slots_libres`) el que está activo, y correctamente: el escaneo de los 30 módulos confirma que
**no hay ningún gap nuevo que generar** con los detectores actuales — los 3 detectores mecánicos
(`detNullSafety`, `detJquerySinOff`, `detEnvRuntime`) y el barrido `detTodoFixme`/`detErrorSintaxis`
ya cubrieron todo lo detectable. Esto es exactamente el comportamiento de diseño (Nivel 2 existe
para no ocupar terminales generando ruido cuando la fuente está agotada) — no una repetición del
incidente del día anterior.

### ¿Los items que genera el motor caen siempre en el mismo módulo?

**No.** La muestra de 12 `[BARRIDO]` del 2026-09-04 cubre 11 módulos distintos (WhatsAppAgent,
VoIP, MegaFamilia, Payments, Marketing, Flotas, Talento, Tickets, GestionRed, PortalPago,
PortalCliente) — consistente con el diseño documentado de `ciclo()`: *"REPARTO ROUND-ROBIN entre
módulos, no vaciar el módulo 1 primero... 10 items del mismo módulo ocupan UNA terminal y dejan 5
ociosas"*. La concentración en un solo módulo que sí se observó el 2026-09-03 (10 elegibles en
"Roadmap / Circuito CC") no vino del `[BARRIDO]`: fueron sub-items humanos/Cowork de la cascada de
"paraguas bucle-reap" documentada extensamente en `CLAUDE.md` ese mismo día (#975-#982,
#9990012-#9990013, etc.) — trabajo generado por el propio ciclo de auditoría del circuito sobre sí
mismo, no por `BarridoService`/`AuditorService` generando gaps de código.

## Recomendaciones

**Bug real (ya resuelto dentro de esta misma investigación, sin código nuevo de este item):**
`BarridoService::detTodoFixme()` — corregido por #9990252, mergeado antes de que este reporte se
terminara de escribir. Nada que proponer aquí: el ciclo se cerró solo.

**Decisiones de diseño de Irving (NO se tocan aquí):**
- Si vale la pena bajar `sequia.gasto_racha_umbral` (hoy 2) o el criterio de "item real" que
  rearma el Nivel 2, para que el motor no se apague tan rápido cuando su fuente de código se agota
  — es una decisión de calibración de producto, no un bug.
- Si el barrido debería tener un detector adicional que NO dependa de grep de texto (que siempre
  va a tener el problema estructural de "palabra española común vs. marcador en inglés") — fuera
  de alcance mecánico, es rediseño del detector.

**Nota de proceso (no bug, solo observación):** el patrón "el falso positivo de HOY genera 11
items de ruido antes de que el 12° lo corrija" es intrínseco a un auditor mecánico sin memoria
entre corridas del mismo defecto — cada hallazgo se trata como independiente hasta que alguien
(humano o el propio motor) toca el detector. Es el costo esperado de barrer con grep de texto; el
motor ya demostró que se autocorrige cuando encuentra el patrón raíz, que es la propiedad que
importa mantener.
