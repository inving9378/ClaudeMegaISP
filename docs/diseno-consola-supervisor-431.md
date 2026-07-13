# Diseño — Consola del Supervisor (item #431, Fase 1: auditar + diseñar, SIN construir)

**Fecha:** 2026-07-13
**Alcance de este documento:** Fase 1 del item #431 — auditoría de la infraestructura existente
del Circuito CC + diseño de las 4 piezas pedidas en el `prompt` del item ((a) seguridad,
(b) desmenuzado, (c) tope RAM/concurrencia, (d) contrato item-madre↔sub-items). **No se
construyó código nuevo** — este documento ES el entregable de la Fase 1. Fases 2 (intake en
Terminales) y 3 (despacho inmediato) quedan como items futuros, a decidir por Irving.

---

## 0. Resumen — lo que ya existe vs. lo que falta

La pieza más importante del hallazgo: **casi toda la maquinaria que la Consola del Supervisor
necesita YA EXISTE** y está probada en producción (dev) desde los items #334/#337/#341. La
"Consola" no es un sistema nuevo — es una **UI nueva sobre 3 mecanismos ya vivos**:

| Pieza que pide #431 | Mecanismo existente a reusar |
|---|---|
| Lanzar trabajo desde web sin que www-data ejecute `claude` directo | Patrón **flag + picker on-box** (`RoadmapCircuitoService::requestDisparo` → `circuito:disparo-check` → `vuelta.sh`), ya usado por el botón "Ejecutar vuelta ahora" |
| Repartir a estaciones libres con tope de concurrencia | `SchedulerCommand` (`circuito:scheduler`) — semáforo de N slots `wt-1..wt-N` vía flock, ya paraleliza items módulo-disjuntos |
| Item madre → sub-items ligados | Campo `origen_item_id` + patrón ya implementado en `RoadmapController::seguimiento()` |
| Desmenuzar texto libre en estructura con IA | `RevisorService::proponerOpciones()` / `briefarC()` — ya llaman `ClaudeApiClient` y devuelven JSON estructurado a partir de un item |
| Gating A/B/C preservado | `RoadmapItem::tomablePorCircuito()` + `ejecutablesParalelo()` + `config('circuito.revisor.alcance.denylist')` — nada de esto se toca |

**Lo que SÍ falta y es trabajo nuevo real de Fase 2/3:**
1. La caja de texto "orden al supervisor" en la Torre (UI).
2. Un endpoint + comando que llame a Claude para **partir una orden en N sub-items** (hoy solo existe partir-en-opciones o generar-un-brief, no generar-N-items-completos).
3. Lectura de RAM libre antes de despachar (hoy no existe en el circuito — sí existe un precedente de lectura de memoria en otro comando, ver §3).
4. Un permiso nuevo dedicado (`circuito.supervisor.ordenar`), más estrecho que `roadmap_manage`.

---

## (a) Seguridad — guard, auditoría, frontera dev-only

### Guard web: reusar el patrón de permisos Spatie ya usado por todo `/api/roadmap/*`

Las rutas actuales del circuito (`app/Modules/Addons/Roadmap/routes.php:77-127`) están bajo
`middleware(['web','auth'])` **sin** restricción de rol a nivel de ruta — la restricción real
vive **dentro de cada método del controller** vía `$this->authorize('<permiso>')`
(`app/Modules/Addons/Roadmap/Controllers/RoadmapController.php`, ej. líneas 208, 228, 286, 949).//

Verificado en BD (dev) que los 5 permisos del circuito hoy están asignados **exclusivamente** a
`super-administrator` + `DESARROLLADOR`:

```
circuito.disparar => [super-administrator, DESARROLLADOR]
circuito.decidir  => [super-administrator, DESARROLLADOR]
circuito.pause    => [super-administrator, DESARROLLADOR]
roadmap_manage    => [super-administrator, DESARROLLADOR]
roadmap_view      => [super-administrator, DESARROLLADOR]
```

**Diseño para Fase 2:** crear un permiso NUEVO y dedicado `circuito.supervisor.ordenar`
(no reusar `circuito.disparar` ni `roadmap_manage` — el requisito del item es "solo
super-administrator", y una capacidad que **crea N items y dispara procesos** merece su propio
gate, graduable por separado si algún día se relaja). Migración idéntica en forma al precedente
`app/Modules/Addons/Roadmap/migrations/2026_07_10_060000_create_circuito_disparar_permission.php`
(firstOrCreate + `givePermissionTo` aditivo, nunca `sync*`, `forgetCachedPermissions()`),
asignada solo a `super-administrator` (el item pide explícitamente "solo super-administrator";
si Irving quiere incluir `DESARROLLADOR` lo decide al aprobar Fase 2 — no asumido aquí).

El controller nuevo (`RoadmapController::ordenarSupervisor()` o un controller dedicado)
hace `$this->authorize('circuito.supervisor.ordenar')` como primera línea, igual que los 30+
usos existentes de `$this->authorize(...)` en el mismo archivo.

### Auditoría por invocación

Ya existe una tabla de auditoría para el mecanismo de disparo: `circuito_disparos`
(`requested_by`, `origin`, `item_id`, `requested_at`, `consumed_at` —
`RoadmapCircuitoService::requestDisparo()` líneas 974-1000). El diseño de la orden del
supervisor debe seguir el mismo patrón: una tabla `circuito_ordenes_supervisor` (o reusar
`log` del item madre — ver §(d)) con `ordenado_por` (login del usuario, vía
`RoadmapController::actorLabel()`, línea 269 — nunca inferir el actor de otro lado),
`texto_orden`, `sub_items_creados` (ids), `requested_at`. Cada invocación queda escrita
ANTES de disparar cualquier proceso (fail-safe: si el disparo falla, la auditoría de "quién
pidió qué" ya quedó).

### Frontera dev-only

El circuito entero ya opera bajo el supuesto "esta caja es dev" (nunca referencia a `.198`/
`.108` en ningún comando de `app/Modules/Addons/Roadmap/Console/`). No hay un guard explícito
de `APP_ENV`/host en el código actual — la frontera es organizativa (el circuito solo corre
on-box en dev, prod no tiene los comandos `circuito:*` en su crontab). **Recomendación para
Fase 2:** agregar un guard explícito y barato (`abort_if(app()->environment('production'), 403)`
o equivalente por config) al endpoint nuevo específicamente, porque es el primero de la familia
`circuito.*` que **lanza procesos de escritura de código** a partir de texto libre de Irving —
más motivo que los endpoints de solo lectura/toggle existentes para no depender solo de "prod no
tiene el cron".

---

## (b) Desmenuzado — cómo un claude parte la orden en sub-items completos

### Patrón a reusar: `RevisorService` + `ClaudeApiClient`

`RevisorService::proponerOpciones()` (`app/Modules/Addons/Roadmap/Services/RevisorService.php:412-450`)
ya resuelve el problema estructuralmente idéntico: le da a Claude opus un `system` prompt +
el contexto del item, y le exige responder **exclusivamente un array JSON** (sin texto fuera,
sin markdown) con un formato de string fijo por elemento. `briefarC()` (línea 372) es la misma
mecánica para generar un brief libre en vez de JSON. Ambos usan `ClaudeApiClient` (namespace
`App\Modules\Addons\Marketing\Services\ClaudeApiClient`) — el **adaptador IA único** del sistema
(regla de CLAUDE.md "SERVICIOS COMPARTIDOS ÚNICOS": IA vive solo en el módulo IA/Marketing,
nadie monta su propio cliente HTTP). El desmenuzado de la orden del supervisor debe hacer el
mismo llamado, no un cliente nuevo.

### Contrato de salida propuesto (JSON estricto, igual de estricto que `proponerOpciones`)

```json
{
  "sub_items": [
    {
      "title": "string, obligatorio, <=255 chars",
      "modulo": "string|null — mismo criterio que RoadmapItem.modulo (usado por el pre-filtro de paralelismo)",
      "reporte_coloquial": "string, obligatorio — 1-2 frases, lo que ve Irving en la tarjeta",
      "description": "string|null — detalle técnico si aplica",
      "prompt": "string, obligatorio — instrucción ejecutable para el ejecutor (mismo campo que ya usa el resto del roadmap)",
      "nivel_riesgo": "A|B|C, obligatorio",
      "opciones": ["array de strings, SOLO si nivel_riesgo=C, 2-3 opciones formato 'Opción N: ... — Pro: ... Contra: ...'"],
      "opcion_elegida": "string|null — SOLO si nivel_riesgo=C Y la orden de Irving ya decidía explícitamente (si no, null y queda pendiente en la bandeja como cualquier C)"
    }
  ]
}
```

### Regla de "items completos" (ya existe como concepto — aplicarla igual aquí)

El item #431 exige sub-items "COMPLETOS" (título+reporte_coloquial+nivel+modulo; C con
opciones+opcion_elegida). Esto es **el mismo criterio que ya usa el resto del roadmap** para que
un item sea ejecutable: `RoadmapItem` ya tiene los campos `opciones`/`opcion_elegida` poblados
por separado (`proponerOpciones` NUNCA toca `opcion_elegida` — eso lo decide Irving, línea 409).
**Diseño:** el comando de desmenuzado debe validar server-side (no confiar en que el JSON de
Claude venga completo) antes de crear cada `RoadmapItem`:
- `title` y `reporte_coloquial` no vacíos, `nivel_riesgo` en `RoadmapItem::NIVELES_RIESGO`.
- Si `nivel_riesgo === 'C'`: exige `opciones` con 2-3 elementos. `opcion_elegida` se deja en
  `null` salvo que la orden de Irving haya sido explícita al punto de poder mapearla 1:1 (caso
  raro; por defecto un C generado por desmenuzado **siempre** cae a la bandeja normal
  `requiere_irving` para que Irving elija — la orden NO es un atajo para saltarse su propia
  decisión, ver guardarraíl 4 del item). Este principio ya existe como guard duro en otra
  vía del sistema: `RoadmapCircuitoService::guard()` (líneas 339-345) prohíbe explícitamente
  que una vía no-humana (externa/MCP) fije `aprobado_irving` — *"la aprobación humana es
  exclusiva de Irving desde la Torre de control"*. El desmenuzado por IA, aunque disparado
  por una orden de Irving, sigue siendo una escritura de **la IA**, no un clic de Irving:
  debe respetar la misma frontera y nunca auto-fijar `aprobado_irving` ni saltarse la bandeja
  de un C.
- Un sub-item que no pasa la validación **no se crea**; se reporta como "descartado" en la
  respuesta al usuario (falla visible, nunca silenciosa — mismo criterio que
  `proponerOpciones()` línea 57: "sin propuesta utilizable — se omite").

### Modelo

Mismo criterio ya usado por el revisor (`config/circuito.php:39-40`): Opus
(`circuito.revisor.model_hard`, hoy `claude-opus-4-7`) para esta tarea — es una decisión de
partición de trabajo con impacto en gating de seguridad (nivel_riesgo por sub-item), no rutina
de Sonnet.

---

## (c) Tope de RAM / concurrencia — dónde vive y cómo se lee

### Concurrencia: ya existe, se reusa tal cual

`config/circuito.php` define:
- `paralelismo` (línea 88, default 6, override runtime en `settings.circuito_paralelismo` vía
  `RoadmapCircuitoService::getParalelismo()` líneas 1134-1140, clamp 1..12).
- `max_builds` (línea 89, default 3) — tope de builds `npm` simultáneos (No aplica directo a
  workers claude, es para el semáforo del build frontend; mencionado para no confundirlo).

`SchedulerCommand::handle()` (`app/Modules/Addons/Roadmap/Console/SchedulerCommand.php:51-62`)
ya calcula slots libres (`wt-1..wt-N` cuyo flock no está tomado) y nunca lanza más de
`getParalelismo()` a la vez. **El despacho inmediato de la Consola del Supervisor (Fase 3) DEBE
pasar por el mismo cálculo de slots libres** — no inventar un contador de concurrencia paralelo.
Concretamente: los sub-items A/B que la orden genere se crean con `estado_aprobacion` normal
(`pendiente_revision` para A, lo que corresponda para B) y el "despacho inmediato" de Fase 3 se
reduce a: (1) escribir los items, (2) invocar el mismo camino que ya usa el botón "Ejecutar
vuelta ahora" — `RoadmapCircuitoService::requestDisparo()` — que ya respeta pausa, ya hace
debounce, y cuyo picker (`circuito:disparo-check`) ya llama a `circuito:scheduler`
indirectamente vía `vuelta.sh`/cron. **No hay necesidad de un mecanismo de disparo nuevo**, ver
§(a) y la nota "flag + picker" del resumen.

### RAM libre: NO existe hoy en el circuito — hay que agregarlo, con precedente reusable

Confirmado por grep: ningún comando de `app/Modules/Addons/Roadmap/Console/` lee memoria del
sistema. `SchedulerCommand` solo cuenta slots por flock, **no por RAM disponible** — si Irving
sube el paralelismo a 12 en una caja con poca RAM, hoy nada lo frena (este fue justo el origen
del OOM que tumbó la ".202" que menciona el item).

Sí existe precedente de lectura de memoria en el proyecto:
`app/Console/Commands/Active/ServerStatusCommand.php:48-56` — usa `shell_exec('free -m')` +
regex para sacar `MemTotal`/`MemUsed` en MB, y `sys_getloadavg()` (línea 62) para carga del
sistema. Ese comando está gateado a `config('app.env') === 'production'` (línea 30) y es
puramente informativo (guarda un log), pero el snippet de lectura es directamente reusable.

**Diseño propuesto (Fase 3, no construido aquí):**
- Nuevo método pequeño, ej. `RoadmapCircuitoService::ramLibreMb(): ?int` — mismo `shell_exec('free -m')`
  + parseo, `null` si falla (fail-safe: si no se puede leer RAM, no bloquear por eso —
  decisión a confirmar con Irving en Fase 2, alternativa es fail-safe al revés/no despachar).
- Config nueva `circuito.ram_minima_libre_mb` (ej. default conservador, a definir con Irving —
  la caja de dev es "4 cores/17GB" según el comentario de `config/circuito.php:85`).
- En `SchedulerCommand::handle()` (y su equivalente para despacho inmediato), **antes** de
  lanzar cada `lanzarVueltaItem()` (línea 97/129): si `ramLibreMb() < ram_minima_libre_mb` →
  no lanzar ese slot, dejar el item en su estado actual (no se pierde, se reintenta en el
  siguiente tick del scheduler). Mismo espíritu que el chequeo de slot libre ya existente
  (línea 56: `if (! $this->slotFree(...)) continue`).
- El "tope de workers claude simultáneos configurable" que pide el item ES `paralelismo`
  (ya configurable, ya con UI de Irving vía `setParalelismo()`/`nombresWorkers()`) — no hace
  falta un segundo tope, solo el guard de RAM adicional descrito arriba.

---

## (d) Contrato item-madre ↔ sub-items

### Ya existe: `origen_item_id`

Migración `2026_07_10_040000_add_cancelado_and_origen_to_roadmap_items.php` agregó
`origen_item_id` (nullable, indexado) a `roadmap_items` — "item de la Hoja de Ruta que originó
este (seguimiento) — trazabilidad". Ya usado en producción (dev) por
`RoadmapController::seguimiento()` (líneas 386-424): crea un `RoadmapItem` nuevo con
`origen_item_id` apuntando al item de origen, más una entrada en su `log` (`evento =>
'creado_como_seguimiento'`) y opcionalmente cierra el origen.

### Diseño para la orden del supervisor

1. **El item-madre es la ORDEN misma**, no un item ejecutable normal. Al enviar la orden desde
   la Torre: crear un `RoadmapItem` con `title` = resumen corto de la orden,
   `description` = texto completo tal cual lo escribió Irving, `status` = algo que lo distinga
   de un item normal (ej. reusar `modulo = 'Supervisor'` o un campo booleano nuevo si se
   necesita filtrar la UI — a decidir en Fase 2, no hay precedente de un "tipo" de item hoy),
   y **sin** pasar por triaje/ejecución (no es ejecutable, es contenedor).
2. Cada sub-item generado por el desmenuzado (§b) se crea con `origen_item_id` = id de la
   madre — mismo campo, mismo patrón que `seguimiento()`. El `log` de cada sub-item registra
   `evento => 'creado_por_orden_supervisor'` con el id de la madre (paralelo exacto a
   `'creado_como_seguimiento'`). El frontend ya tiene precedente de armar este payload
   (`resources/js/components/module/releases/TorreControl.vue:521`, `origen_item_id: it.id`
   en el flujo de "crear seguimiento") — la UI nueva de la Consola puede seguir la misma forma.
3. **Trazabilidad inversa** (madre → hijos): hoy no hay una query lista para "dame los hijos de
   X" (el índice en `origen_item_id` ya lo soporta: `RoadmapItem::where('origen_item_id', $id)`),
   pero no existe un accessor/scope nombrado. Fase 2 debería agregar
   `RoadmapItem::scopeHijosDe($query, int $madreId)` (trivial, aditivo) para que la UI de la
   Torre pueda mostrar "esta orden generó estos N items" con sus estados en vivo.
4. **Cierre de la madre:** no hay guardarraíl automático hoy (nada cierra el item de
   `seguimiento` cuando el nuevo se completa). Para la Consola del Supervisor, dado que la madre
   representa una orden con N hijos, Fase 2/3 debería decidir si la madre se marca
   `completado` automáticamente cuando **todos** sus hijos llegan a estado terminal
   (`completado`/`cancelado`/`rechazado`) — o si queda manual. Recomendación: automático pero
   solo agregando estado (no ejecutando nada), análogo a como `MergeRunner` solo actualiza
   estado tras verificar — bajo riesgo, aditivo, reversible.

---

## Qué NO se construyó en esta Fase 1 (y por qué es correcto)

Conforme al `prompt` del item: esta Fase 1 es auditoría + diseño. No se tocó:
- Ningún archivo de `app/Modules/Addons/Roadmap/` (Controllers/Services/Console/migrations).
- Ninguna migración de permisos nueva.
- Ninguna ruta nueva.
- Ningún componente Vue de la Torre.

Fases 2 y 3 quedan como **items futuros separados** de la Hoja de Ruta (a crear cuando Irving
apruebe este diseño), cada uno con su propio nivel de riesgo — Fase 2 (intake + creación de
sub-items) es previsiblemente B/C por tocar permisos y creación de items desde IA; Fase 3
(despacho inmediato) es la más sensible (lanza procesos) y debería ir acotada con el guard de
RAM y el permiso dedicado descritos en §(a)/§(c) desde su primer commit, no como un añadido
posterior.

## Preguntas abiertas para Irving antes de aprobar Fase 2

1. `circuito.supervisor.ordenar`: ¿solo `super-administrator`, o también `DESARROLLADOR` (como
   el resto de los permisos del circuito)? El item dice "solo super-administrator" — tomado
   literal en este diseño, pero es una decisión de Irving, no asumida por default.
2. RAM mínima libre (`circuito.ram_minima_libre_mb`): ¿qué valor conservador fijar en la caja de
   dev (4 cores/17GB)? Necesita medirse con el circuito corriendo a `paralelismo` alto.
3. Cierre automático de la madre cuando todos los hijos terminan: ¿sí o queda manual?
4. ¿La madre (orden) es visible en la bandeja normal de items, o necesita su propia sección en
   la Torre (fuera del flujo de items ejecutables)?
