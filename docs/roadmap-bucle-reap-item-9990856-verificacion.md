# Item #9990856 — CIRC-02 (paraguas): se completa el intento de cierre faltante

## Contexto

Item #9990856 es un PARAGUAS explícito. Su propio `title` lo dice ("PARAGUAS") y su `prompt`
es tajante: *"PARAGUAS — no lo trabajes directo. Este item agrupa CIRC-02a, CIRC-02b y CIRC-02c
(se ejecutan en ese orden)... Cierra por cascada cuando cierren sus tres sub-items."*

Objetivo real del feature (para contexto, vive en los hijos): que escribir un comentario de
Irving en un item `requiere_irving` sea, por sí solo, el acto de responder — sin segundo clic
para pasar el item a `en_progreso`, sin que la escritura pise `comentarios_claude` sin
historial. La respuesta se guarda en tabla propia, re-encola el item, y la siguiente vuelta la
recibe con precedencia sobre el prompt original.

El revisor (#338) escaló el paraguas a `requiere_irving` por tocar arquitectura de decisión del
propio circuito (regla de precedencia, re-encolado, flujo rechazado/completado). Irving aprobó
la única pregunta estructurada (q1: "marcarlo como PARAGUAS no-ejecutable, dejar que cierre por
cascada, el circuito solo trabaja los sub-items" — opción recomendada, confianza alta,
reversible). El autopilot lo dejó `aprobado_revisor`.

## Lo mismo que ya se documentó ~30 veces en CLAUDE.md

Mismo patrón exacto que la familia #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/
#9990012/#917/#910/#936/#9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/
#9990836 (todas documentadas en CLAUDE.md bajo "HOJA DE RUTA — Items pendientes"): un item
paraguas ya fue descompuesto correctamente en sub-items por una vuelta previa, pero esa vuelta
nunca ejecutó el intento de cierre (`estado_aprobacion = 'completado'`) que dispara el guard de
paraguas del modelo. Sin ese intento, el item se queda `en_progreso` colgado (o vuelve a
`aprobado_revisor`/`aprobado_irving` vía reaper) y el pool lo reparte una y otra vez sin que haya
trabajo propio que hacer — el trabajo real ya vive en los hijos.

## Verificación en esta vuelta

Los 3 sub-items (`origen_item_id=9990856`) ya existían, creados por una sesión previa, intactos
y sin que esta vuelta los tocara:

| Item | Título | Estado | Dueño |
|------|--------|--------|-------|
| **#9990857** | CIRC-02a — Diagnóstico: cuántas respuestas de Irving se han perdido (SOLO LECTURA) | `en_progreso` | `wt-3` (no se toca — aislamiento #334) |
| **#9990858** | CIRC-02b — Mecanismo: hilo de respuestas (`roadmap_item_respuestas`) + re-encolado automático | `requiere_irving` | sin reclamar |
| **#9990859** | CIRC-02c — Visibilidad: hilo de respuestas en la Torre + watchdog de respuestas sin consumir | `pendiente_revision` | sin reclamar |

La descomposición original seguía siendo correcta y completa (cubre diagnóstico, mecanismo y
visibilidad, en el orden que el propio prompt pide). Solo faltaba el paso de bookkeeping.

## Corrección

Se ejecutó el intento de cierre faltante:

```php
$i = \App\Modules\Addons\Roadmap\Models\RoadmapItem::find(9990856);
$i->estado_aprobacion = 'completado';
$i->save();
```

El guard `RoadmapItem.php` bloque "(2b) PARAGUAS" (~línea 389-419) lo detectó
(`tieneSubItemsAbiertos()` = true, 3 abiertos) y lo reenrutó automáticamente:

- `estado_aprobacion`: `completado` → **`aprobado_irving`**
- `excluir_pool_automatico`: `false` → **`true`**
- `worker_sid`/`claimed_at`: limpiados (liberado, no queda pegado)
- log: evento `flags` registrado con el cambio

Sacado del pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo
complete solo, cuando #9990857, #9990858 y #9990859 cierren los tres.

## Resultado

**Sin cambio de código de negocio** — el trabajo técnico real del mecanismo de respuestas
(diagnóstico, tabla `roadmap_item_respuestas` + re-encolado, visibilidad en Torre + watchdog)
sigue en #9990857/#9990858/#9990859, pendientes de que sus dueños los cierren.
