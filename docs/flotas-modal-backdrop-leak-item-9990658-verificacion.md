# Item #9990658 — Fix leak de `.modal-backdrop` en 3 componentes Flotas — verificación

## Contexto

El item #9990658 nació como sub-item de seguimiento de #126 (2026-09-09 09:16), con el
diagnóstico exacto ya hecho: `FleetGeofenceShow.vue`, `FleetGeofenceList.vue` y
`FleetRuleList.vue` tenían un `<style>` NO-scoped con la regla global
`.modal-backdrop.show { z-index: 9998; opacity: .5 }`, que Vue inyecta en `<head>` para TODA la
app, subiendo el backdrop por encima de otros modales (candado de administración/permisos,
aplicar pago en clientes) y dejando la pantalla en gris. El mismo patrón ya se había corregido
antes solo en `FleetVehicleShow.vue` (clase propia `.flt-delete-backdrop` en vez de la global).

## Verificación (2026-09-09, wt-1)

Al llegar a ejecutar el item, el fix **ya estaba aplicado en `main`**:

```
29156e4c fix(flotas): evita que el backdrop de 3 modales tape ModalSimple/pagos
```

Commit directo de Irving (`tu@email.com`), fechado 09:18:56 — **2 minutos después** de que el
item #9990658 se creara (09:16:59) como seguimiento de #126, y ya integrado a `main` antes de que
el pool lo repartiera a esta terminal (`b67346ac`, la integración de #126, es posterior en el
log). Carrera de timing: mientras se diagnosticaba #126, se aplicó el fix directo en el mismo
acto, y el generador de seguimientos creó #9990658 sin ver que ya se había resuelto — mismo
patrón documentado en CLAUDE.md para #733/#741/#753/#9990003/#9990353 (fix aplicado por la sesión
que investigaba, seguimiento generado sobre el spec original antes de verlo).

### Confirmación línea por línea

Los 3 archivos objetivo tienen `<style scoped>` (no `<style>` a secas) y usan clases propias del
componente en el div del backdrop, en vez de la clase global:

| Archivo | `<style>` tag | Clase del backdrop | Regla renombrada |
|---|---|---|---|
| `FleetGeofenceShow.vue:187,200` | `scoped` | `flt-geofence-backdrop` (línea 87) | `.flt-geofence-backdrop.show { z-index: 9998; opacity: .5 }` |
| `FleetGeofenceList.vue:205,224` | `scoped` | `flt-geofence-backdrop` (línea 112) | ídem |
| `FleetRuleList.vue:285,296` | `scoped` | `flt-rule-backdrop` (líneas 131, 146) | `.flt-rule-backdrop.show { z-index: 9998; opacity: .5 }` |

`grep` confirma que no queda ninguna regla `.modal-backdrop.show` NO-scoped en los 3 archivos —
solo el comentario explicativo que menciona `.modal-backdrop` en prosa. `ModalSimple.vue` no fue
tocado (fuera de alcance, como pedía el spec).

## Veredicto

**Sin cambio de código** — el fix ya estaba mergeado a `main` desde antes de que esta terminal
reclamara el item. No se requiere build ni verificación visual adicional: el commit `29156e4c` ya
resuelve exactamente lo que pedía el spec, archivo por archivo.
