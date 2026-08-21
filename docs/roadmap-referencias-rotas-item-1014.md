# Item #1014 — 6 referencias `#NNN` rotas en items cerrados: investigación y causa raíz

Item #1014 detectó que 6 items **cerrados** de la Hoja de Ruta mencionan un `#NNN` en su texto
(`description`/`comentarios_claude`) que no corresponde a ningún `roadmap_items.id` existente.
El item pedía revisar cada referencia por separado en vez de adivinar la causa en bloque. Esta es
esa revisión, con la pregunta original del item — "¿cuáles eran typos y cuáles apuntaban a trabajo
que de verdad nunca se creó?" — ya contestada caso por caso abajo.

**Origen del item: `tipo=manual`** (creado por un auditor/LLM revisando texto, no por un comando
detector en el repo — se buscó un generador automático de este patrón, `grep` cruzado en
`AuditorService`/`ThomasService`/comandos `Active`, y no existe uno que produzca items con este
título; el `#(\d+)` de `ThomasService::referenciasItemEnTexto()` (#967) es una función distinta,
usada solo para dependencias declaradas en la pregunta maestra, no para auditar items cerrados).

## Resultado: 5 de 6 son falsos positivos del propio patrón de detección — no referencian items

El patrón `#NNN` en la prosa de este repo se usa para **cualquier entidad numerada**, no solo
`roadmap_items`: ids de `clients`, de `invoices`/proformas, de `deployment_logs`, de
`roadmap_item_reports` (el output de `circuito:reportar`, que literalmente imprime
`Reporte #{id}`). Un grep de `#(\d+)` contra `roadmap_items` sin distinguir el namespace de la
entidad los confunde a todos con referencias cruzadas de la Hoja de Ruta. Solo 1 de los 6 casos
(#419) referenciaba de verdad otros `roadmap_items`.

### #87 → "#7501" — FALSO POSITIVO (id de cliente, no de item)

Texto original: *"Detectado al validar bypass Mikrotik con **cliente #7501**"*.
`#7501` es `clients.id = 7501`, el cliente usado como caso de reproducción al diagnosticar el
bug de `TypeError` del shim `App\Models\Client`. Nunca fue una referencia a un item de la Hoja
de Ruta — no había ningún `roadmap_items.id=7501` que crear ni que perder.

### #194 → "#71389" — FALSO POSITIVO (id de proforma/factura, no de item)

Texto original: *"REGRESIÓN confirmada read-only (**proforma #71389**, cli 661, period
2025-09, pending $349)"*. `#71389` es `invoices.id = 71389` (la proforma de adeudo usada como
evidencia de la regresión de saldado FIFO). Tampoco es una referencia a la Hoja de Ruta.

### #419 → "#422" y "#423" — ÚNICO CASO REAL, y NO es un typo ni trabajo perdido

Estos SÍ fueron `roadmap_items` reales. El propio log de #419 lo documenta: *"Verificado en
DRY-RUN: **#422** benigno->B, **#423** fiscal->C (match factura), **ambos de prueba
HARD-DELETE**"*. Se crearon a propósito como datos de prueba para validar `RevisorService::
triarNivelNull()` (el triaje de items con `nivel_riesgo=NULL`, commit `a827f3ce`) y se borraron
a propósito (hard-delete) una vez verificado el comportamiento — igual que se limpia cualquier
fixture de prueba. No es un typo (el número es correcto, apuntaba a items que existieron) ni es
"trabajo que nunca se creó" (se creó, cumplió su función de prueba, y se retiró intencionalmente).

### #529 → "#56" — FALSO POSITIVO (id de deployment_log, no de item)

Texto original: *"Evidencia en **deployment_logs #56**/#57/#58/#59 (V1.26-V1.29)"*. `#56` es
`deployment_logs.id = 56`. El propio texto ya aclara la tabla (`deployment_logs`); el `#NNN`
genérico es lo único que lo hace parecer una referencia de Hoja de Ruta.

### #957 → "#1296" — FALSO POSITIVO (id de `roadmap_item_reports`, no de `roadmap_items`)

Texto original: *"Decisión: reusar heartbeat #808 en vez de tabla nueva (ver **reporte de
decisión #1296**)"*. `#1296` es un `roadmap_item_reports.id` — la fila que dejó el propio
`circuito:reportar --tipo=decision` al registrar esa decisión (`ReportarItemCommand::handle()`
imprime literalmente `"Reporte #{$r->id} agregado al item #{$item->id}"`). Nótese que en el mismo
texto conviven dos namespaces distintos con el mismo prefijo `#`: `#808` SÍ es un
`roadmap_items.id` real (el heartbeat del item #808, existe), y `#1296` es un
`roadmap_item_reports.id` — casualmente ambos parsean igual con `/#(\d+)/`.

## Conclusión / por qué no hay fix de código

Ninguno de los 6 casos es un bug de datos de la Hoja de Ruta ni una pérdida de trabajo: 5 son
lectura ambigua de un patrón de texto compartido entre varios namespaces de ids (`clients`,
`invoices`, `deployment_logs`, `roadmap_item_reports`), y el sexto (#419) es limpieza de
fixtures de prueba, correcta y esperada. No hay nada que reparar en `roadmap_items` ni en los
items citados — se cierra como hallazgo documentado, sin cambio funcional.

**Nota para futuras auditorías de este tipo** (si alguna vuelve a cruzar `#NNN` contra
`roadmap_items.id`): antes de reportar como "referencia rota", vale la pena mirar el contexto
inmediato del texto (palabra anterior: "cliente", "proforma"/"factura", "deployment_logs",
"reporte") para descartar estos cuatro namespaces conocidos antes de asumir que apunta a la Hoja
de Ruta.

## Cómo verificar

```bash
grep -n "cliente #7501" -A2 -B2 <<< "$(php artisan tinker --execute='echo \App\Modules\Addons\Roadmap\Models\RoadmapItem::find(87)->description;')"
php artisan tinker --execute='echo \App\Models\Client::find(7501)?->id ?? "no existe";'          # cliente real, no roadmap item
php artisan tinker --execute='echo \App\Models\Invoice::find(71389)?->id ?? "no existe";'         # proforma real, no roadmap item
php artisan tinker --execute='echo \DB::table("deployment_logs")->find(56)?->id ?? "no existe";'  # deployment_log real
php artisan tinker --execute='echo \App\Modules\Addons\Roadmap\Models\RoadmapItemReport::find(1296)?->id ?? "no existe";' # report real
php artisan tinker --execute='echo \App\Modules\Addons\Roadmap\Models\RoadmapItem::find(422)?->id ?? "no existe (hard-deleted a propósito)";'
```
