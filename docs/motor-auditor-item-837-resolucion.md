# Resolución del item #837 — "Motor Auditor auto-encadenado" (premisa incorrecta)

El item #837 pedía construir desde cero el **motor auditor auto-encadenado del Circuito CC**:
migración (`tipo`/`origen_item_id`/`hallazgo_firma`/`auditoria_ciclo`), un
`RoadmapAuditorService`, un comando `roadmap:auditar` y un `Observer` que, al completarse
**cualquier** item, dispara una nueva auditoría y crea los items faltantes — con freno, cap por
corrida y dedupe por firma.

**Esa premisa es incorrecta: ese motor ya existe y está vivo, bajo el item #559** ("Motor de
Auditoría Continua"). Verificado en esta sesión (2026-08-20):

## 1. El motor ya existe, con el mismo diseño

| Pieza pedida por #837 | Ya existe como |
|---|---|
| `RoadmapAuditorService` | `app/Modules/Addons/Roadmap/Services/AuditorService.php` |
| Comando `roadmap:auditar --dry-run --max=` | `app/Modules/Addons/Roadmap/Console/AuditorCommand.php` (`circuito:auditor`, dry-run por default) |
| Columna `hallazgo_firma` (dedupe) | Columna `auditor_fingerprint` (migración `2026_08_08_190000_add_auditor_fingerprint_to_roadmap_items.php`), mismo rol |
| Columna `origen_item_id` | Ya existe en `roadmap_items` / modelo `RoadmapItem` |
| Cap por corrida (freno anti-inundación) | `config/circuito.php` → `auditor.cap_por_ciclo` (default 10) |
| Kill-switch dedicado | `circuito.auditor.enabled` (`CIRCUITO_AUDITOR` en `.env`) + honra el kill-switch global `circuito_pausado` |
| Detección de "qué falta" | 6 detectores (`hueco_ruteado`, `enlace_roto`, `todo`, `andamiaje`, `sin_clasificar`, `semilla`), documentados en `docs/motor-auditoria.md` |

Documentación completa: `docs/motor-auditoria.md`.

## 2. El disparo NO es "por observer en cada completado" — es más seguro

#837 pedía un `RoadmapItemObserver::updated()` que dispara en **cada** item que pasa a
`completado`, con debounce (30 min) y anti-bucle (`tipo='auditoria'` no re-dispara) codificados a
mano en el observer.

El motor #559 en cambio va enganchado **dentro de `circuito:scheduler`** (el único despachador,
corre cada minuto por cron) y sólo escanea si se cumplen **todas** estas condiciones:

```
motor encendido       (circuito.auditor.enabled)
Y circuito sin pausar  (circuito_pausado != 1)
Y cola < umbral        (auditor.umbral_cola, default 3 — mide reclamables reales, no "aprobados en bruto")
Y pasó el intervalo    (auditor.min_intervalo_minutos, default 15)
```

Esto logra el mismo objetivo que pedía #837 (que el circuito nunca se quede sin trabajo cuando
hay huecos reales) con una condición **más fuerte** que "cualquier completado": mide la cola
*reclamable* (misma puerta que usa el scheduler, `RoadmapCircuitoService::ejecutablesParalelo()`),
no un conteo ingenuo. El caso real que motivó esa distinción: al 2026-08-08 había 87 items en
`aprobado_irving` y 0 reclamables — un disparador que sólo mirara "hay items aprobados" se habría
disparado sin necesidad.

## 3. Construir el motor de #837 habría duplicado infraestructura viva

Si se hubiera implementado el prompt de #837 tal cual, habría **dos sistemas** escribiendo items
nuevos en la Hoja de Ruta con esquemas de dedupe distintos (`hallazgo_firma` vs
`auditor_fingerprint`) y dos triggers de disparo distintos (observer-por-completado vs
scheduler-por-cola) — compitiendo o pisándose sobre la misma tabla `roadmap_items`.

## Historial de la decisión

- 2026-08-19: escalado a Irving (`consulta_supervisor`) con dos opciones — A) cerrar sin código
  nuevo, documentando; B) agregar solo un disparo-por-evento sobre el motor #559 existente.
- 2026-08-20 16:10: Irving aprueba (`aprobado_irving`) sin fijar `opcion_elegida` explícita ni
  comentario.
- 2026-08-20/21 (esta sesión, wt-1): re-verificado contra el código real que #559 sigue vivo,
  `enabled=true` por default, con cap/kill-switch/dedupe operando. Se opta por la **Opción A**
  (recomendada en el brief original): cerrar #837 sin código nuevo — construir la Opción B
  (observer adicional que invoque `circuito:auditor` por-evento) sumaría un segundo camino de
  disparo sin necesidad real, ya que el gating por cola+intervalo del scheduler ya evita que las
  terminales se queden ociosas sin añadir el riesgo de un disparo-por-completado sin cap
  compartido. Decisión reversible: si en el futuro se detecta que el intervalo de 15 min deja
  huecos reales, ajustar `auditor.min_intervalo_minutos`/`auditor.umbral_cola` es un cambio de una
  línea — no requiere el observer nuevo.

## Conclusión

Sin cambio de código funcional (nada que arreglar en el motor). Este documento y el cierre del
item #837 son la única entrega.
