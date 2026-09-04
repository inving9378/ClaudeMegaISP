# Item #984 — cierre del bucle reap sobre paraguas ya descompuesto (29 llamadas a env() fuera de config/)

## Contexto

`#984` (generado por el Motor de Auditoría Continua, #559) pedía mover las 29 llamadas a `env()`
en tiempo de ejecución fuera de `config/*.php` a claves de `config/<archivo>.php`, mismo cambio
mecánico ya aplicado en #792/#793. Una vuelta previa (`wt-1`, 2026-09-03 16:10) ya hizo el trabajo
correcto:

1. Corrió `circuito:cabida` → **NO CABE** ("ya timeouteó antes").
2. Descompuso las 29 llamadas en 6 sub-items por clúster archivo/config destino, cada uno con su
   spec ya investigada (archivo:línea + config destino):
   - **#1000007** — Scripts (`DB_*`, Mikrotik dev) + DevTools (`TTYD_URL`) + Deploy
     (`SSH_KEY_PASSPHRASE`), 10 hallazgos. `requiere_irving`.
   - **#1000008** — Mail (`MAIL_VENTAS_FROM_*`, `MAIL_FROM_ADDRESS` x2) + `APP_DEBUG`, 5 hallazgos.
     `aprobado_revisor`.
   - **#1000009** — WhatsApp gateway (`AuditController` + `ApiIntegrationService`), config ya
     existe, 4 hallazgos. `requiere_irving`.
   - **#1000010** — GPS listener IP + Replicate token + `FCM_SERVER_KEY` (2 módulos) + title-meta,
     5 hallazgos. `requiere_irving`.
   - **#1000011** — cliente HTTP SmartOLT (`VERIFY_SSL`/`PROXY`) en `AppServiceProvider` +
     `OLTsConfigController`, 4 hallazgos. `aprobado_revisor`.
   - **#1000012** — `UsesApiIntegration` clave dinámica (caso especial, no mecánico), 1 hallazgo.
     `requiere_irving`.

   Total 29/29 hallazgos cubiertos (10+5+4+5+4+1=29).

Esa parte fue correcta y **no se repite**. Lo que faltó: esa misma vuelta nunca intentó **cerrar**
`#984` después de crear los sub-items — en cambio, timeouteó (`max_turns`, sin commits en la rama)
y escaló a `requiere_irving`; Irving aprobó (`irving:CARLOS`, 16:07:47) sin que nadie retomara el
cierre. El ítem quedó `en_progreso` sin que se liberara el claim. El reaper de huérfanos
(`reaper-rapido`) lo vio con el slot `wt-1` libre y lo re-encoló a `aprobado_irving`
(`reap_count=2`), y el pool lo repartió de nuevo (a esta misma terminal) sin que hubiera trabajo
propio que hacer — mismo síntoma exacto que `#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#905`/
`#878`/`#906`/`#907`: un paraguas correctamente descompuesto que nunca recibió el intento de
cierre que activa el guard de "no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `#1000007`–`#1000012` (`origen_item_id=984`) siguen intactos, sin `worker_sid` —
  ninguno fue tocado por nadie más, la descomposición original seguía siendo la correcta.
- Intento de cierre: `RoadmapItem::find(984)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a `aprobado_irving` +
  `excluir_pool_automatico=true`, agregando al log el evento `paraguas_abierto` ("le quedan 6
  sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el último de
  ellos cierre"). Confirmado leyendo `$item->log` tras el save (evento `paraguas_abierto` seguido
  del evento `flags` que audita el cambio de `excluir_pool_automatico`).

## Resultado

`#984` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
los 6 sub-items cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente y
verificado en las sesiones anteriores de este mismo bug) completa `#984` solo, sin intervención
manual.

**Sin cambio de código de negocio.** El trabajo técnico real (mover las 29 llamadas a `env()` a
`config/*.php`) sigue en `#1000007`/`#1000009`/`#1000010`/`#1000012` (`requiere_irving`) y
`#1000008`/`#1000011` (`aprobado_revisor`, listos para que otra terminal los tome).
