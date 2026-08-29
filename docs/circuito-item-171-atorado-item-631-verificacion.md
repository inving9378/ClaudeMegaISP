# Item #631 — Item #171 atorado: estado contradictorio (RESUELTO — se resolvió solo antes de tomarlo)

## Contexto

El item #631 (sub-item de seguimiento de #185) reportaba que el item #171 ("Guardrail de
migraciones falla abierto en la ruta web de Ignition") tenía una rama real lista (commits + test),
nunca integrada a `main`, y un **estado contradictorio** en la fila de BD: `estado_aprobacion =
aprobado_revisor` (nivel B autorizado), pese a que el propio revisor (#338) había emitido veredicto
`ESCALA → requiere_irving` por tocar el mecanismo de autenticación/seguridad del guardrail.

La tarea pedida: (1) reconciliar el estado — fijar `estado_aprobacion=requiere_irving` para que
apareciera en la bandeja de Irving, sin auto-aprobarlo; (2) investigar por qué el veredicto de
escalada del revisor no se reflejó en el campo (posible bug puntual de `RevisorService`/
`DestrabeCommand`).

## Hallazgo: #171 ya se resolvió por sí solo, por el camino normal del circuito

Al leer el estado actual de #171 (hoy, 2026-08-29), **ya no está atorado ni contradictorio**:

- `status = "done"`, `estado_aprobacion = "completado"`
- `merge_commit = "bc03c24822994fe37f57aba39197eb4a7fd0339e"`
- `archivado_por = "merge-runner (backend auto)"`, `archivado_at = 2026-08-27T19:35:02Z`

Verificado directamente:

```
$ git log --oneline -1 bc03c24822994fe37f57aba39197eb4a7fd0339e
bc03c248 Integra circuito #171 (circuito/item-171-guardrail-de-migraciones-falla-abierto-e) a main

$ git merge-base --is-ancestor bc03c24822994fe37f57aba39197eb4a7fd0339e main
ES ANCESTRO DE main local
```

Y el código del fix está presente en `app/Providers/AppServiceProvider.php:162-170` (fuerza
`config(['ignition.enable_runnable_solutions' => false])` atado a
`MigrationGuardService::shouldEnforce()`, sin depender de la línea `.env` que se perdió el 24-ago).

## Qué pasó entre el diagnóstico de #631 (26-ago) y hoy

El log de #171 muestra la secuencia completa:

1. El revisor (#338) escaló #171 con veredicto `ESCALA → requiere_irving` por la palabra
   "seguridad" en la descripción — el disparador que motivó la creación de #631.
2. `circuito:destrabe` (Opus, mecanismo **#338**, ver `DestrabeCommand.php`) re-triajeó el item
   **dos veces** (26-ago 15:08 y 27-ago 13:28) y en ambas lo re-aprobó al pool
   (`estado_aprobacion=aprobado_revisor`, categoría `tecnico_seguro`) con la misma razón: *"Endurecer
   un guardrail defensivo (fail-closed) y extender su cobertura a la ruta web de Ignition es
   aditivo, reversible y refuerza seguridad sin modificar permisos existentes, tocar dinero ni
   prod. La palabra 'seguridad' aquí es falso positivo del revisor."*
3. La rama original chocó ~210 veces contra un cambio ya integrado en `main` (`a2a598f7`, decisión
   de Irving sobre fail-open/fail-closed con su propia prueba de regresión). `wt-1` la descartó
   (27-ago 13:33), reconstruyó el item solo con la mitad que faltaba (forzar en código el cierre
   del botón de Ignition) y lo cerró.
4. `merge-runner` integró la rama reconstruida a `main` sin conflicto (`bc03c248`, 27-ago 19:35).

## Respuesta a la segunda parte de #631: ¿es un bug de `RevisorService`/`DestrabeCommand`?

**No.** `DestrabeCommand` (`app/Modules/Addons/Roadmap/Console/DestrabeCommand.php`) es exactamente
el mecanismo diseñado para esto (comentario de cabecera del propio archivo, item #338): *"re-triajea
items de requiere_irving: los TÉCNICOS/seguros (y los falsos positivos de la denylist) vuelven al
POOL para auto-resolverse; los genuinamente de Irving (negocio/dinero/seguridad/prod) quedan con TAG
+ BRIEF de Opus"*. Que `estado_aprobacion` pase de `requiere_irving` a `aprobado_revisor` **es el
comportamiento intencional** cuando Opus juzga que la escalada del revisor fue un falso positivo —
no es una fuga de estado ni una falla de sincronización entre servicios. El "estado contradictorio"
que #631 documentó (aprobado_revisor conviviendo con un comentario viejo de escalada) es justo la
huella de ese re-triaje: el campo SÍ se actualizó, solo que a un valor distinto del que #631
esperaba, porque #631 fue escrito leyendo el comentario del revisor sin ver que el des-trabe ya
lo había revisado y revertido con su propio razonamiento documentado.

## Por qué NO se ejecuta la acción pedida (fijar `requiere_irving`)

Hacerlo ahora sería activamente dañino: #171 ya está `completado`, mergeado a `main` y el código
vive en `AppServiceProvider.php`. Reabrirlo a `requiere_irving` reintroduciría un item ya resuelto
en la bandeja de Irving, apuntando a una rama (`circuito/item-171-guardrail-de-migraciones-falla-
abierto-e`) que ya fue descartada y reconstruida — confundiría el historial sin ningún beneficio.

## Conclusión

#631 no trae trabajo pendiente: el circuito resolvió #171 por su propio camino normal (revisor →
destrabe → reconstrucción → merge) entre el 26 y el 27 de agosto, antes de que este item fuera
tomado. Se cierra sin cambio de código.

**Deuda menor, sin relación con este hallazgo:** queda la rama huérfana ya mergeada
`circuito/item-171-guardrail-de-migraciones-falla-abierto-e` sin borrar (cosmético, no bloquea
nada). Y el campo `comentarios_claude` de #171 aparece cortado a mitad de frase ("La otra mi") en
el comentario de cierre de `wt-1` — posible truncamiento de escritura, curioso pero sin efecto en
el resultado (el item cerró correcto igual). Ninguno amerita su propio item hoy.
