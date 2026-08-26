# Verificación del item #109 — .catch() silenciosos que ocultan errores 500

El item #109 pedía dos cosas sobre `resources/js/`:

1. Auditar los `.catch()` que son callbacks vacíos (`() => {}`) y reemplazarlos por, al menos,
   un `console.error` o un toast.
2. Solución global posible: un interceptor axios que loguee 5xx en consola **siempre**.

**Ambos pasos ya estaban resueltos**, hechos en una sesión previa (2026-06-29) que quedó
documentada en el `log` del propio item pero cuyo `estado_aprobacion` se atoró en un reclamo
huérfano del incidente del 24-ago (footprint desconocido) y sólo se destrabó a la bandeja de
Irving el 2026-08-25. El código nunca se perdió — está en `main` desde junio:

## PASO 1 — commit `84ca3873` (2026-06-29, ya en `main`)

Reemplaza los `.catch(() => {})` silenciosos en 7 archivos (`request.js`, `FleetRuleList.vue`,
`FleetTabMantenimientos.vue`, `FleetVehicleForm.vue`, `MarketingConversationsView.vue`,
`vendors/billing/helper/helper.js`, `warroom/ViewResumen.vue`) por el patrón de notificación
ya existente en cada archivo (`$q.notify`/toastr) + `console.error(status, endpoint)`. Detalle
completo del inventario (11 casos, por qué cada uno se resolvió así) en el mensaje del commit
y en el primer registro del `log` del item.

## PASO 2 — commit `82e5d8eb` (2026-06-29, ya en `main`)

Interceptor axios global en `resources/js/bootstrap.js` (~línea 62 en adelante): cualquier
respuesta con `status >= 500` deja rastro en consola (`[axios 5xx] {status} {method} {url} —
{mensaje del backend}`) y luego re-lanza el error (`Promise.reject`) para no alterar el flujo
— los `.catch()` locales, arreglados o no, siguen recibiendo el error exactamente igual que
antes. Único singleton axios (`window.axios === import axios from 'axios'`), así que basta
registrarlo una vez.

## Verificación hecha en esta vuelta (2026-08-26)

- `git merge-base --is-ancestor 84ca3873 HEAD` y `... 82e5d8eb HEAD` → ambos son ancestros de
  `main` actual.
- `grep` de patrones `.catch(() => {})` / `.catch(function(){})` en todo `resources/js/`: solo
  queda **uno**, `MarketingConversationsView.vue:206` (mark-as-read), dejado a propósito según
  el propio log del PASO 1 ("side-effect de fondo que se re-sincroniza al recargar; un fallo
  no engaña al usuario"). Ese mismo request ya pasa por el interceptor global del PASO 2 antes
  de llegar al `.catch` local, así que incluso ese caso deja rastro en consola si el backend
  responde 5xx.

**Sin cambio de código funcional.** Este commit solo agrega este documento de verificación
(mismo patrón que `docs/permisos-olt-item-414-verificacion.md`), para dejar registrado por qué
el item se cierra sin una nueva implementación.
