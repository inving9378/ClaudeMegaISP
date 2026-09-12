# Item #9990897 — Fase 2b (exención de docs en footprintDeRama/footprintEnVivo) — RESUELTO sin código

## Contexto

Sub-item de #9990892 (Fase 2 de la exención de docs del detector de colisiones del Circuito CC,
continuación de #9990863 Fase 1). Su propio spec condicionaba la implementación a la conclusión de
la Fase 2a (#9990896, sub-item hermano en la posición 1): si la evidencia real mostraba que la
colisión de footprint SÍ es causa frecuente de terminales ociosas, implementar el filtro de
exención de docs (`esRutaExentaDeColision()`, a insertar en `RoadmapCircuitoService.php` dentro de
`footprintDeRama()` ~línea 2929 y `footprintEnVivo()` ~línea 2966); si la Fase 2a concluía que NO
lo es, cerrar este sub-item **sin código** — decisión ya tomada por Irving en `q3` del item padre
#9990892 (no forzar un cambio sin evidencia de beneficio).

## Veredicto de la Fase 2a (verificado)

La cadena de reintentos #9990896 → #9990916 → #9990936 → **#9990942** (doc
`docs/circuito-colision-ociosidad-item-9990942-verificacion.md`) midió con datos reales: 56
minutos de operación tras el merge de #9990863, con una colisión de footprint real confirmada
dentro de esa ventana (#9990864 pausado por #9990890), y `storage/logs/circuito-despacho-*.log`
**no registró ni un solo ciclo con slot ocioso**. Con el backlog actual (~1580 items pendientes),
el scheduler llena el slot liberado por una colisión con otro candidato del backlog en el mismo
ciclo — la colisión retrasa un item puntual, pero no deja ninguna terminal ociosa.

**Conclusión:** la colisión de footprint NO es causa frecuente de terminales ociosas en el estado
actual del sistema. No-accionable hoy; re-agendable solo si el backlog algún día baja lo
suficiente para que exista ociosidad real que investigar (en ese escenario sí valdría la pena
repetir la medición e implementar el filtro si aplica).

## Resolución de este item

Por el propio spec de #9990897 y la decisión previa de Irving, se cierra **sin código**:
`cierre_sin_codigo_motivo` + `sin_ui_motivo` + `reporte_coloquial` poblados citando este doc y el
de #9990942. No se tocó `RoadmapCircuitoService.php` (los puntos de inserción ya localizados en el
spec original quedan documentados aquí por si se retoma en el futuro).

**Sin cambio de código de aplicación.** El único artefacto de esta vuelta es este doc + la entrada
de bitácora (`docs/bitacora/2026-09-11-item-9990897.md`).
