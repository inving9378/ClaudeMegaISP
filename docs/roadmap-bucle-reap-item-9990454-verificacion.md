# Item #9990454 — MR-23 Fase 4b — bucle reap sobre paraguas ya descompuesto

## Resumen

Mismo patrón documentado repetidamente para esta familia de bugs del propio Circuito CC
(#738, #745, #830, #816, #818, #848, #852, #905, #878, #906, #907, #924, #9990012, #917,
#910, #936, #9990408, #962, entre otros): una vuelta previa ya hizo el trabajo de
investigación y descomposición correctos, pero el proceso murió antes de **intentar cerrar**
el item padre — dejándolo como paraguas huérfano que el reaper re-encola y el pool vuelve a
repartir sin que haya trabajo propio pendiente.

## Qué pasó

La vuelta anterior (`wt-5`, 2026-09-07 15:51) investigó el item #9990454 ("MR-23 fase 4b —
acción 'Ver impacto'") y encontró un hallazgo crítico: la capa **semántica** que el texto
original citaba como ruta del recorrido (`mapared_puertos`, `mapared_hilos`,
`mapared_cables`, `mapared_splitters`, `mapared_empalmes`, `mapared_enlaces_servicio`) está
**en 0 filas en dev** — mismo bloqueo raíz ya documentado en `#9990496`/`#952` (el backfill de
MR-15 nunca corrió contra dev). El propio item exige verificar el recorrido con datos reales
antes de darlo por bueno, así que construirlo sobre esa capa vacía sería imposible de
verificar.

Con ese hallazgo, `wt-5` descompuso correctamente el trabajo en dos sub-items
(`origen_item_id=9990454`):

- **#9990555** — Fase 4b-i: `ImpactoAnalysisService` (backend + endpoint + permiso), con la
  decisión ya documentada de construir el recorrido sobre la **capa cruda** real
  (`mapared_devices` 8785 filas, `mapared_devices_ports` 19229 filas,
  `mapared_devices_ports_connections` 8740 filas, `mapared_fibers` 17242 filas) en vez de la
  capa semántica vacía — cambio de interpretación respecto al texto original que preserva su
  intención y sí permite verificar con datos reales.
- **#9990556** — Fase 4b-ii: UI "Ver impacto" (menú contextual + panel Quasar), correctamente
  **bloqueada** hasta que #9990555 esté mergeado a main (necesita el endpoint y el permiso).

Pero el proceso de `wt-5` murió a media vuelta **antes de intentar cerrar** #9990454 — el log
solo registra `claim_liberado_al_morir_la_vuelta` (15:51:40). El item volvió a `aprobado_irving`
sin `excluir_pool_automatico`, y quedó disponible para que el pool lo repartiera de nuevo sin
que hubiera nada propio que hacer (el trabajo real ya vive en los dos sub-items).

## Verificación de esta vuelta

Confirmado contra la BD real de dev:

- `#9990555` — sigue intacto, `requiere_irving`, `worker_sid=null` (sin reclamar).
- `#9990556` — sigue intacto, `pendiente_revision`, `worker_sid=null` (sin reclamar), con su
  bloqueo de dependencia hacia `#9990555` documentado en su propia `description`.

Ningún otro proceso tocó la descomposición desde que `wt-5` la creó — sigue siendo correcta y
no requiere rehacerse.

## Corrección aplicada

Se ejecuta el intento de cierre que faltaba: `estado_aprobacion = 'completado'` sobre
`#9990454`. El guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo
detecta con sub-items abiertos y lo reenruta a `aprobado_irving` +
`excluir_pool_automatico=true`, sacándolo del pool/reaper hasta que el hook de cierre en
cascada (`RoadmapItem.php:459-491`) lo complete solo cuando `#9990555` y `#9990556` cierren
los dos.

## Sin cambio de código de negocio

El trabajo técnico real de MR-23 Fase 4b (servicio de impacto sobre la capa cruda + UI del
menú contextual) sigue en `#9990555` (pendiente de que Irving lo apruebe, `requiere_irving`) y
`#9990556` (`pendiente_revision`, bloqueado hasta que el backend esté en main). Este item solo
documenta y aplica el cierre-intento faltante.
