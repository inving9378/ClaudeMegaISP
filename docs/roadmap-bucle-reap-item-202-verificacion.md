# Item #202 — cierre del bucle reap sobre paraguas ya descompuesto (Expediente RH — Hijo D)

## Contexto

`#202` ("Expediente RH — Hijo D: paquetes de documentos por puesto y generacion automatica al
alta") es sub-item de seguimiento de `#191`. Depende de los Hijos B (`#200`, motor de plantillas)
y C (`#201`, conversión de las 11 plantillas RH) — dependencia real, no de estilo.

Una vuelta previa (`wt-1`, 2026-09-03 09:00) ya hizo el diagnóstico correcto:

1. Corrió `circuito:cabida 202` → **NO CABE** (`ya_timeouteo_antes`; el item había timeouteado a
   los 600s sin commits en su intento anterior, 2026-09-03 07:29).
2. Verificó que la contradicción de spec original (escalada el 2026-08-28 por `wt-2`: "el spec pide
   generar documentos con un motor y plantillas que hoy no existen") ya estaba resuelta — `#200` y
   `#201` habían cerrado y mergeado a `main` (`188942e1`) entre el 2026-08-28 y el 2026-09-03.
3. En vez de picar código directo, descompuso el trabajo real en 3 sub-items secuenciales con spec
   precisa (tablas/servicios/hooks ya localizados en el código):
   - **#870** — D1: modelo + pantalla de asignación de paquete de documentos por puesto.
   - **#871** — D2: motor de generación automática al alta.
   - **#872** — D3: enlace Flotas/Inventario para completar documentos pendientes (responsiva de
     vehículo/herramienta) sin recapturar datos.
4. Reportó la decisión (`circuito:reportar --tipo=decision`, log `2026-09-03 09:00`).

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca terminó el intento de
cerrar `#202` (el proceso terminó sin ejecutar el cierre). El item se quedó reclamado por el
`worker_sid` de esa sesión sin liberar el claim. El reaper de huérfanos (`reaper-rapido`) lo vio con
el slot libre y lo escaló (`huerfano_escalado`, `reap_count=5`, 2026-09-03 09:04) tras 5 reclamos
fallidos acumulados a lo largo de varios días — mismo síntoma exacto que
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`: un paraguas correctamente descompuesto que nunca recibió
el intento de cierre que activa el guard de "no completar mientras queden hijos abiertos". Irving
volvió a aprobarlo dos veces más (08:56 y 10:24) sin que nadie ejecutara ese paso.

## Verificación de esta vuelta

- Query directa: los 3 hijos (`origen_item_id=202`) — `#870`, `#871`, `#872` — existen, todos
  `estado_aprobacion=aprobado_irving`, `status=pending`, sin `worker_sid` (sin reclamar). Ninguno se
  tocó.
- Intento de cierre: `RoadmapItem::find(202)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (bloque "(2b) PARAGUAS", `RoadmapItem.php`) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true`, y agregó al log el evento `paraguas_abierto`
  ("le quedan 3 sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el
  último de ellos cierre.", `subitems_abiertos=3`). Confirmado leyendo `$item->log` tras el save.

## Resultado

`#202` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
sus 3 hijos cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente y
verificado en las sesiones anteriores de este mismo bug) completa `#202` solo, sin intervención
manual.

**Sin cambio de código de negocio.** El trabajo técnico real del Hijo D (paquetes de documentos por
puesto, generación automática al alta, enlace con Flotas/Inventario) sigue en
`#870`/`#871`/`#872`, esperando triaje/reclamo.
