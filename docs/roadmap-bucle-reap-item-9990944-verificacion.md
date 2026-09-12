# Item #9990944 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

Item #9990944 ("Fase B — SupervisorService: aplicar filtrarConDependenciasCerradas() en
listosParaTerminal()/listosParaTerminalTotal()") es sub-item de seguimiento de #9990938,
bloqueado por `depende_de=[9990943]` (Fase A — `RoadmapCircuitoService::filtrarConDependenciasCerradas()`).

## Qué se encontró al llegar a este item

1. **La dependencia ya estaba resuelta.** #9990943 está `completado` con `merge_commit`
   (`012e319b`) — el método público de la Fase A ya existe en `main`.
2. **El item ya había sido correctamente descompuesto** por una vuelta previa (`wt-2`,
   2026-09-11 20:12, log: "circuito:cabida devolvió NO CABE (ya_timeouteo_antes); descompuesto
   en #9990954 (Fase B1: listosParaTerminal()) y #9990957 (Fase B2: listosParaTerminalTotal(),
   depende de B1, mismo archivo)").
3. **Esa vuelta nunca intentó cerrar al padre.** El item se quedó `en_progreso` colgado con el
   `worker_sid` de esa sesión; el reaper lo detectó huérfano 25 minutos después
   (`huerfano_reencolado`, `reap_count=1`) y lo devolvió a `aprobado_revisor`; el pool lo repartió
   de nuevo (a `wt-5`, esta vuelta) sin trabajo propio pendiente — mismo síntoma que la familia de
   items #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/
   #9990408/#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836/#9990856/
   #9990892/#9990896/#9990886/#9990893 ya documentada en `CLAUDE.md`.

## Verificación previa a actuar

Sin tocar código, se confirmó vía tinker que los 2 hijos (`origen_item_id=9990944`) siguen
intactos, sin reclamar:

- **#9990954** (Fase B1) — `aprobado_irving`, `worker_sid` vacío, sin `merge_commit`.
- **#9990957** (Fase B2) — `aprobado_revisor`, `worker_sid` vacío, sin `merge_commit`.

No había rama de trabajo propia de #9990944 en este worktree ni cambios pendientes — la
descomposición original seguía siendo correcta, nadie más la tocó.

## Corrección aplicada

Se ejecutó el intento de cierre faltante:

```php
$i = RoadmapItem::find(9990944);
$i->estado_aprobacion = "completado";
$i->save();
```

El guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo detectó y
reenrutó automáticamente:

```
{"ts":"...","por":"paraguas","evento":"paraguas_abierto",
 "motivo":"Este item se descompuso y le quedan 2 sub-item(s) abierto(s): no se completa.
 Queda como paraguas y cierra solo cuando el último de ellos cierre.",
 "subitems_abiertos":2}
```

Resultado: `estado_aprobacion=aprobado_irving`, `excluir_pool_automatico=true`,
`worker_sid`/`claimed_at` liberados — sacado del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990954 y #9990957 cierren los dos.

## Conclusión

**Sin cambio de código de negocio.** El trabajo técnico real (aplicar el filtro en
`listosParaTerminal()` y `listosParaTerminalTotal()` de `SupervisorService.php`) sigue en
#9990954 y #9990957, pendientes de que una terminal los reclame.
