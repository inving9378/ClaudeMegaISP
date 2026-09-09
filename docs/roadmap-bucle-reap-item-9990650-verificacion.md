# Item #9990650 — Talento: placeholders de firma en plantillas + colocación en el render (empresa/trabajador): bucle reap sobre paraguas ya descompuesto

## Contexto

`#9990650` ("Talento: placeholders de firma en plantillas + colocación en el render
empresa/trabajador", Fase 3+4 de `#9990646`) es la misma familia de bug documentada
repetidamente en `CLAUDE.md` (#738, #745, #830, #816, #818, #848, #852, #905, #878, #906, #907,
#924, #9990012, #917, #910, #936, #9990408, #962, #9990554, #9990549, #9990624, entre otros): un
item se descompone correctamente en sub-items, pero nadie ejecuta el intento de cierre que
dispara el guard de paraguas (`RoadmapItem.php` bloque "(2b) PARAGUAS"), así que el item se queda
colgado hasta que el reaper lo re-encola y el pool lo vuelve a repartir sin que haya trabajo
propio que hacer.

## Lo que ya se hizo bien (sesión previa)

El item pedía: (a) agregar un placeholder por slot (`{{firma.empresa}}`/`{{firma.trabajador}}`)
en el HTML de las 11 plantillas de Talento que ya declaran slots en
`talento_document_template_signature_slots`; (b) que el renderer (mismo servicio que ya sustituye
`fecha.*` — `EmployeeDocumentPackageService`) inserte la imagen de firma por slot o deje línea en
blanco si falta; (c) marcar el documento "completo" solo cuando todos los slots requeridos tengan
firma, reusando el `status_efectivo` que calcula `#9990649`.

Un `DES-TRABE (Opus)` había escalado el item por anti-loop (ejecutor lo corrió 1× y no lo
ejecutó). Irving lo aprobó con las 3 preguntas estructuradas resueltas (set mínimo de
placeholders, reemplazo in-place con imagen escalada, línea en blanco si falta firma — todas
opción 1 recomendada).

La vuelta previa (`wt-3`, 2026-09-09 08:45) ya hizo lo correcto: corrió `circuito:cabida` (NO
CABE, ya había timeouteado) y descompuso el trabajo en 3 fases secuenciales, sin crear rama ni
tocar código:

- **#9990653** — "Talento: placeholders `{{firma.empresa}}`/`{{firma.trabajador}}` en las 11
  plantillas + nueva versión" (Fase (a), sin dependencias).
- **#9990654** — "Talento: renderer inserta imagen de firma por slot + `EmployeeDocumentPackageService`
  alimenta `firma.*`" (Fase (b); decisión propia registrada: usar data URI base64 inline en vez de
  URL, para no depender de la ruta `slot_key` de `#9990649`).
- **#9990655** — "Talento: `status_efectivo` por slots (reusar de #9990649, no duplicar) +
  verificación final E2E del item padre #9990650" (Fase (c), **bloqueada** con
  `depende_de=[9990649]` hasta que ese item mergee).

## Por qué se quedó colgado

Justo después de escribir la decisión de descomposición en `comentarios_claude`, esa vuelta
terminó sin intentar el cierre del padre (no hay evento de intento de `completado` en el log). El
siguiente evento es `soltar-claim` (sid `wt-3`, 2026-09-09 08:45:29): "La vuelta de wt-3 terminó
sin cerrar el item (muerte del proceso: kill, OOM o freno a media vuelta). Se libera el reclamo y
vuelve a la cola como aprobado_irving". El item volvió a `aprobado_irving` sin
`excluir_pool_automatico`, así que el pool lo repartió de nuevo — esta vez a `wt-1` (esta vuelta,
2026-09-09 14:45).

## Verificación del estado real (esta vuelta)

```
9990650 (yo) | en_progreso     | excl=false | sid=wt-1
  9990653    | pendiente_revision | origen_item_id=9990650 | sin depende_de
  9990654    | pendiente_revision | origen_item_id=9990650 | sin depende_de
  9990655    | pendiente_revision | origen_item_id=9990650 | depende_de=[9990649]
```

Los 3 hijos siguen intactos, sin reclamar (`worker_sid` vacío) — la descomposición original
seguía siendo correcta, nadie más la tocó.

## Corrección aplicada

Esta vuelta ejecuta el intento de cierre faltante (`estado_aprobacion = 'completado'`). El guard
de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo detecta y reenruta:

```
{"por":"paraguas","evento":"paraguas_abierto",
 "motivo":"Este item se descompuso y le quedan 3 sub-item(s) abierto(s): no se completa. Queda
           como paraguas y cierra solo cuando el último de ellos cierre.",
 "subitems_abiertos":3}
```

Resultado: `estado_aprobacion=aprobado_irving`, `excluir_pool_automatico=true`,
`worker_sid`/`claimed_at` limpiados. Queda sacado del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando #9990653, #9990654 y #9990655 cierren
los tres (el último, #9990655, sigue bloqueado por `depende_de=[9990649]`).

## Sin cambio de código de negocio

El trabajo técnico real de Talento (placeholders en las 11 plantillas, renderer con imagen
inline, `status_efectivo` por slots) sigue en #9990653/#9990654/#9990655, pendientes de que una
terminal los reclame (el último, además, de que `#9990649` mergee primero).

## Addendum (2026-09-09, misma tarde) — segunda vuelta del mismo bucle sobre el mismo item

Minutos después del aparcado de arriba, David (delegado de Irving) liberó el paraguas a propósito
(`evento:liberado_por_irving`, 09:01:14, "confirma nivel B: liberar para ejecución. Frente
Documentos.") — `excluir_pool_automatico` volvió a `false`. Eso lo regresó al pool, que lo
repartió de nuevo a `wt-1` (`claimed_at` 15:02:05Z). Mientras tanto **#9990653 y #9990654 ya
habían mergeado** (commits `8a545bb3` y `90785625`, confirmados en `git log` antes de tocar
nada): el trabajo real de las Fases (a) y (b) del padre está hecho. Solo queda **#9990655**
(Fase (c): `status_efectivo` + verificación E2E), que sigue `aprobado_revisor`, sin reclamar, y
**todavía bloqueada** por `depende_de=[9990649]` — ese item ("UI + endpoint de firma POR SLOT en
el expediente") sigue `aprobado_irving`, sin `merge_commit`, sin reclamar.

No hay trabajo propio de `#9990650` que hacer (es puro paraguas, sin código directo). Repetí el
mismo cierre-intento: `estado_aprobacion='completado'` → el guard lo reenrutó otra vez a
`aprobado_irving`+`excluir_pool_automatico=true` ("le quedan 1 sub-item(s) abierto(s)"),
liberando `worker_sid`/`claimed_at`. Cerrará solo cuando `#9990655` cierre (y eso espera a que
`#9990649` mergee primero). **Sin cambio de código** en esta vuelta tampoco.
