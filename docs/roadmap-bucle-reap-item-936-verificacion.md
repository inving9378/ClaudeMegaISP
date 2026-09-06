# Item #936 — bucle reap sobre el paraguas raíz de la épica MAPA DE RED (MR-00)

## Contexto

#936 (MR-00) es el item paraguas raíz de toda la épica "módulo MAPA DE RED": su propio `prompt`
es explícito — "No se ejecuta. Es el contenedor de MR-01 a MR-28... Este item es un PARAGUAS: no
tiene trabajo propio. No lo reclames para 'hacerlo'. Cierra solo, por el hook de cascada del
modelo (`RoadmapItem::saved`), cuando el último de sus sub-items cierre." El propio prompt cita
la lista de items previos que ya sufrieron este mismo bug: #738, #745, #830, #816, #818, #848,
#878.

## Cómo llegó a reclamarse pese a la advertencia

El log del item muestra la secuencia real:

1. `2026-09-04 01:10` — `circuito:liberar-cascada-mapa-red` detuvo la cascada por un item
   atascado (#939 llevaba 189 min abiertos), dejando el techo en #943 (MR-07) y todo lo
   posterior (#944 en adelante) sin liberar.
2. `2026-09-06 16:10:35` — directiva explícita de Irving (`destapado_mapa`): el driver
   `#9990366` (subir el techo de la cascada) había cerrado en falso ("no registró rama de
   trabajo"), así que los items aprobados quedaron parqueados sin que la cascada los soltara.
   Irving pidió construir el mapa completo sin pacing y **quitó `excluir_pool_automatico` de
   #936** ("el orden lo sigue guardando `depende_de`").
3. Minutos después (`2026-09-06 22:10:51`) el pool reclamó **#936 mismo** (no sus hijos) —
   con `excluir_pool_automatico=false` y `estado_aprobacion=aprobado_irving`, el paraguas volvió
   a ser un candidato normal de dispatch, exactamente el mismo bug ya documentado 7 veces.

## Por qué la directiva de Irving no necesitaba tocar al padre

Verificado contra la BD real: cada hijo de #936 (`origen_item_id=936`) tiene su **propio**
`excluir_pool_automatico`, independiente del padre. La mayoría de los hijos liberados por la
cascada (MR-04, MR-07 en adelante) ya tenían `excluir_pool_automatico=false` desde antes de la
directiva — son ellos, no el padre, los que el pool debe considerar. Solo MR-33/34/35
(`#9990288/#9990289/#9990290`) siguen con `excluir_pool_automatico=true` **a propósito** (así lo
dice la propia `description` de #936: "Los tres nacen aprobados por Irving y CON FRENO"). El
padre nunca debió ser el objetivo de "destapar" — al quitarle el freno quedó expuesto a
reclamarse a sí mismo.

## Estado de los hijos al momento de esta verificación

29 sub-items siguen abiertos (de 33 no-paraguas + MR-32/MR-36 ya cerrados): MR-05 en
`aprobado_revisor`, MR-07/MR-08/MR-09/MR-10 en `en_progreso`, MR-11 a MR-35 en `aprobado_irving`
(la mayoría con `excluir_pool_automatico=false`, ya elegibles para el pool; MR-33/34/35
intencionalmente congelados). Nada de esto se tocó.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'`). El guard de
paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo detectó y reenrutó automáticamente:

```json
{"ts":"2026-09-06T16:11:29-06:00","por":"paraguas","evento":"paraguas_abierto",
 "motivo":"Este item se descompuso y le quedan 29 sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el último de ellos cierre.",
 "subitems_abiertos":29}
```

Resultado: `estado_aprobacion=aprobado_irving`, `excluir_pool_automatico=true`, `worker_sid` y
`claimed_at` liberados. #936 queda fuera del pool/reaper otra vez — igual que antes de la
directiva de Irving — hasta que el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo
complete solo cuando el último de sus ~29 sub-items abiertos cierre.

## Sin cambio de código de negocio

No se tocó ningún hijo ni su `excluir_pool_automatico` individual: la directiva de Irving de
"construir el mapa completo sin pacing" sigue vigente para MR-04/MR-07 en adelante, que
conservan su propio flag en `false` y siguen siendo elegibles para el pool por `depende_de`. El
trabajo técnico real de la épica sigue en sus ~29 sub-items abiertos.
