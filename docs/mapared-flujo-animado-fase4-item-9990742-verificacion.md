# MR flujo animado Fase 4 — conectar a la potencia óptica real (item #9990742)

**Fecha:** 2026-09-11
**Alcance:** verificar el bloqueo de datos ANTES de picar código (spec explícito del item), sin
simular datos falsos si el bloqueo sigue vivo.

## 1. Estado de la dependencia (Fases 1-3)

- **Fase 1** (`#9990739`, wiring + feature flag) → `completado`, mergeada (`3715476251dc267c`).
- **Fase 2** (`#9990740`, los 4 estados simulados a mano) → `aprobado_irving`, **ya mergeada**
  (`f4767645cd1ee9eb`, integrada esta misma sesión de circuito).
- **Fase 3** (`#9990741`, guardas de rendimiento + botón congelar) → `aprobado_irving`, **con rama
  creada pero SIN `merge_commit` todavía** (`circuito/item-9990741-mr-flujo-animado-fase-3-...`,
  sin `worker_sid` — nadie la tiene tomada ahora mismo).

Es decir: la dependencia declarada por el propio título ("depende de que Fases 1-3 estén
mergeadas") **no está completa** — falta Fase 3. Pero esto es secundario frente al bloqueo real
de datos (§2), que impide la Fase 4 sin importar si Fase 3 ya mergeó o no.

## 2. Verificación del bloqueo de datos — SIGUE BLOQUEADO

```
mapared_enlaces_servicio: 0 filas
olt_onus: 2954 filas (sí tiene datos)
```

Confirmado con `SELECT COUNT(*)` directo contra la BD de dev. Este es el **mismo hallazgo** ya
documentado tres veces antes para esta misma tabla:

- `docs/mapared-comparativa-item-963-verificacion.md` (agosto 2026)
- `docs/mapared-mr16-fase2b-item-9990496-verificacion.md` (2026-09-07)
- Auditoría PASO 0 de `#9990733` (padre de este item, 2026-09-10) — citada textualmente en el
  propio spec de `#9990742`.

Reverificado ahora (2026-09-11): sigue en 0 filas. Nadie corrió el backfill real
(`mapared:backfill`, sin `--dry-run`) desde la última vez que se documentó.

**Consecuencia concreta para la Fase 4:** sin `mapared_enlaces_servicio` poblada no existe
asociación real NAP→ONT→cliente en la BD. `olt_onus` sí tiene señal óptica cacheada (2954 filas,
alimentada por `smartolt:sync-critical` cada 10 min, vía `OLTsService::getSignalAndStatus` — la
fuente que la decisión q2 del item ya fijó como correcta), pero **no hay forma de saber a qué
enlace/route del mapa corresponde cada fila de `olt_onus`** sin la tabla de enlaces de servicio
que los une. Conectar el flujo animado a un ONT "cualquiera" (sin el enlace real que lo ata a la
route dibujada en el mapa) sería exactamente el dato falso disfrazado de real que el propio
prompt del item prohíbe.

## 3. Decisión aplicada (ya tomada por Irving, q3 → opción 1 recomendada)

> "Abortar la Fase 4 y escalar a Irving con reporte del estado del bloqueo — Pro: no expone datos
> sensibles, respeta la frontera de seguridad/permisos."

Esta vuelta ejecuta exactamente eso: no se tocó `mapUtils.js` ni `LeafletMapRed.vue`, no se
simularon IDs de enlace falsos, y se deja este reporte para que Irving lo revise en la Hoja de
Ruta. La Fase 4 real solo puede continuar cuando `mapared_enlaces_servicio` tenga filas reales
(lo cual depende de que se corra `mapared:backfill` de verdad y/o se den de alta enlaces reales
vía la UI de `EnlacesServicioController`, ninguno de los dos en alcance de este item).

## 4. Dónde engancharía la Fase 4 cuando el bloqueo se resuelva (para no repetir la investigación)

- Punto de estado por route: `flujoAnimadoConfig`/`isFlujoAnimadoPilot()` en
  `resources/js/components/module/mapared/helper/mapUtils.js:369-397` — hoy resuelve un único
  "piloto" (`pilotRouteId` o el primer route con `coords`), fijo en `est-ok` vía
  `FLUJO_ANIMADO_PILOT_CLASSES`.
- Regla ya fijada por el prompt original (no reabrir): el estado se aplica SIEMPRE con
  `classList` sobre `layer.getElement()`, nunca redibujando la capa completa.
- Umbrales dBm ya fijos por decisión #5 del item padre: -8 a -25 = `ok`, -25 a -27 =
  `degradado`, -27 a -29 = `critico`, sin señal/LOS = `caido`.
- Fuente de la señal ya fijada por decisión q2: `olt_onus` (cacheada, sync cada 10 min vía
  `smartolt:sync-critical`), **NO** consultas SNMP nuevas en vivo.
- Lo que falta y bloquea todo lo anterior: el join real `mapared_enlaces_servicio` → `olt_onus`
  (o el ONT/serial que las una) para saber qué route del mapa corresponde a qué fila de
  `olt_onus`. Sin eso, no hay "potencia real de ESTE enlace" que mapear.

## 5. Validación con screenshot — no aplica

El propio item pedía dejar explícito que faltaba "VALIDAR CON SCREENSHOT de Irving (un NAP real
con clientes en distintos estados)". Esa validación no aplica porque no hay NAP real con
clientes asociados en la BD (§2) — no hay nada que capturar todavía.

## Conclusión

Bloqueo de datos real y confirmado, no una duda de implementación. Item cerrado documentando el
hallazgo (mismo patrón que `#963`/`#9990496`/`#9990471`), sin cambios de código de aplicación.
Retomar cuando `mapared_enlaces_servicio` tenga filas reales.
