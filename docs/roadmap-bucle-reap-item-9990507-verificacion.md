# Item #9990507 — bucle reap sobre paraguas ya descompuesto (verificación end-to-end de recuperación de contraseña)

## Contexto

`#9990507` ("Verificar en dev el ciclo completo de recuperación de contraseña
(request→email→token→reset→login)") es un sub-item de seguimiento de `#9990477`. Tras pasar por
la válvula de contexto (aflojada: «permiso» solo aparece en el nombre del módulo clasificatorio,
no como acción) y ser des-trabado por Opus (categoría `tecnico_seguro` — verificación funcional
en dev, sin tocar permisos/roles/credenciales), Irving lo aprobó eligiendo la Opción 1 de la
pregunta estructurada (ejecutar el test end-to-end en dev con usuario dummy, con evidencia por
paso, sin tocar usuarios reales).

El propio log del item muestra que una vuelta previa (`wt-4`, 2026-09-07 09:46) ya hizo lo
correcto:

1. Corrió `circuito:cabida` → **NO CABE**.
2. Verificó que el sibling `#9990506` ("agregar el link") seguía sin `merge_commit`.
3. Descompuso el trabajo en dos fases encadenadas por `depende_de`:
   - **#9990512** — Fase 1: request de reset (link, vista y token generado), con usuario dummy
     ad-hoc.
   - **#9990513** — Fase 2: reset con el token, redirect y login posterior, más limpieza —
     depende de que Fase 1 cierre primero.
4. Dejó la nota de decisión en `comentarios_claude`.

Pero el proceso **murió antes de intentar el cierre** del padre: el log registra a las `09:46:49`
el evento `claim_liberado_al_morir_la_vuelta` (`soltar-claim`, sid `wt-4`) — "La vuelta de wt-4
terminó sin cerrar el item (muerte del proceso: kill, OOM o freno a media vuelta)" — el claim se
liberó y el item volvió a `aprobado_irving` sin que nadie hubiera intentado cerrarlo. El pool lo
repartió de nuevo (a `wt-1`, esta vuelta) sin que hubiera trabajo propio que hacer: la misma
familia de bug ya documentada en
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#852`/`#905`/`#878`/`#906`/`#907`/`#924`/`#9990012`/
`#917`/`#910`/`#936`/`#9990422`/`#9990412`/`#9990484` (y otros).

## Verificación

Consultados los 2 hijos (`origen_item_id=9990507`) directo en BD:

| Item | Título | Estado | Worker | depende_de | merge_commit |
|------|--------|--------|--------|------------|---------------|
| #9990512 | Fase 1 — request de reset: link, vista y token generado | `pendiente_revision` | (sin reclamar) | — | — |
| #9990513 | Fase 2 — reset con el token, redirect y login posterior | `pendiente_revision` | (sin reclamar) | — | — |

Ambos siguen intactos, sin reclamar por ninguna terminal — la descomposición original sigue
siendo correcta y completa, nadie más la tocó ni hace falta re-descomponerla.

## Corrección

Esta vuelta ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'` vía
tinker) sobre `#9990507`. El guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b)
PARAGUAS") lo reenrutó a `aprobado_irving` + `excluir_pool_automatico=true`, liberando
`worker_sid`/`claimed_at` (evento en el log de flags con `excluir_pool_automatico: false→true`),
sacándolo del pool/reaper hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`
aprox.) lo complete solo cuando `#9990512` y `#9990513` cierren los dos.

## Resultado

Sin cambio de código de negocio. El trabajo técnico real de la verificación (request de reset en
Fase 1, y reset+login+limpieza en Fase 2, encadenados por `depende_de`) sigue en `#9990512` y
`#9990513`, ambos `pendiente_revision` y sin reclamar por ninguna terminal.
