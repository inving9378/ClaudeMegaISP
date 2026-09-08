# MR-28 (#964) — Retiro del módulo perdedor: NO se ejecuta (verificación 2026-09-07)

## Contexto

El prompt de #964 (MR-28) es explícito: "Solo se ejecuta con la decisión de MR-27 tomada por
Irving y escrita en el item." Esa decisión no existía cuando #964 se aprobó (2026-09-03) —
dependía de que #963 (MR-27) llenara la rúbrica de MR-29 con evidencia real y de que Irving
ratificara la conclusión.

## Qué se verificó en esta vuelta

1. **La decisión de MR-27 ya existe y ya la escribió Irving.** #963 (MR-27) cerró con la rúbrica
   de MR-29 (#967) llenada contra la BD real: los 6 criterios reprueban o no son medibles, todos
   por la misma causa raíz (MR-05/#941 nunca corrió, `mapared_*` casi vacío:
   `mapared_devices`=9 vs `map_devices`=8785). Esa conclusión se llevó a Irving en el item
   [RESPUESTA] #9990446, pregunta q1: **"¿Ratificar la recomendación de MR-27 (no retirar Mapa de
   Red y activar MR-30...)?"** — Irving aprobó la **Opción 1 (recomendada)**: *"Ratificar 'no
   retirar' y aprobar activar MR-30"*. Queda registrado en el log de #9990446
   (`2026-09-07T06:58:23-06:00 · irving:CARLOS · aprobar`, `opcion_elegida` = la opción 1) y
   ejecutado (`merge_commit 88d2c4f0...`).
2. **MR-30 (#968, contingencia) ya está activada y cerrada** (`completado`), con su propio
   reporte formal contra la épica #936 (#9990447), que dice explícitamente: *"MR-28 (retiro,
   #964) sigue sin ejecutarse — correcto, es justo lo que pide la contingencia."*
3. **Estado real de `module_registry` — ambos módulos siguen activos** (comparado 2026-09-07):
   ```
   id=16 addon-mapas    (Mapas, legacy)   active=1
   id=51 addon-mapa-red (Mapa de Red, nuevo) active=1
   ```
   Ninguno fue ocultado ni marcado `keep_data`. Es el estado correcto según la decisión ratificada.

## Conclusión

La decisión de MR-27, ya tomada por Irving y escrita en el item (#9990446, q1), es **NO retirar
ningún módulo** — mantener ambos (`Mapas` legacy con datos reales, `Mapa de Red` nuevo en beta) y
activar la contingencia MR-30 en su lugar. Por lo tanto el gate del propio prompt de #964 nunca
se cumple en el sentido de "ejecutar el retiro": la condición que habilitaría el Tiempo 1
("decisión de MR-27 escrita") sí se cumplió, pero su contenido es explícitamente lo contrario a
retirar. **Tiempo 1 y Tiempo 2 de MR-28 NO se ejecutan.** El estado correcto de `module_registry`
(ambos módulos activos) ya es el estado real verificado — no hay nada que cambiar.

Esto no es una premisa incorrecta del item en sí (el gate estaba bien escrito), sino que la
decisión que el gate esperaba ya se resolvió en sentido "no ejecutar". #964 se cierra
documentando esa verificación, sin tocar código ni `module_registry`.

## Qué sigue pendiente (fuera de alcance de #964)

- #941 (MR-05, copia legacy→`mapared_*`) sigue siendo la causa raíz de 4/6 criterios reprobados
  — es el paso real que permitiría una futura re-evaluación de MR-27/MR-29.
- Cualquier retiro futuro del módulo perdedor requeriría una nueva ronda de MR-27/MR-29 con datos
  reales, y una nueva decisión explícita de Irving — no se reabre este item para eso.
