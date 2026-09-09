# Item #9990660 — Seguimiento: pregunta sin resolver de #126 — verificación

## Contexto

El item #126 («al dar click en el candado la pantalla se queda en gris y en clientes al aplicar
pago también se queda en gris») se cerró (`completado`, merge `b67346ac`) con 1 pregunta
estructurada que quedó sin `opcion_elegida` en el momento del cierre automático:

> ¿Cómo abordar el bug de pantalla gris al abrir modales (candado en Permisos y aplicar pago en
> Clientes)?

Con 3 opciones: (1) diagnosticar primero y reportar causa raíz antes de tocar código —
recomendada, confianza media, reversible; (2) arreglar directo asumiendo backdrop huérfano; (3)
separar en dos sub-items (Permisos autorizable ya / pago escalado a Irving). El generador de
seguimientos (#1008) creó #9990660 automáticamente al cerrar #126, sin mirar que #126 ya había
sido trabajado en el mismo acto de cierre.

Irving aprobó #9990660 eligiendo `opcion_elegida = b0ffda8cf468f81f`. Verificado por hash
(`RoadmapItem::claveOpcion()` = `substr(sha1(...), 0, 16)` del texto normalizado): ese hash
corresponde a la **Opción 1 — "Diagnosticar primero... y reportar causa raíz antes de tocar
código"**.

## Verificación (2026-09-09, wt-3)

El propio log de #126 muestra que la Opción 1 (diagnosticar primero) **ya fue el camino que se
siguió, en el mismo cierre del padre**:

- `wt-2` investigó la causa raíz dada (leak de CSS no-scoped) y encontró que 2 de los 3 archivos
  de Flotas (`FleetGeofenceShow.vue`/`FleetGeofenceList.vue`) ya usaban `<style scoped>` —no
  había fuga real ahí—, y solo `FleetRuleList.vue` compartía el mismo patrón. Aun así aplicó el
  mismo hardening (clase propia en vez de `.modal-backdrop` global) en los 3, por consistencia
  con `FleetVehicleShow.vue`.
- Investigación adicional confirmó que la causa raíz REAL del bug reportado por el usuario
  (backdrops huérfanos al navegar por la SPA) **ya estaba corregida en `main` desde el 29 de
  junio** (commit `8bac9038`) — mucho antes de que se abriera #126.
- El `reporte_coloquial` de #126 ya deja escrito el diagnóstico completo, incluida la hipótesis
  de que si el gris persiste hoy es caché de navegador (`Ctrl+Shift+R`), no un bug de código.

Esto es exactamente el proceso que pide la Opción 1: diagnóstico antes de tocar código, en zona
sensible (uno de los dos síntomas reportados era "aplicar pago"), con el hallazgo documentado
antes de aplicar el hardening cosmético. La elección de Irving en #9990660 coincide con lo que ya
ocurrió — la pregunta llegó a esta terminal después de que su propia respuesta ya se hubiera
ejecutado, mismo patrón de carrera de timing documentado en CLAUDE.md para
#733/#741/#753/#9990003/#9990353/#9990658.

### Estado actual del código (re-verificado, sin regresión)

Los 3 archivos de Flotas + `FleetVehicleShow.vue` (los 4 que comparten el patrón de backdrop
manual) siguen usando clases propias del componente, no la regla global `.modal-backdrop`:

| Archivo | Clase del backdrop |
|---|---|
| `FleetGeofenceShow.vue:87` | `flt-geofence-backdrop` |
| `FleetGeofenceList.vue:112` | `flt-geofence-backdrop` |
| `FleetRuleList.vue:131,146` | `flt-rule-backdrop` |
| `FleetVehicleShow.vue:181` | `flt-delete-backdrop` |

Sin ocurrencias de `.modal-backdrop.show` sin scope en ninguno de los 4.

## Veredicto

**Sin cambio de código** — la pregunta de #9990660 ya tiene respuesta ejecutada: se siguió la
Opción 1 (diagnosticar primero), el diagnóstico y el fix quedaron documentados en el cierre de
#126 (merge `b67346ac`), y no hay trabajo pendiente derivado de esta pregunta. Solo se deja
constancia escrita de la verificación, como en los casos previos de esta misma familia.
